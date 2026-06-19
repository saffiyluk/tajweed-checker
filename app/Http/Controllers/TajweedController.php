<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\AudioRecitation;
use App\Models\AnalysisResult;
use App\Services\GeminiFeedbackService;
use App\Services\QuranTranscriptionMatcher;
use App\Services\TajweedAnalysisService;
use Kreait\Firebase\Factory;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class TajweedController extends Controller
{
    // Firebase Storage instance. When Firebase is not configured, this stays null and local storage is used.
    protected $storage;

    // Service that handles local Tajweed analysis helper logic, such as reading audio duration.
    protected $tajweedService;

    // Service that asks Gemini to improve/produce user-friendly recitation feedback.
    protected $geminiFeedbackService;

    // Service that maps rough blind transcription to the closest Quran ayah.
    protected $quranTranscriptionMatcher;

    public function __construct(
        TajweedAnalysisService $tajweedService,
        GeminiFeedbackService $geminiFeedbackService,
        QuranTranscriptionMatcher $quranTranscriptionMatcher
    )
    {
        // Every route in this controller requires a logged-in user.
        $this->middleware('auth');
        $this->tajweedService = $tajweedService;
        $this->geminiFeedbackService = $geminiFeedbackService;
        $this->quranTranscriptionMatcher = $quranTranscriptionMatcher;

        if (!config('tajweed.use_firebase_storage', false)) {
            $this->storage = null;
            return;
        }

        try {
            $credentialsPath = base_path(config('firebase.credentials'));
            $storageBucket = config('firebase.storage_bucket');

            if (empty($credentialsPath) || empty($storageBucket)) {
                \Log::info('Firebase not configured - using local storage');
                $this->storage = null;
                return;
            }

            if (file_exists($credentialsPath)) {
                $factory = (new Factory())
                    ->withServiceAccount($credentialsPath)
                    ->withProjectId('tajweed-detection-fyp')
                    ->withDefaultStorageBucket($storageBucket);

                // Create the Firebase Storage client once so upload/download methods can reuse it.
                $this->storage = $factory->createStorage();
                \Log::info('Firebase Storage initialized from file: ' . $credentialsPath);
            } else {
                \Log::warning('Firebase credentials file not found: ' . $credentialsPath);
                $this->storage = null;
            }
        } catch (\Exception $e) {
            \Log::warning('Firebase initialization failed: ' . $e->getMessage());
            $this->storage = null;
        }
    }

    /**
     * Handle audio upload and submit for analysis
     */
    public function upload(Request $request)
    {
        $this->extendExecutionLimit();

        \Log::info('Tajweed upload request started', [
            'user_id' => Auth::id(),
            'has_file' => $request->hasFile('audio'),
            'has_base64' => $request->filled('audio_base64'),
            'rule' => $request->input('tajweed_rule'),
        ]);

        // Accept either an uploaded audio file or a base64 browser recording.
        // Keep audio validation extension-based because browser/Windows MIME types vary a lot.
        $validator = Validator::make($request->all(), [
            'audio' => 'nullable|file|max:51200',
            'audio_base64' => 'nullable|string',
            'tajweed_rule' => 'required|in:ikhfa,izhar',
            'selected_ayah' => 'nullable|string|max:3000',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->hasFile('audio') && !$request->filled('audio_base64')) {
                $validator->errors()->add('audio', 'Please upload or record an audio file.');
            }

            if ($request->hasFile('audio')) {
                $extension = strtolower($request->file('audio')->getClientOriginalExtension());
                $allowedExtensions = ['mp3', 'wav', 'webm', 'm4a', 'ogg', 'oga', 'flac', 'aac', 'mp4'];

                if (!in_array($extension, $allowedExtensions, true)) {
                    $validator->errors()->add('audio', 'Unsupported audio format. Please use MP3, WAV, WEBM, M4A, OGG, FLAC, AAC, or MP4.');
                }
            }
        });

        if ($validator->fails()) {
            \Log::warning('Tajweed upload validation failed', [
                'user_id' => Auth::id(),
                'errors' => $validator->errors()->toArray(),
                'file_name' => $request->hasFile('audio') ? $request->file('audio')->getClientOriginalName() : null,
                'mime' => $request->hasFile('audio') ? $request->file('audio')->getMimeType() : null,
                'extension' => $request->hasFile('audio') ? $request->file('audio')->getClientOriginalExtension() : null,
            ]);

            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $userId = $user->id;

        try {
            $audioData = null;
            $filename = null;

            // Case 1: normal file upload from an input field.
            if ($request->hasFile('audio')) {
                $file = $request->file('audio');
                $audioData = file_get_contents($file->getRealPath());
                $filename = $file->getClientOriginalName();
            // Case 2: browser-recorded audio sent as a base64 data URL.
            } elseif ($request->filled('audio_base64')) {
                $base64Data = $request->input('audio_base64');
                if (preg_match('/^data:(audio\/[-\w.+]+)(?:;codecs=[^;,]+)?;base64,/i', $base64Data, $matches)) {
                    $audioData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1), true);

                    if ($audioData === false) {
                        return back()->with('error', 'Failed to decode audio data');
                    }

                    $mimeType = strtolower($matches[1]);
                    $extensionMap = [
                        'audio/webm' => 'webm',
                        'audio/wav' => 'wav',
                        'audio/x-wav' => 'wav',
                        'audio/mpeg' => 'mp3',
                        'audio/mp3' => 'mp3',
                        'audio/mp4' => 'mp4',
                        'audio/x-m4a' => 'm4a',
                        'audio/aac' => 'aac',
                        'audio/ogg' => 'ogg',
                        'audio/opus' => 'opus',
                    ];
                    $extension = $extensionMap[$mimeType] ?? 'webm';
                    $filename = 'recording_' . time() . '.' . $extension;
                } else {
                    \Log::warning('Invalid audio base64 payload received', [
                        'user_id' => $userId,
                        'prefix' => substr($base64Data, 0, 80),
                    ]);
                    return back()->with('error', 'Invalid audio data format');
                }
            } else {
                return back()->with('error', 'No audio file provided');
            }

            if (!$audioData) {
                return back()->with('error', 'Failed to process audio data');
            }

            $rule = $request->input('tajweed_rule');
            \Log::info('Tajweed upload audio payload decoded', [
                'user_id' => $userId,
                'filename' => $filename,
                'bytes' => strlen($audioData),
                'rule' => $rule,
            ]);

            // Firebase paths are grouped by user and rule so files are easy to find later.
            $firebaseStoragePath = "users/{$userId}/audios/{$rule}/" . uniqid() . '_' . $filename;
            $firebaseUrl = null;

            // Try Firebase first. If it fails or is not configured, fall back to Laravel public storage.
            if ($this->storage) {
                try {
                    $contentType = $request->hasFile('audio')
                        ? $file->getMimeType()
                        : 'audio/' . pathinfo($filename, PATHINFO_EXTENSION);

                    $bucket = $this->storage->getBucket();

                    // Store the raw audio bytes and attach metadata that is useful in Firebase.
                    $bucket->upload($audioData, [
                        'name' => $firebaseStoragePath,
                        'metadata' => [
                            'contentType' => $contentType,
                            'userId' => (string) $userId,
                            'rule' => $rule,
                            'uploadedAt' => now()->toIso8601String(),
                            'filename' => $filename,
                        ]
                    ]);

                    // Confirm the object exists before saving the path in the database.
                    $object = $bucket->object($firebaseStoragePath);
                    if ($object->exists()) {
                        $objectInfo = $object->info();
                        $fileSize = isset($objectInfo['size']) ? (int) $objectInfo['size'] : strlen($audioData);
                        $uploadedAt = isset($objectInfo['timeCreated']) ? $objectInfo['timeCreated'] : now()->toIso8601String();

                        \Log::info("Audio uploaded to Firebase", [
                            'path' => $firebaseStoragePath,
                            'size' => $fileSize,
                            'uploaded_at' => $uploadedAt,
                        ]);
                    } else {
                        \Log::error("Firebase upload verification failed for: {$firebaseStoragePath}");
                    }

                    $object = $bucket->object($firebaseStoragePath);
                    // Long-lived signed URL lets the app play/download Firebase audio later.
                    $firebaseUrl = $object->signedUrl(
                        new \DateTime('+10 years')
                    );

                    \Log::info("Audio uploaded to Firebase: {$firebaseStoragePath}");
                } catch (\Exception $e) {
                    \Log::warning("Firebase upload failed, saving locally: " . $e->getMessage());
                    $this->storeLocally($request, $userId, $rule, $filename, $audioData, $firebaseStoragePath, $firebaseUrl);
                }
            } else {
                $this->storeLocally($request, $userId, $rule, $filename, $audioData, $firebaseStoragePath, $firebaseUrl);
            }

            // Save a temporary local copy so the analysis service can calculate duration.
            $tempPath = storage_path('app/temp_' . uniqid() . '.wav');
            file_put_contents($tempPath, $audioData);

            $duration = $this->tajweedService->getAudioDuration($tempPath);
            unlink($tempPath);

            // Main audio record: who uploaded it, where it is stored, and which rule it belongs to.
            $audioRecitation = AudioRecitation::create([
                'user_id' => Auth::id(),
                'audio_file_path' => $firebaseStoragePath,
                'tajweed_rule' => $rule,
                'original_filename' => $filename,
                'duration_seconds' => $duration,
                'firebase_url' => $firebaseUrl,
            ]);

            // Create the result row immediately so the UI can show "pending" while analysis runs.
            AnalysisResult::create([
                'audio_id' => $audioRecitation->id,
                'correctness' => null,
                'confidence_score' => 0,
                'processing_status' => 'pending',
                'feedback_message' => 'Your audio is being analyzed. Please wait...',
            ]);

            \Log::info("Audio recitation created for user {$userId} with rule {$rule}");
            $analysisOutcome = $this->analyzeRecitation(
                $audioRecitation,
                $audioData,
                $request->input('selected_ayah')
            );

            if (($analysisOutcome['status'] ?? null) === 'timeout') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation->id)
                    ->with('error', 'Prediction took too long. Please try a shorter recording.');
            }

            // If the request was made via XHR/fetch, return JSON with a redirect URL
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'redirect' => route('tajweed.result', $audioRecitation->id),
                    'id' => $audioRecitation->id,
                ], 200);
            }

            return redirect()
                ->route('tajweed.result', $audioRecitation->id)
                ->with('success', 'Audio uploaded successfully! Analysis in progress...');

        } catch (\Exception $e) {
            \Log::error('Tajweed upload error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    // Helper function for local storage fallback
    private function storeLocally($request, $userId, $rule, $filename, $audioData, &$path, &$url)
    {
        // Normal uploads can be moved directly; base64 recordings must be written from raw bytes.
        if ($request->hasFile('audio')) {
            $localPath = Storage::disk('public')->putFileAs(
                "tajweed/{$userId}/{$rule}",
                $request->file('audio'),
                $filename
            );
        } else {
            $localPath = "tajweed/{$userId}/{$rule}/" . uniqid() . '_' . $filename;
            Storage::disk('public')->put($localPath, $audioData);
        }

        $path = $localPath;
        $url = asset("storage/{$localPath}");
        \Log::info("Audio stored locally (Firebase unavailable): {$localPath}");
    }

    private function analyzeRecitation(AudioRecitation $audioRecitation, string $audioData, ?string $selectedAyah = null): array
    {
        $this->extendExecutionLimit();

        $analysisResult = AnalysisResult::firstOrCreate(
            ['audio_id' => $audioRecitation->id],
            [
                'correctness' => null,
                'confidence_score' => 0,
                'processing_status' => 'pending',
                'feedback_message' => 'Your audio is being analyzed. Please wait...',
            ]
        );

        $analysisResult->update([
            'correctness' => null,
            'confidence_score' => 0,
            'processing_status' => 'pending',
            'feedback_message' => 'Your audio is being analyzed. Please wait...',
            'transcribed_text' => null,
            'detected_errors' => null,
            'suggestions' => null,
        ]);

        if (config('tajweed.enable_service_analysis', false)) {
            $this->tajweedService->analyzeAudio($audioRecitation);
        }

        $filename = $audioRecitation->original_filename ?: ('audio_' . $audioRecitation->id . '.wav');
        $audioPath = storage_path('app/' . uniqid('predict_', true) . '_' . basename($filename));
        file_put_contents($audioPath, $audioData);

        try {
            $pythonBinary = config('tajweed.python_binary', 'python');

            [$output, $result] = $this->runPythonJson(
                $pythonBinary,
                base_path('python/predict.py'),
                $audioPath,
                config('tajweed.prediction_timeout', 60)
            );

            if (!$result) {
                throw new \RuntimeException('Invalid response from Python prediction script: ' . $output);
            }

            if (($result['status'] ?? null) === 'timeout') {
                $analysisResult->update([
                    'processing_status' => 'failed',
                    'feedback_message' => 'Prediction took too long. Please try a shorter recording, or run the app with a Python environment where TensorFlow loads faster.',
                    'confidence_score' => 0,
                    'correctness' => 'incorrect',
                ]);

                return ['status' => 'timeout'];
            }

            if (isset($result['error'])) {
                throw new \RuntimeException('Python prediction failed: ' . $result['error']);
            }

            $prediction = $result['prediction'] ?? 'unknown';
            $confidence = round(($result['confidence'] ?? 0) * 100);
            $margin = round(($result['margin'] ?? 0) * 100);
            $selectedRule = $audioRecitation->tajweed_rule;
            $knownRules = ['ikhfa', 'izhar'];
            $cnnPrediction = data_get($result, 'cnn.raw_prediction');
            $cnnConfidence = round((float) data_get($result, 'cnn.confidence', 0) * 100);
            $featureOtherConfidence = round((float) data_get($result, 'feature_model.other_confidence', 0) * 100);
            $usedOppositeRuleFallback = false;

            if (
                in_array($cnnPrediction, $knownRules, true)
                && $cnnPrediction !== $selectedRule
                && $cnnConfidence >= config('tajweed.opposite_rule_confidence_threshold', 45)
                && $featureOtherConfidence < config('tajweed.strong_other_confidence_threshold', 65)
                && (($result['status'] ?? null) === 'unrelated' || $prediction === 'other')
            ) {
                $prediction = $cnnPrediction;
                $confidence = max($confidence, $cnnConfidence);
                $usedOppositeRuleFallback = true;
            }

            $isUnrelatedAudio = !$usedOppositeRuleFallback
                && (($result['status'] ?? null) === 'unrelated'
                || $prediction === 'other'
                || (
                    $confidence < config('tajweed.unrelated_confidence_threshold', 55)
                    && $margin < config('tajweed.unrelated_margin_threshold', 10)
                ));

            \Log::info('Tajweed prediction interpreted', [
                'selected_rule' => $selectedRule,
                'prediction' => $prediction,
                'confidence' => $confidence,
                'cnn_prediction' => $cnnPrediction,
                'cnn_confidence' => $cnnConfidence,
                'feature_other_confidence' => $featureOtherConfidence,
                'used_opposite_rule_fallback' => $usedOppositeRuleFallback,
                'is_unrelated_audio' => $isUnrelatedAudio,
            ]);

            if ($isUnrelatedAudio) {
                $prediction = 'other';
                $feedback = "This recording does not appear to contain a clear Ikhfa or Izhar example. Please upload or record a Quran recitation segment that includes the selected tajweed rule, then try again with less background noise.";
            } elseif ($prediction == $selectedRule) {
                $feedback = $confidence >= 80
                    ? "Good recitation. " . ucfirst($selectedRule) . " is correct."
                    : "Recitation matches " . ucfirst($selectedRule) . ", but confidence is only {$confidence}%. Please try again for a clearer reading.";
            } else {
                $feedback = "Recitation appears incorrect. Detected: " . ucfirst($prediction) . " with {$confidence}% confidence.";
            }

            $transcribeOutput = 'Transcription skipped.';
            $transcribeResult = ['status' => 'skipped', 'text' => ''];
            $transcribedText = $this->resolveTranscriptionText('', $selectedAyah);
            $quranMatch = null;

            if (trim((string) $selectedAyah) === '' && config('tajweed.enable_transcription', false)) {
                [$transcribeOutput, $transcribeResult] = $this->runPythonJson(
                    $pythonBinary,
                    base_path('python/transcribe.py'),
                    $audioPath,
                    config('tajweed.transcription_timeout', 90)
                );

                $transcribedText = $this->resolveTranscriptionText($transcribeResult['text'] ?? '', null);

                if (($transcribeResult['status'] ?? null) !== 'success') {
                    \Log::warning('Whisper transcription failed: ' . trim($transcribeOutput));
                } elseif (config('tajweed.enable_quran_matching', true)) {
                    $quranMatch = $this->quranTranscriptionMatcher->match($transcribeResult['text'] ?? '');

                    if ($quranMatch) {
                        $transcribedText = $quranMatch['text'];

                        \Log::info('Blind transcription matched Quran ayah', [
                            'audio_id' => $audioRecitation->id,
                            'surah' => $quranMatch['surah'],
                            'ayah' => $quranMatch['ayah'],
                            'score' => $quranMatch['score'],
                            'margin' => $quranMatch['margin'],
                            'raw_text' => $transcribeResult['text'] ?? '',
                        ]);
                    }
                }
            }

            if (config('tajweed.enable_diacritization', true)) {
                $transcribedText = $quranMatch
                    ? $transcribedText
                    : $this->geminiFeedbackService->diacritizeArabic($transcribedText);
            }

            if (config('tajweed.enable_ai_feedback', false)) {
                $feedback = $this->geminiFeedbackService->generate(
                    $selectedRule,
                    $prediction,
                    $confidence,
                    $transcribedText,
                    $feedback
                );
            }

            $analysisResult->update([
                'processing_status' => 'completed',
                'feedback_message' => $feedback,
                'transcribed_text' => $transcribedText,
                'confidence_score' => $confidence,
                'correctness' => (!$isUnrelatedAudio && $prediction == $selectedRule) ? 'correct' : 'incorrect',
                'detected_errors' => $isUnrelatedAudio ? [
                    [
                        'error' => 'Unrelated or unclear audio',
                        'type' => 'unrelated_audio',
                    ],
                ] : null,
                'suggestions' => $isUnrelatedAudio ? [
                    'Record only the Quran recitation segment for the selected rule.',
                    'Avoid music, speech, silence, or background noise.',
                    'Use the Other dataset in the admin panel to improve unrelated-audio detection.',
                ] : null,
            ]);

            session([
                'tajweed_result' => $result,
            ]);

            \Log::info('Python raw output: ' . trim($output));
            \Log::info('Python result:', $result);
            \Log::info('Whisper raw output: ' . trim($transcribeOutput));
            \Log::info('Tajweed upload analysis completed', [
                'audio_id' => $audioRecitation->id,
                'prediction' => $prediction,
                'confidence' => $confidence,
                'redirect' => route('tajweed.result', $audioRecitation->id),
            ]);

            return ['status' => 'completed'];
        } finally {
            if (file_exists($audioPath)) {
                @unlink($audioPath);
            }
        }
    }

    private function loadStoredAudio(AudioRecitation $audioRecitation): string
    {
        if ($this->storage && strpos((string) $audioRecitation->audio_file_path, 'users/') === 0) {
            $bucket = $this->storage->getBucket();
            $object = $bucket->object($audioRecitation->audio_file_path);

            if ($object->exists()) {
                return $object->downloadAsString();
            }
        }

        $localPath = (string) $audioRecitation->audio_file_path;

        if ($localPath !== '' && Storage::disk('public')->exists($localPath)) {
            return Storage::disk('public')->get($localPath);
        }

        if (strpos($localPath, 'public/') === 0) {
            $trimmedPath = substr($localPath, 7);

            if (Storage::disk('public')->exists($trimmedPath)) {
                return Storage::disk('public')->get($trimmedPath);
            }
        }

        throw new \RuntimeException('Stored audio file could not be found for re-analysis.');
    }

    private function extendExecutionLimit(int $seconds = 300): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
    }

    /**
     * Run a Python script and parse its JSON response.
     *
     * The scripts sometimes print warnings before/after JSON, so this method can
     * recover the first JSON object from mixed stdout/stderr output.
     */
    private function runPythonJson(string $pythonBinary, string $scriptPath, string $audioPath, int $timeoutSeconds): array
    {
        $process = new Process([$pythonBinary, $scriptPath, $audioPath]);
        $process->setTimeout($timeoutSeconds);
        $process->setEnv($this->pythonProcessEnvironment());

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            \Log::warning('Python script timed out', [
                'script' => $scriptPath,
                'timeout_seconds' => $timeoutSeconds,
            ]);

            return [
                'Timed out after ' . $timeoutSeconds . ' seconds',
                [
                    'status' => 'timeout',
                    'error' => 'Timed out after ' . $timeoutSeconds . ' seconds',
                ],
            ];
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $output = trim($stdout . "\n" . $stderr);

        $result = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonOutput = $this->extractJsonObject($output);
            $result = $jsonOutput ? json_decode($jsonOutput, true) : null;
        }

        if ($stderr !== '') {
            \Log::warning('Python script stderr: ' . $stderr, [
                'script' => $scriptPath,
            ]);
        }

        return [$output, $result];
    }

    /**
     * Extract a JSON object from noisy command output.
     */
    private function extractJsonObject(string $output): ?string
    {
        $start = strpos($output, '{');
        $end = strrpos($output, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return substr($output, $start, $end - $start + 1);
    }

    /**
     * Prefer the selected ayah when the user provided one; otherwise use Whisper text.
     */
    private function resolveTranscriptionText(?string $whisperText, ?string $selectedAyah): string
    {
        $selectedAyah = trim((string) $selectedAyah);

        if ($selectedAyah !== '') {
            return $selectedAyah;
        }

        $whisperText = trim((string) $whisperText);

        return $whisperText !== '' ? $whisperText : 'Unable to transcribe audio';
    }

    /**
     * Provide a predictable Windows-friendly environment for Python, TensorFlow, and Whisper.
     */
    private function pythonProcessEnvironment(): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
        $path = getenv('PATH') ?: getenv('Path') ?: '';
        $temp = getenv('TEMP') ?: sys_get_temp_dir();
        $tmp = getenv('TMP') ?: $temp;
        $pythonHome = storage_path('app/python-home');
        $appData = $pythonHome . DIRECTORY_SEPARATOR . 'AppData';
        $localAppData = $appData . DIRECTORY_SEPARATOR . 'Local';
        $roamingAppData = $appData . DIRECTORY_SEPARATOR . 'Roaming';

        foreach ([$pythonHome, $localAppData, $roamingAppData] as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }

        $windowsPythonHome = str_replace('/', '\\', $pythonHome);
        $drive = preg_match('/^[A-Za-z]:/', $windowsPythonHome) ? substr($windowsPythonHome, 0, 2) : 'C:';
        $homePath = preg_match('/^[A-Za-z]:(.*)$/', $windowsPythonHome, $matches) ? $matches[1] : $windowsPythonHome;

        return [
            'PATH' => $path,
            'Path' => $path,
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => getenv('WINDIR') ?: $systemRoot,
            'HOME' => $pythonHome,
            'USERPROFILE' => $pythonHome,
            'HOMEDRIVE' => $drive,
            'HOMEPATH' => $homePath,
            'APPDATA' => $roamingAppData,
            'LOCALAPPDATA' => $localAppData,
            'KERAS_HOME' => $pythonHome . DIRECTORY_SEPARATOR . '.keras',
            'TEMP' => $temp,
            'TMP' => $tmp,
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
            'TF_CPP_MIN_LOG_LEVEL' => '3',
            'TF_ENABLE_ONEDNN_OPTS' => '0',
            'WHISPER_MODEL' => config('tajweed.whisper_model', 'small'),
        ];
    }

    // Test Firebase connection
    public function testFirebaseStorage()
    {
        try {
            $credentialsPath = base_path(config('firebase.credentials'));
            $bucketName = config('firebase.storage_bucket');

            // Log the important configuration values before trying to connect.
            \Log::info('Firebase Test - Credentials: ' . $credentialsPath);
            \Log::info('Firebase Test - Bucket: ' . $bucketName);
            \Log::info('Firebase Test - File exists: ' . (file_exists($credentialsPath) ? 'YES' : 'NO'));

            if (!file_exists($credentialsPath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Firebase credentials file not found',
                    'path' => $credentialsPath
                ], 404);
            }

            if (empty($bucketName)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'FIREBASE_STORAGE_BUCKET not set in .env'
                ], 400);
            }

            $factory = (new Factory())
                ->withServiceAccount($credentialsPath)
                ->withDefaultStorageBucket($bucketName);

            $storage = $factory->createStorage();

            if ($storage === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Firebase Storage initialization failed - createStorage() returned null'
                ], 500);
            }

            $bucket = $storage->getBucket();

            return response()->json([
                'status' => 'success',
                'bucket' => $bucket->name(),
                'message' => 'Firebase Storage connected successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Firebase test error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function result(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        $result = $audioRecitation->analysisResult()
            ->with('audio')
            ->first();

        return view('tajweed.result', compact('result'));
    }

    /**
     * Display user's recitation history
     * 
     * @return \Illuminate\View\View
     */
    public function history()
    {
        $user = Auth::user();

        // Get this user's recordings with their analysis rows, newest first.
        $recitations = AudioRecitation::where('user_id', $user->id)
            ->with('analysisResult')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('tajweed.history', compact('recitations'));
    }

    public function reanalyze(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        try {
            $audioData = $this->loadStoredAudio($audioRecitation);
            $outcome = $this->analyzeRecitation($audioRecitation, $audioData);

            if (($outcome['status'] ?? null) === 'timeout') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation)
                    ->with('error', 'Re-analysis took too long. Please try again later.');
            }

            return redirect()
                ->route('tajweed.result', $audioRecitation)
                ->with('success', 'Analysis re-ran successfully.');
        } catch (\Throwable $e) {
            \Log::error('Tajweed re-analysis error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());

            return redirect()
                ->route('tajweed.result', $audioRecitation)
                ->with('error', 'Re-analysis failed: ' . $e->getMessage());
        }
    }

    public function storeCorrection(Request $request, AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        $validated = $request->validate([
            'prediction_feedback' => 'required|in:correct,incorrect,unsure',
            'transcription_feedback' => 'required|in:correct,incorrect,unsure',
            'corrected_rule' => 'nullable|in:ikhfa,izhar,other',
            'corrected_transcription' => 'nullable|string|max:5000',
            'correction_note' => 'nullable|string|max:2000',
        ]);

        $analysisResult = $audioRecitation->analysisResult()->firstOrCreate(
            ['audio_id' => $audioRecitation->id],
            [
                'confidence_score' => 0,
                'processing_status' => 'completed',
            ]
        );

        $analysisResult->update([
            'prediction_feedback' => $validated['prediction_feedback'],
            'transcription_feedback' => $validated['transcription_feedback'],
            'corrected_rule' => $validated['corrected_rule'] ?? null,
            'corrected_transcription' => $validated['corrected_transcription'] ?? null,
            'correction_note' => $validated['correction_note'] ?? null,
            'correction_review_status' => 'pending',
            'correction_admin_note' => null,
            'correction_submitted_by' => Auth::id(),
            'correction_reviewed_by' => null,
            'correction_submitted_at' => now(),
            'correction_reviewed_at' => null,
        ]);

        Log::info('Tajweed correction submitted', [
            'audio_id' => $audioRecitation->id,
            'user_id' => Auth::id(),
            'prediction_feedback' => $validated['prediction_feedback'],
            'transcription_feedback' => $validated['transcription_feedback'],
            'corrected_rule' => $validated['corrected_rule'] ?? null,
        ]);

        return redirect()
            ->route('tajweed.result', $audioRecitation)
            ->with('success', 'Thanks. Your correction was sent for admin review.');
    }

    /**
     * Get analysis result (AJAX)
     * 
     * @param AudioRecitation $audioRecitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalysisStatus(AudioRecitation $audioRecitation)
    {
        // Route model binding gives the AudioRecitation. Policy check prevents viewing another user's audio.
        $this->authorize('view', $audioRecitation);

        $result = $audioRecitation
            ->analysisResult;

        if (!$result) {
            return response()
                ->json([
                    'status'
                    => 'pending',
                    'message'
                    => 'Analysis not yet started'
                ]);
        }

        return response()->json([
            'status' => $result->processing_status,
            'correctness' => $result->correctness,
            'confidence' => $result->confidence_score,
            'feedback' => $result->feedback_message,
            'transcribed_text' => $result->transcribed_text,
            'errors' => $result->detected_errors,
            'suggestions' => $result->suggestions,
        ]);
    }

    public function debugFirebase()
    {
        $credentialsPath = base_path(config('firebase.credentials'));
        $bucketName = config('firebase.storage_bucket');

        // Return these values in the response to make .env/config problems easier to spot.
        $debugInfo = [
            'credentials_path' => $credentialsPath,
            'credentials_exists' => file_exists($credentialsPath),
            'bucket_name' => $bucketName,
            'bucket_empty' => empty($bucketName),
        ];

        if (!file_exists($credentialsPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credentials file not found',
                'debug' => $debugInfo
            ], 404);
        }

        if (empty($bucketName)) {
            return response()->json([
                'status' => 'error',
                'message' => 'FIREBASE_STORAGE_BUCKET not set in .env',
                'debug' => $debugInfo
            ], 400);
        }

        if (!$this->storage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Firebase Storage not initialized in constructor',
                'debug' => $debugInfo
            ], 500);
        }

        try {
            $bucket = $this->storage->getBucket();

            // Verify that the configured bucket is the bucket Firebase actually opened.
            $actualBucketName = $bucket->name();

            if ($actualBucketName !== $bucketName) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Bucket mismatch',
                    'expected' => $bucketName,
                    'actual' => $actualBucketName,
                    'debug' => $debugInfo
                ]);
            }

            // Upload a tiny test file to prove the app has write access.
            $bucket->upload("test-connection-" . time(), [
                'name' => 'debug/test-' . time() . '.txt'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Firebase Storage connected successfully!',
                'bucket' => $actualBucketName,
                'debug' => $debugInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'debug' => $debugInfo,
                'trace' => $e->getLine() . ' in ' . $e->getFile()
            ], 500);
        }
    }

    // Show Ikhfa Haqiqi page
    public function ikhfaHaqiqi()
    {
        return view('tajweed.ikhfaHaqiqi');
    }

    // Show Izhar Halqi page
    public function izharHalqi()
    {
        return view('tajweed.izharHalqi');
    }

    public function destroy(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        // Delete the stored audio file before deleting the database row.
        if ($audioRecitation->audio_file_path) {
            // Local public-disk file.
            if (Storage::disk('public')->exists($audioRecitation->audio_file_path)) {
                Storage::disk('public')->delete($audioRecitation->audio_file_path);
            }

            // Firebase file paths start with users/.
            if ($this->storage && strpos($audioRecitation->audio_file_path, 'users/') === 0) {
                try {
                    $bucket = $this->storage->getBucket();
                    $object = $bucket->object($audioRecitation->audio_file_path);
                    if ($object->exists()) {
                        $object->delete();
                        \Log::info("Deleted Firebase file: {$audioRecitation->audio_file_path}");
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to delete Firebase file: " . $e->getMessage());
                }
            }
        }

        // Delete the analysis result that belongs to this recording.
        if ($audioRecitation->analysisResult) {
            $audioRecitation->analysisResult->delete();
        }

        // Finally delete the audio record itself.
        $audioRecitation->delete();

        return redirect()->route('tajweed.history')->with('success', 'Recording deleted successfully.');
    }

    public function download(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        // Firebase files are downloaded by redirecting to their signed URL.
        if ($audioRecitation->firebase_url) {
            return redirect($audioRecitation->firebase_url);
        }

        // Local files can be streamed directly from Laravel's public disk.
        if ($audioRecitation->audio_file_path && Storage::disk('public')->exists($audioRecitation->audio_file_path)) {
            return Storage::disk('public')->download($audioRecitation->audio_file_path, $audioRecitation->original_filename);
        }

        return back()->with('error', 'File not found.');
    }

    public function getAudioUrl(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        $url = null;

        // Prefer Firebase URL when available; otherwise build a public local-storage URL.
        if ($audioRecitation->firebase_url) {
            $url = $audioRecitation->firebase_url;
        } elseif ($audioRecitation->audio_file_path) {
            $localPath = $audioRecitation->audio_file_path;
            if (strpos($localPath, 'public/') === 0) {
                $localPath = substr($localPath, 7);
            }

            if (Storage::disk('public')->exists($localPath)) {
                $url = Storage::disk('public')->url($localPath);
            }
        }

        return response()->json([
            'success' => !empty($url),
            'url' => $url,
            'has_firebase' => !empty($audioRecitation->firebase_url),
            'has_local' => !empty($audioRecitation->audio_file_path),
        ]);
    }

    public function playAudio(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        if ($audioRecitation->firebase_url) {
            // If it is already a signed URL, redirect to it.
            if (strpos($audioRecitation->firebase_url, 'sign=') !== false) {
                return redirect($audioRecitation->firebase_url);
            }

            // Otherwise generate a fresh signed URL that lasts one hour.
            try {
                if ($this->storage && strpos($audioRecitation->audio_file_path, 'users/') === 0) {
                    $bucket = $this->storage->getBucket();
                    $object = $bucket->object($audioRecitation->audio_file_path);
                    $signedUrl = $object->signedUrl(new \DateTime('+1 hour'));
                    return redirect($signedUrl);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to generate signed URL: ' . $e->getMessage());
            }

            // Fallback to the stored URL if a fresh signed URL could not be generated.
            return redirect($audioRecitation->firebase_url);
        }

        // Stream local storage files directly so the browser can play them inline.
        if ($audioRecitation->audio_file_path) {
            $localPath = $audioRecitation->audio_file_path;
            if (strpos($localPath, 'public/') === 0) {
                $localPath = substr($localPath, 7);
            }

            if (Storage::disk('public')->exists($localPath)) {
                $file = Storage::disk('public')->path($localPath);
                $mime = mime_content_type($file) ?: 'audio/mpeg';

                return response()->file($file, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline',
                    'Cache-Control' => 'public, max-age=31536000'
                ]);
            }
        }

        abort(404, 'Audio file not found');
    }

    public function playDatasetAudio(string $rule, string $filename)
    {
        if (!in_array($rule, ['ikhfa', 'izhar'], true) || basename($filename) !== $filename) {
            abort(404, 'Audio file not found');
        }

        $datasetDirectory = realpath(base_path("python/dataset/{$rule}"));
        $file = realpath(base_path("python/dataset/{$rule}/{$filename}"));

        if (!$datasetDirectory || !$file || !str_starts_with($file, $datasetDirectory) || !is_file($file)) {
            abort(404, 'Audio file not found');
        }

        $mime = mime_content_type($file) ?: 'audio/wav';

        return response()->file($file, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function analyzeAudio(Request $request)
    {
        // Lightweight endpoint that only runs speech_to_text.py and returns its JSON.
        $audioPath = $request->file('audio')->getPathname();
        $pythonBinary = config('tajweed.python_binary', 'python');
        $command = escapeshellarg($pythonBinary) . " " . escapeshellarg(base_path("python/speech_to_text.py")) . " " . escapeshellarg($audioPath);
        $output = shell_exec($command);
        $result = json_decode($output, true);

        return response()->json($result);
    }


}
