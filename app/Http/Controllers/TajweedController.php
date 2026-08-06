<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\AudioRecitation;
use App\Models\AnalysisResult;
use App\Services\GeminiFeedbackService;
use App\Services\QuranTranscriptionMatcher;
use App\Services\TajweedAnalysisService;
use App\Services\TajweedCorrectnessService;
use Kreait\Firebase\Factory;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class TajweedController extends Controller
{
    private const MAX_AUDIO_BYTES = 50 * 1024 * 1024;

    // Firebase Storage instance. When Firebase is not configured, this stays null and local storage is used.
    protected $storage;

    // Service that handles local Tajweed analysis helper logic, such as reading audio duration.
    protected $tajweedService;

    // Service that asks Gemini to improve/produce user-friendly recitation feedback.
    protected $geminiFeedbackService;

    // Service that maps rough blind transcription to the closest Quran ayah.
    protected $quranTranscriptionMatcher;

    protected $tajweedCorrectnessService;

    public function __construct(
        TajweedAnalysisService $tajweedService,
        GeminiFeedbackService $geminiFeedbackService,
        QuranTranscriptionMatcher $quranTranscriptionMatcher,
        TajweedCorrectnessService $tajweedCorrectnessService
    )
    {
        // Every route in this controller requires a logged-in user.
        $this->middleware('auth');
        $this->tajweedService = $tajweedService;
        $this->geminiFeedbackService = $geminiFeedbackService;
        $this->quranTranscriptionMatcher = $quranTranscriptionMatcher;
        $this->tajweedCorrectnessService = $tajweedCorrectnessService;

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

        // ===== REPORT SCREENSHOT START: Section 4.3.1 - Input Validation =====
        // Accept either an uploaded audio file or a base64 browser recording.
        // Keep audio validation extension-based because browser/Windows MIME types vary a lot.
        $validator = Validator::make($request->all(), [
            'audio' => 'nullable|file|max:51200',
            'audio_base64' => 'nullable|string',
            'tajweed_rule' => 'required|in:ikhfa,izhar',
            'selected_ayah' => 'required|string|max:3000',
            'source_surah' => 'nullable|integer|min:1|max:114|required_with:source_ayah',
            'source_ayah' => 'nullable|integer|min:1|max:286|required_with:source_surah',
            'browser_transcript' => 'nullable|string|max:5000',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->hasFile('audio') && !$request->filled('audio_base64')) {
                $validator->errors()->add('audio', 'Please upload or record an audio file.');
            }

            if ($request->filled('audio_base64')) {
                $maximumEncodedLength = (int) ceil(self::MAX_AUDIO_BYTES * 4 / 3) + 1024;

                if (strlen((string) $request->input('audio_base64')) > $maximumEncodedLength) {
                    $validator->errors()->add('audio', 'The recorded audio must not be larger than 50 MB.');
                }
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
        // ===== REPORT SCREENSHOT END: Section 4.3.1 - Input Validation =====

        $user = Auth::user();
        $userId = $user->id;

        try {
            $audioData = null;
            $filename = null;

            // ===== REPORT SCREENSHOT START: Section 4.3.3 - Audio Submission Decoding =====
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

                    if (strlen($audioData) > self::MAX_AUDIO_BYTES) {
                        return back()->withErrors([
                            'audio' => 'The recorded audio must not be larger than 50 MB.',
                        ])->withInput();
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
            // ===== REPORT SCREENSHOT END: Section 4.3.3 - Audio Submission Decoding =====

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

            // Keep a local mirror as the canonical analysis path. Firebase can still
            // be used for playback via firebase_url, but re-analysis should not fail
            // just because Google OAuth/network is unavailable.
            if (strpos($firebaseStoragePath, 'users/') === 0) {
                $localMirrorPath = "tajweed/{$userId}/{$rule}/" . uniqid() . '_' . $filename;
                Storage::disk('public')->put($localMirrorPath, $audioData);

                \Log::info('Firebase upload mirrored locally for re-analysis', [
                    'firebase_path' => $firebaseStoragePath,
                    'local_path' => $localMirrorPath,
                ]);

                $firebaseStoragePath = $localMirrorPath;
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
                $request->input('selected_ayah'),
                $request->input('browser_transcript'),
                $request->filled('source_surah') ? (int) $request->input('source_surah') : null,
                $request->filled('source_ayah') ? (int) $request->input('source_ayah') : null
            );

            if (($analysisOutcome['status'] ?? null) === 'timeout') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation->id)
                    ->with('error', 'Prediction took too long. Please try a shorter recording.');
            }

            if (($analysisOutcome['status'] ?? null) === 'failed') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation->id)
                    ->with('error', 'The analysis could not be completed. Please check the recording and try again.');
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
            return back()->with('error', 'The recording could not be saved or analyzed. Please try again.');
        }
    }

    // Helper function for local storage fallback
    private function storeLocally($request, $userId, $rule, $filename, $audioData, &$path, &$url)
    {
        // Normal uploads can be moved directly; base64 recordings must be written from raw bytes.
        if ($request->hasFile('audio')) {
            $storedFilename = uniqid('', true) . '_' . basename($filename);
            $localPath = Storage::disk('public')->putFileAs(
                "tajweed/{$userId}/{$rule}",
                $request->file('audio'),
                $storedFilename
            );
        } else {
            $localPath = "tajweed/{$userId}/{$rule}/" . uniqid() . '_' . $filename;
            Storage::disk('public')->put($localPath, $audioData);
        }

        $path = $localPath;
        $url = asset("storage/{$localPath}");
        \Log::info("Audio stored locally (Firebase unavailable): {$localPath}");
    }

    private function analyzeRecitation(
        AudioRecitation $audioRecitation,
        string $audioData,
        ?string $selectedAyah = null,
        ?string $browserTranscript = null,
        ?int $sourceSurah = null,
        ?int $sourceAyah = null
    ): array
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
            'predicted_rule' => null,
            'classification_status' => null,
            'classification_method' => null,
            'class_probabilities' => null,
            'model_predictions' => null,
            'confidence_score' => 0,
            'processing_status' => 'processing',
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

            // ===== REPORT SCREENSHOT START: Sections 4.2.3 and 4.3.4 - Backend Analysis Invocation =====
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
                    'classification_status' => 'failed',
                    'feedback_message' => 'Prediction took too long. Please try a shorter recording, or run the app with a Python environment where TensorFlow loads faster.',
                    'confidence_score' => 0,
                    'correctness' => null,
                ]);

                return ['status' => 'timeout'];
            }

            if (isset($result['error'])) {
                throw new \RuntimeException('Python prediction failed: ' . $result['error']);
            }

            $prediction = $result['prediction'] ?? 'unknown';
            $confidence = round(($result['confidence'] ?? 0) * 100);
            $margin = round(($result['margin'] ?? 0) * 100);
            // ===== REPORT SCREENSHOT END: Sections 4.2.3 and 4.3.4 - Backend Analysis Invocation =====
            $selectedRule = $audioRecitation->tajweed_rule;
            $cnnPrediction = data_get($result, 'cnn.raw_prediction');
            $cnnConfidence = round((float) data_get($result, 'cnn.confidence', 0) * 100);
            $featureOtherConfidence = round((float) data_get($result, 'feature_model.other_confidence', 0) * 100);
            $quality = (array) data_get($result, 'quality', []);
            $ghunnahDurationMs = (float) data_get($quality, 'ghunnah_duration_ms', 0);
            $audioInputIssue = $this->detectAudioInputIssue($quality);
            $pythonSaysUnrelated = ($result['status'] ?? null) === 'unrelated' || ($result['prediction'] ?? null) === 'other';
            $ikhfaMinGhunnahMs = (int) config('tajweed.ikhfa_min_ghunnah_ms', 80);
            $ikhfaMinLocalGhunnahMs = (int) config('tajweed.ikhfa_min_local_ghunnah_ms', 50);
            $izharMaxGhunnahMs = (int) config('tajweed.izhar_max_ghunnah_ms', 50);
            $ruleBasedAnalysisEnabled = (bool) config('tajweed.enable_rule_based_analysis', false);

            // Keep the ensemble decision intact. A single CNN branch or the selected
            // ayah must not turn an "other/unrelated" result into a confident rule.
            $isUnrelatedAudio = $audioInputIssue !== null
                || $pythonSaysUnrelated
                || (
                    $ruleBasedAnalysisEnabled
                    && $confidence < config('tajweed.unrelated_confidence_threshold', 55)
                    && $margin < config('tajweed.unrelated_margin_threshold', 10)
                );

            \Log::info('Tajweed prediction interpreted', [
                'selected_rule' => $selectedRule,
                'prediction' => $prediction,
                'confidence' => $confidence,
                'cnn_prediction' => $cnnPrediction,
                'cnn_confidence' => $cnnConfidence,
                'feature_other_confidence' => $featureOtherConfidence,
                'ghunnah_duration_ms' => $ghunnahDurationMs,
                'ikhfa_min_ghunnah_ms' => $ikhfaMinGhunnahMs,
                'ikhfa_min_local_ghunnah_ms' => $ikhfaMinLocalGhunnahMs,
                'izhar_max_ghunnah_ms' => $izharMaxGhunnahMs,
                'rule_based_analysis_enabled' => $ruleBasedAnalysisEnabled,
                'is_unrelated_audio' => $isUnrelatedAudio,
                'audio_input_issue' => $audioInputIssue,
                'quality' => $quality,
            ]);

            if ($isUnrelatedAudio) {
                $prediction = 'other';
                $feedback = $audioInputIssue['message']
                    ?? "This recording does not appear to contain a clear Ikhfa or Izhar example. Please upload or record a Quran recitation segment that includes the selected tajweed rule, then try again with less background noise.";
            } elseif ($prediction == $selectedRule) {
                $feedback = $confidence >= 80
                    ? "Good recitation. " . ucfirst($selectedRule) . " is correct."
                    : "Recitation matches " . ucfirst($selectedRule) . ", but confidence is only {$confidence}%. Please try again for a clearer reading.";
            } else {
                $feedback = "Recitation appears incorrect. Detected: " . ucfirst($prediction) . " with {$confidence}% confidence.";
            }

            $transcribeOutput = 'Transcription skipped.';
            $transcribeResult = ['status' => 'skipped', 'text' => ''];
            $browserTranscript = trim((string) $browserTranscript);
            $selectedAyahText = trim((string) $selectedAyah);
            $speechTranscript = '';
            $speechTranscriptSource = 'none';
            $speechTranscriptNote = null;

            $browserTranscriptLetterCount = $this->countArabicLetters($browserTranscript);
            $minBrowserTranscriptLetters = (int) config('tajweed.min_browser_transcript_letters', 8);
            $browserTranscriptLooksGarbage = $this->isLikelyGarbageTranscription($browserTranscript);
            $hasBrowserTranscript = $browserTranscript !== ''
                && $browserTranscriptLetterCount >= $minBrowserTranscriptLetters
                && !$browserTranscriptLooksGarbage;
            $browserTranscriptIndicatesUnrelated = $this->transcriptLooksNonArabicSpeech($browserTranscript);

            if ($browserTranscript !== '' && !$hasBrowserTranscript) {
                \Log::info('Ignoring short browser transcript for Tajweed analysis', [
                    'audio_id' => $audioRecitation->id,
                    'browser_transcript' => $browserTranscript,
                    'arabic_letter_count' => $browserTranscriptLetterCount,
                    'minimum_letters' => $minBrowserTranscriptLetters,
                    'looks_like_garbage' => $browserTranscriptLooksGarbage,
                ]);

                $browserTranscript = '';
            }

            if ($hasBrowserTranscript) {
                $speechTranscript = $browserTranscript;
                $speechTranscriptSource = 'browser';
            }

            $transcribedText = $this->resolveTranscriptionText($browserTranscript, $selectedAyahText);
            $quranMatch = null;

            if (!$hasBrowserTranscript && config('tajweed.enable_transcription', false)) {
                [$transcribeOutput, $transcribeResult] = $this->runPythonJson(
                    $pythonBinary,
                    base_path('python/transcribe.py'),
                    $audioPath,
                    config('tajweed.transcription_timeout', 90)
                );

                $rawTranscribedText = $this->resolveTranscriptionText($transcribeResult['text'] ?? '', null);
                $rawTranscribedLetterCount = $this->countArabicLetters($rawTranscribedText);

                if (($transcribeResult['status'] ?? null) !== 'success') {
                    \Log::warning('Whisper transcription failed: ' . trim($transcribeOutput));
                } elseif ($rawTranscribedLetterCount < $minBrowserTranscriptLetters || $this->isLikelyGarbageTranscription($rawTranscribedText)) {
                    \Log::info('Ignoring short Whisper transcript for Tajweed analysis', [
                        'audio_id' => $audioRecitation->id,
                        'raw_transcript' => $rawTranscribedText,
                        'arabic_letter_count' => $rawTranscribedLetterCount,
                        'minimum_letters' => $minBrowserTranscriptLetters,
                        'looks_like_garbage' => $this->isLikelyGarbageTranscription($rawTranscribedText),
                    ]);

                    $speechTranscriptNote = 'Whisper ran, but did not return enough clear Arabic text.';
                    if ($selectedAyahText === '') {
                        $transcribedText = 'Unable to transcribe audio';
                    }
                } else {
                    $speechTranscript = $rawTranscribedText;
                    $speechTranscriptSource = 'whisper';

                    if ($selectedAyahText === '') {
                        $transcribedText = $rawTranscribedText;
                    }
                }

                if ($selectedAyahText === '' && $speechTranscript !== '' && config('tajweed.enable_quran_matching', true)) {
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

            $transcribedTextHasDiacritics = (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}\x{06E1}]/u', $transcribedText);

            if (
                $transcribedText !== 'Unable to transcribe audio'
                && $this->countArabicLetters($transcribedText) >= $minBrowserTranscriptLetters
                && !$transcribedTextHasDiacritics
                && config('tajweed.enable_diacritization', true)
            ) {
                $transcribedText = $quranMatch
                    ? $transcribedText
                    : $this->geminiFeedbackService->diacritizeArabic($transcribedText);
            }

            $speechTranscriptForDisplay = $speechTranscript !== '' ? $speechTranscript : null;
            $transcriptForQualityGate = $speechTranscript !== '' ? $speechTranscript : $transcribedText;
            $transcriptIndicatesUnrelated = $browserTranscriptIndicatesUnrelated
                || $this->transcriptLooksNonArabicSpeech($transcriptForQualityGate);

            // Compare the words the microphone actually captured with the selected
            // ayah. `transcribedText` cannot be used for this gate because it
            // deliberately prefers the selected reference text for highlighting.
            [$sourceSurah, $sourceAyah, $selectedReferenceQuranMatch] = $this->resolveSelectedAyahCoordinates(
                $selectedAyahText,
                $sourceSurah,
                $sourceAyah
            );

            $speechQuranMatch = null;
            if ($selectedAyahText !== ''
                && $speechTranscript !== ''
                && $sourceSurah !== null
                && $sourceAyah !== null
                && config('tajweed.enable_quran_matching', true)) {
                $speechQuranMatch = $this->quranTranscriptionMatcher->match($speechTranscript);
            }

            $recitationMatchAssessment = $this->assessSelectedAyahMatch(
                $selectedAyahText,
                $speechTranscript,
                $speechQuranMatch,
                $sourceSurah,
                $sourceAyah
            );

            \Log::info('Selected-ayah speech validation completed', [
                'audio_id' => $audioRecitation->id,
                'assessment' => $recitationMatchAssessment,
            ]);

            if ($transcriptIndicatesUnrelated) {
                $isUnrelatedAudio = true;
                $prediction = 'other';
                $feedback = 'The recording/transcript does not look like Arabic Quran recitation, so Tajweed correctness cannot be judged. Please record only the selected Quran segment.';
            }

            if (config('tajweed.enable_ai_feedback', false) && !$isUnrelatedAudio) {
                $feedback = $this->geminiFeedbackService->generate(
                    $selectedRule,
                    $prediction,
                    $confidence,
                    $speechTranscriptForDisplay ?: $transcribedText,
                    $feedback
                );
            }

            $transcribedTextHasTajweedMarks = (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}\x{06E1}]/u', $transcribedText);
            $ruleDetectionText = $ruleBasedAnalysisEnabled
                ? ($selectedAyahText !== ''
                    ? $selectedAyahText
                    : ($transcribedTextHasTajweedMarks ? trim($transcribedText) : ''))
                : '';
            $ruleDetectionSource = $ruleBasedAnalysisEnabled
                ? ($selectedAyahText !== '' ? 'selected ayah' : 'transcription')
                : 'disabled';
            $ruleTargets = $ruleDetectionText !== ''
                ? $this->detectTajweedTargets($ruleDetectionText)
                : [];
            $ruleContextChecked = $ruleDetectionText !== '';
            $selectedRuleTargets = collect($ruleTargets)
                ->filter(fn(array $target): bool => ($target['rule'] ?? null) === $selectedRule)
                ->values();

            // The real rule is detected from the Quran text. The selected rule is only the user's choice/filter.
            // If the selected rule does not exist in the ayah, still analyze the actual detected targets,
            // but mark the overall attempt as incorrect/mismatch instead of trusting the selected rule.
            $hasDetectedRuleTargets = collect($ruleTargets)->isNotEmpty();
            $selectedRuleMismatch = $ruleContextChecked && $hasDetectedRuleTargets && $selectedRuleTargets->isEmpty();
            $ruleContextValid = !$ruleContextChecked || $hasDetectedRuleTargets;
            $hasSelectedAyahContext = $selectedAyahText !== '' && $ruleContextValid && $hasDetectedRuleTargets;
            $detectedErrors = [];
            $suggestions = [];
            \Log::info('Tajweed text rule detection completed', [
                'audio_id' => $audioRecitation->id,
                'selected_rule' => $selectedRule,
                'rule_detection_source' => $ruleDetectionSource,
                'rule_detection_text' => $ruleDetectionText,
                'targets' => $ruleTargets,
                'selected_rule_mismatch' => $selectedRuleMismatch,
            ]);

            $hasRequiredGhunnah = $selectedRule !== 'ikhfa' || $ghunnahDurationMs >= $ikhfaMinGhunnahMs;
            $targetResults = [];
            $pronunciationEvidence = [];
            $referencePronunciationVerified = false;
            $transcriptionUnclear = trim((string) $selectedAyah) === ''
                && !$hasBrowserTranscript
                && (
                    $transcribedText === 'Unable to transcribe audio'
                    || $this->countArabicLetters($transcribedText) < $minBrowserTranscriptLetters
                );
            $canRunReferencePronunciationAnalysis = (bool) config('tajweed.enable_quran_pronunciation_model', true)
                && $hasSelectedAyahContext
                && $audioInputIssue === null;

            if ($ruleContextChecked) {
                if ($isUnrelatedAudio && !$canRunReferencePronunciationAnalysis) {
                    \Log::info('Skipping per-target Tajweed validation because audio is unrelated/unclear', [
                        'audio_id' => $audioRecitation->id,
                        'audio_input_issue' => $audioInputIssue,
                        'python_says_unrelated' => $pythonSaysUnrelated,
                        'transcript_indicates_unrelated' => $transcriptIndicatesUnrelated ?? false,
                    ]);
                } elseif ($ruleContextValid) {
                    if ($hasSelectedAyahContext && !$pythonSaysUnrelated && $audioInputIssue === null) {
                        $isUnrelatedAudio = false;
                    }

                    if ($selectedRuleMismatch) {
                        $detectedRules = collect($ruleTargets)
                            ->pluck('rule')
                            ->unique()
                            ->values()
                            ->implode(', ');

                        $detectedErrors[] = [
                            'error' => ucfirst($ruleDetectionSource) . ' contains ' . ($detectedRules !== '' ? $detectedRules : 'another rule') . ', not the selected ' . ucfirst($selectedRule) . ' rule.',
                            'type' => 'selected_rule_mismatch',
                            'selected_rule' => $selectedRule,
                            'detected_rules' => $detectedRules !== '' ? $detectedRules : 'none',
                            'targets' => $ruleTargets,
                        ];

                        $suggestions[] = 'Use the rule detected from the ayah text for correctness validation. If the ayah contains Izhar, do not validate it as Ikhfa, and vice versa.';
                    }

                    $targetResults = $this->analyzeTajweedTargetResults(
                        $ruleTargets,
                        data_get($result, 'quality', []),
                        $ikhfaMinGhunnahMs,
                        $ikhfaMinLocalGhunnahMs,
                        $izharMaxGhunnahMs
                    );
                    $targetResults = $this->applyTargetWindowModel(
                        $pythonBinary,
                        $audioPath,
                        $ruleTargets,
                        $targetResults
                    );
                    $targetResults = $this->applyQuranPronunciationModel(
                        $pythonBinary,
                        $audioPath,
                        $selectedAyahText,
                        $ruleTargets,
                        $targetResults,
                        $pronunciationEvidence,
                        $sourceSurah,
                        $sourceAyah
                    );

                    $referencePronunciationVerified = (bool) ($pronunciationEvidence['reference_verified'] ?? false);

                    if ($referencePronunciationVerified) {
                        // The general classifier sees only a broad rule pattern and can
                        // call a full ayah "Other". A high-confidence phoneme alignment
                        // against the selected Quran text is direct evidence that the
                        // recording is relevant, so it may safely clear that gate.
                        $isUnrelatedAudio = false;
                        $transcriptionUnclear = false;
                    }

                    $targetResults = $this->enforcePresenceBasedGhunnahRules(
                        $targetResults,
                        data_get($result, 'quality', []),
                        $selectedRule,
                        $ikhfaMinGhunnahMs,
                        $ikhfaMinLocalGhunnahMs,
                        $izharMaxGhunnahMs
                    );
                    $targetResults = $this->enforceIzharNasalSafety($targetResults);
                    $targetResults = $this->applyHybridRuleAudioFallback(
                        $targetResults,
                        data_get($result, 'quality', [])
                    );
                    $targetResults = $this->neutralizeUntrustedTargetDecisions($targetResults);
                    // This is intentionally last: target-aligned Quran phonemes
                    // (or the local duration fallback) are the final elongation
                    // rule and must not be overwritten by a broad classifier,
                    // global ghunnah, or the conservative-pass fallback.
                    $targetResults = $this->applyElongationDurationRules($targetResults);
                    $targetSummary = implode(', ', array_slice($this->summarizeTajweedTargets($ruleTargets), 0, 3));
                    $targetCounts = collect($ruleTargets)->countBy('rule');
                    $feedback .= " Rule scan found " . count($ruleTargets) . " tajweed target"
                        . (count($ruleTargets) === 1 ? '' : 's')
                        . " (" . (int) ($targetCounts['ikhfa'] ?? 0) . " Ikhfa, " . (int) ($targetCounts['izhar'] ?? 0) . " Izhar)"
                        . ($targetSummary !== '' ? ": {$targetSummary}." : '.');
                } else {
                    $detectedRules = collect($ruleTargets)
                        ->pluck('rule')
                        ->unique()
                        ->values()
                        ->implode(', ');

                    $detectedErrors[] = [
                        'error' => ucfirst($ruleDetectionSource) . ' does not contain any supported Ikhfa or Izhar trigger.',
                        'type' => 'rule_target_missing',
                        'expected_rule' => $selectedRule,
                        'detected_rules' => $detectedRules !== '' ? $detectedRules : 'none',
                        'targets' => $ruleTargets,
                    ];

                    $suggestions[] = 'Choose an ayah segment that contains nun sakinah or tanwin followed by an Ikhfa or Izhar letter.';
                    $feedback .= " Rule scan did not find any supported Ikhfa or Izhar target in the {$ruleDetectionSource}, so this attempt needs the correct ayah chunk before the ML result is trusted.";
                }
            }

            $silentOrNoRecitation = $this->isSilentOrNoRecitation($quality, $audioInputIssue);
            // Global content alignment is independent from the target verdict
            // and error-explanation stage. A target/explainer failure must not
            // discard a valid global PER proving that a different ayah was read.
            $referenceModelContentChecked = $this->hasUsableReferenceContentEvidence(
                $pronunciationEvidence
            );
            $referenceContentMismatch = $referenceModelContentChecked
                && (bool) ($pronunciationEvidence['content_mismatch'] ?? false);
            $referenceModelConfidence = is_numeric($pronunciationEvidence['model_confidence'] ?? null)
                ? (float) $pronunciationEvidence['model_confidence']
                : 0.0;
            $referenceContentAuthoritative = $referencePronunciationVerified
                || ($referenceModelContentChecked
                    && $referenceModelConfidence >= (float) config(
                        'tajweed.quran_pronunciation_min_model_confidence',
                        0.72
                    ));

            // A high-confidence Muaalem alignment is direct audio evidence and
            // overrides a conflicting browser/Whisper transcript. Otherwise a
            // confident phoneme or actual-speech mismatch is an input-validation
            // outcome, not a pronunciation error.
            $selectedAyahMismatch = $this->hasSelectedAyahMismatch(
                $referenceContentAuthoritative,
                $referenceContentMismatch,
                $recitationMatchAssessment
            );
            $hasCompleteTrustedTargetEvidence = count($targetResults) > 0
                && collect($targetResults)->every(
                    fn (array $targetResult): bool => $this->hasTrustedTargetDecision($targetResult)
                );
            $recitationVerified = $this->isRecitationContentVerified(
                $referencePronunciationVerified,
                $referenceContentAuthoritative,
                $referenceContentMismatch,
                $recitationMatchAssessment
            );
            $analysisPipelineFailed = $this->shouldFailAnalysisPipeline(
                $pronunciationEvidence,
                $silentOrNoRecitation,
                $audioInputIssue,
                $referencePronunciationVerified,
                $hasCompleteTrustedTargetEvidence
            );

            if ($silentOrNoRecitation || $selectedAyahMismatch) {
                $inputReason = $silentOrNoRecitation
                    ? 'No recitation was detected, so this target was not evaluated.'
                    : 'The recitation did not match the selected ayah, so this target was not evaluated.';
                $targetResults = array_map(fn (array $targetResult): array => array_merge($targetResult, [
                    'status' => 'uncertain',
                    'reason' => $inputReason,
                    'target_window_decision_source' => $silentOrNoRecitation
                        ? 'no_recitation_input_gate'
                        : 'selected_ayah_mismatch_input_gate',
                ]), $targetResults);
            } elseif ($analysisPipelineFailed) {
                $targetResults = array_map(fn (array $targetResult): array => array_merge($targetResult, [
                    'status' => 'analysis_failed',
                    'reason' => 'Target pronunciation analysis failed. No target verdict was assigned.',
                ]), $targetResults);
            }

            if ($transcriptionUnclear) {
                $isUnrelatedAudio = true;
                $prediction = 'other';
                $detectedErrors[] = [
                    'error' => 'Transcription was too short or unclear to locate the Ikhfa/Izhar target.',
                    'type' => 'transcription_unclear',
                ];

                $suggestions[] = 'Select the exact ayah segment before recording, or paste the corrected transcript after analysis.';
                $suggestions[] = 'Record a slightly longer Quran segment that includes the nun sakinah or tanwin and the following letter.';
                $feedback = 'The app could not transcribe enough Arabic text to locate the Ikhfa or Izhar target, so Tajweed correctness cannot be judged reliably. Please select the exact ayah chunk or record a clearer Quran segment.';
            }

            $izharSoundsLikeIkhfa = $selectedRule === 'izhar'
                && (
                    ($prediction === 'ikhfa' && $confidence >= config('tajweed.opposite_rule_confidence_threshold', 45))
                    || ($cnnPrediction === 'ikhfa' && $cnnConfidence >= config('tajweed.opposite_rule_confidence_threshold', 45))
                );
            $hasTrustedSelectedTargetEvidence = collect($targetResults)
                ->contains(fn (array $targetResult): bool => ($targetResult['rule'] ?? null) === $selectedRule
                    && $this->hasTrustedTargetDecision($targetResult));

            if ($silentOrNoRecitation || $selectedAyahMismatch) {
                $detectedErrors[] = [
                    'error' => $silentOrNoRecitation
                        ? ($audioInputIssue['message'] ?? 'The recording contains no recitation.')
                        : 'The captured recitation does not match the selected ayah.',
                    'type' => $silentOrNoRecitation ? 'audio_input_issue' : 'selected_ayah_mismatch',
                    'audio_input_issue' => $audioInputIssue,
                    'transcript_indicates_unrelated' => $transcriptIndicatesUnrelated ?? false,
                    'recitation_match' => $recitationMatchAssessment,
                ];

                $suggestions = array_merge($suggestions, [
                    'Record only the Quran recitation segment for the selected rule.',
                    'Avoid music, speech, silence, or background noise.',
                ]);
            }

            if ($ruleBasedAnalysisEnabled && count($targetResults) === 0 && !$transcriptionUnclear && !$isUnrelatedAudio && $selectedRule === 'ikhfa' && !$hasRequiredGhunnah) {
                $detectedErrors[] = [
                    'error' => 'Ghunnah appears too short or unclear for Ikhfa.',
                    'type' => 'weak_ghunnah',
                    'ghunnah_duration_ms' => $ghunnahDurationMs,
                    'minimum_duration_ms' => $ikhfaMinGhunnahMs,
                ];

                $suggestions[] = 'For Ikhfa, hide the nun or tanwin sound and hold the nasal ghunnah for about two harakah before the next letter.';
                $feedback = "Recitation appears incorrect for Ikhfa. The recording sounds too clear because the estimated ghunnah is only "
                    . round($ghunnahDurationMs)
                    . "ms; try holding the nasal sound longer before the next letter.";
            }

            if ($ruleBasedAnalysisEnabled && !$hasTrustedSelectedTargetEvidence && !$transcriptionUnclear && !$isUnrelatedAudio && $izharSoundsLikeIkhfa) {
                \Log::info('Broad classifier disagreed with selected Izhar rule; retaining as diagnostic only', [
                    'audio_id' => $audioRecitation->id,
                    'prediction' => $prediction,
                    'cnn_prediction' => $cnnPrediction,
                ]);
            }

            foreach ($targetResults as $targetResult) {
                if (($targetResult['status'] ?? null) !== 'incorrect') {
                    continue;
                }

                $detectedErrors[] = [
                    'error' => $targetResult['reason'] ?? 'Tajweed target needs practice.',
                    'type' => 'target_' . ($targetResult['rule'] ?? 'tajweed') . '_error',
                    'target' => $targetResult,
                ];
            }

            if (count($targetResults) > 0) {
                // Correctness is decided across every target detected in the selected
                // ayah, so the feedback must describe that same scope. The stored
                // selected rule is a legacy practice-category value and may be Ikhfa
                // even when the combined checker found both Ikhfa and Izhar.
                $targetResultsForFeedback = collect($targetResults)
                    ->values();
                $evaluatedRuleLabels = $targetResultsForFeedback
                    ->pluck('rule')
                    ->filter(fn ($rule): bool => in_array($rule, ['ikhfa', 'izhar'], true))
                    ->unique()
                    ->sortBy(fn (string $rule): int => $rule === 'ikhfa' ? 0 : 1)
                    ->map(fn (string $rule): string => ucfirst($rule))
                    ->values()
                    ->all();
                $evaluatedRulesText = implode(' and ', $evaluatedRuleLabels);
                $targetScopeDescription = $targetResultsForFeedback->count() === 1
                    ? 'the detected ' . ($evaluatedRulesText !== '' ? $evaluatedRulesText . ' ' : '') . 'target'
                    : 'all ' . $targetResultsForFeedback->count() . ' detected '
                        . ($evaluatedRulesText !== '' ? $evaluatedRulesText . ' ' : '') . 'targets';

                $detectedErrors[] = [
                    'error' => 'Per-target Tajweed analysis completed.',
                    'type' => 'target_analysis',
                    'targets' => $targetResults,
                ];

                foreach ($targetResultsForFeedback as $targetResult) {
                    $suggestions[] = ucfirst($targetResult['rule'] ?? 'target')
                        . ' in "' . ($targetResult['snippet'] ?? '') . '": '
                        . ucfirst($targetResult['status'] ?? 'unknown')
                        . ' - ' . ($targetResult['reason'] ?? 'checked');
                }

                $incorrectTargets = $targetResultsForFeedback
                    ->filter(fn(array $targetResult): bool => ($targetResult['status'] ?? null) !== 'correct')
                    ->values();

                $targetSummaryText = $targetResultsForFeedback
                    ->map(fn(array $targetResult): string => ucfirst($targetResult['rule'] ?? 'target')
                        . ' "' . ($targetResult['snippet'] ?? '') . '" = '
                        . ucfirst($targetResult['status'] ?? 'unknown'))
                    ->take(5)
                    ->implode('; ');

                if ($incorrectTargets->isEmpty()) {
                    $feedback = "The target analysis found no errors, but this is only a correctness decision when a trained target-window model supplied the evidence. {$targetSummaryText}.";
                } else {
                    $feedback = "Some tajweed targets need practice. " . $incorrectTargets
                        ->map(fn(array $targetResult): string => ucfirst($targetResult['rule'] ?? 'target') . ' in "' . ($targetResult['snippet'] ?? '') . '": ' . ($targetResult['reason'] ?? 'needs practice'))
                        ->take(3)
                        ->implode(' ')
                        . " Target summary: {$targetSummaryText}.";
                }

                $suggestions[] = 'Review each highlighted target: Ikhfa needs ghunnah, while Izhar should stay clear without ghunnah.';
            }

            $correctnessEvaluation = $this->tajweedCorrectnessService->evaluate([
                'model_status' => $result['status'] ?? 'unknown',
                'selected_rule' => $selectedRule,
                'prediction' => $prediction,
                'confidence' => $confidence,
                'silent_or_no_recitation' => $silentOrNoRecitation,
                'selected_ayah_mismatch' => $selectedAyahMismatch,
                'recitation_verified' => $recitationVerified,
                'analysis_failed' => $analysisPipelineFailed,
                'selected_rule_mismatch' => $selectedRuleMismatch,
                'rule_context_valid' => $ruleContextValid,
                'target_results' => $targetResults,
            ]);
            $elongationRuleTargets = collect($targetResults)
                ->filter(fn (array $targetResult): bool => ($targetResult['target_window_decision_source'] ?? null) === 'target_elongation_rule')
                ->values();
            $firstElongationError = $elongationRuleTargets
                ->first(fn (array $targetResult): bool => ($targetResult['status'] ?? null) === 'incorrect');
            $allTargetsUseElongationRule = count($targetResults) > 0
                && $elongationRuleTargets->count() === count($targetResults);
            $hybridRuleAudioVerified = !$referencePronunciationVerified
                && in_array($correctnessEvaluation['correctness'], ['correct', 'incorrect'], true)
                && collect($targetResults)->contains(
                    fn (array $targetResult): bool => ($targetResult['target_window_decision_source'] ?? null) === 'hybrid_rule_audio'
                );
            $finalProcessingStatus = (string) ($correctnessEvaluation['processing_status'] ?? 'completed');
            $evaluationFailed = $finalProcessingStatus === 'failed';

            if ($evaluationFailed || $correctnessEvaluation['correctness'] === 'uncertain') {
                $feedback = $correctnessEvaluation['reason'];
            } elseif (is_array($firstElongationError)) {
                $feedback = 'Needs practice. The target elongation check found an error. '
                    . ($firstElongationError['reason'] ?? 'Please review the highlighted target and try again.');
            } elseif ($allTargetsUseElongationRule && $correctnessEvaluation['correctness'] === 'correct') {
                $feedback = 'Correct. Every detected Ikhfa and Izhar target met the target-local elongation rule.';
            } elseif ($referencePronunciationVerified && $correctnessEvaluation['correctness'] === 'correct') {
                $feedback = 'Correct. The reference-aware Quran pronunciation model aligned '
                    . ($targetScopeDescription ?? 'every detected Tajweed target')
                    . ' with high confidence and found no pronunciation error at any evaluated target.';
            } elseif ($referencePronunciationVerified && $correctnessEvaluation['correctness'] === 'incorrect') {
                $firstIncorrectTarget = collect($targetResults)
                    ->first(fn (array $targetResult): bool => ($targetResult['status'] ?? null) === 'incorrect');
                $incorrectRule = ucfirst((string) ($firstIncorrectTarget['rule'] ?? 'Tajweed'));
                $feedback = 'Needs practice. The reference-aware Quran pronunciation model evaluated '
                    . ($targetScopeDescription ?? 'the detected Tajweed targets')
                    . " and found an error at an {$incorrectRule} target. "
                    . ($firstIncorrectTarget['reason'] ?? 'Please review the highlighted target and try again.');
            } elseif ($hybridRuleAudioVerified && $correctnessEvaluation['correctness'] === 'correct') {
                $feedback = 'Correct. Hybrid Quran-rule and audio analysis checked '
                    . ($targetScopeDescription ?? 'the detected Tajweed target')
                    . ' and found the expected Ikhfa/Izhar pattern without a target error.';
            } elseif ($hybridRuleAudioVerified && $correctnessEvaluation['correctness'] === 'incorrect') {
                $firstIncorrectTarget = collect($targetResults)
                    ->first(fn (array $targetResult): bool => ($targetResult['status'] ?? null) === 'incorrect');
                $feedback = 'Needs practice. Hybrid Quran-rule and audio analysis checked '
                    . ($targetScopeDescription ?? 'the detected Tajweed target')
                    . ' and found an issue. '
                    . ($firstIncorrectTarget['reason'] ?? 'Please review the highlighted target and try again.');
            } else {
                $feedback = $correctnessEvaluation['reason'];
            }

            if ($evaluationFailed) {
                $detectedErrors[] = [
                    'type' => 'analysis_failed',
                    'error' => $correctnessEvaluation['reason'],
                ];
            }

            $finalClassificationStatus = $evaluationFailed
                ? 'failed'
                : ($correctnessEvaluation['correctness'] === 'uncertain'
                    ? (string) $correctnessEvaluation['classification_status']
                    : ((is_array($firstElongationError) || $allTargetsUseElongationRule)
                        ? 'elongation_verified'
                        : ($referencePronunciationVerified
                            ? 'reference_verified'
                            : ($hybridRuleAudioVerified
                                ? 'hybrid_verified'
                                : (string) $correctnessEvaluation['classification_status']))));
            $finalClassificationMethod = $evaluationFailed
                ? 'analysis_pipeline_failure'
                : ($correctnessEvaluation['correctness'] === 'uncertain'
                    ? ($silentOrNoRecitation ? 'audio_activity_gate' : 'selected_ayah_validation')
                    : ((is_array($firstElongationError) || $allTargetsUseElongationRule)
                        ? 'target_elongation_rule'
                        : ($referencePronunciationVerified
                            ? 'quran_muaalem_reference_alignment'
                            : ($hybridRuleAudioVerified
                                ? 'hybrid_quran_rule_audio'
                                : (string) $correctnessEvaluation['classification_status']))));

            $analysisResult->update([
                'processing_status' => $finalProcessingStatus,
                'feedback_message' => $feedback,
                'transcribed_text' => $transcribedText,
                'confidence_score' => ($silentOrNoRecitation || $evaluationFailed)
                    ? 0
                    : $correctnessEvaluation['confidence'],
                'correctness' => $correctnessEvaluation['correctness'],
                'predicted_rule' => $prediction,
                'classification_status' => $finalClassificationStatus,
                'classification_method' => $finalClassificationMethod,
                'class_probabilities' => is_array($result['probabilities'] ?? null)
                    ? $result['probabilities']
                    : null,
                'model_predictions' => [
                    'margin' => $result['margin'] ?? null,
                    'weights' => $result['weights'] ?? null,
                    'cnn' => $result['cnn'] ?? null,
                    'feature_model' => $result['feature_model'] ?? null,
                    'transcription' => [
                        'reference_text' => $transcribedText,
                        'reference_source' => $selectedAyahText !== ''
                            ? 'selected_ayah'
                            : ($quranMatch ? 'quran_match' : 'transcription'),
                        'speech_text' => $speechTranscriptForDisplay,
                        'speech_source' => $speechTranscriptSource,
                        'speech_note' => $speechTranscriptNote,
                        'browser_text' => $hasBrowserTranscript ? $browserTranscript : null,
                        'whisper_text' => ($speechTranscriptSource === 'whisper') ? $speechTranscript : null,
                        'selected_ayah_match' => $recitationMatchAssessment,
                        'selected_reference_quran_match' => $selectedReferenceQuranMatch,
                        'speech_quran_match' => $speechQuranMatch,
                    ],
                    'pronunciation' => $pronunciationEvidence ?: null,
                ],
                'detected_errors' => count($detectedErrors) > 0 ? $detectedErrors : null,
                'suggestions' => count($suggestions) > 0 ? $suggestions : null,
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

            return ['status' => $finalProcessingStatus];
        } catch (\Throwable $e) {
            \Log::error('Tajweed ML analysis failed', [
                'audio_id' => $audioRecitation->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            try {
                $analysisResult->update([
                    'processing_status' => 'failed',
                    'correctness' => null,
                    'predicted_rule' => null,
                    'classification_status' => 'failed',
                    'classification_method' => null,
                    'class_probabilities' => null,
                    'model_predictions' => null,
                    'confidence_score' => 0,
                    'feedback_message' => 'The analysis could not be completed. Please try again with a clear, shorter recording.',
                    'detected_errors' => [[
                        'type' => 'analysis_failed',
                        'error' => 'The machine-learning pipeline did not return a usable result.',
                    ]],
                    'suggestions' => [
                        'Check that the configured Python environment and model files are available.',
                        'Try a clear recording with less background noise.',
                    ],
                ]);
            } catch (\Throwable $updateException) {
                \Log::error('Could not persist Tajweed ML failure state', [
                    'audio_id' => $audioRecitation->id,
                    'error' => $updateException->getMessage(),
                ]);
            }

            return [
                'status' => 'failed',
                'error' => 'The machine-learning pipeline did not return a usable result.',
            ];
        } finally {
            if (file_exists($audioPath)) {
                @unlink($audioPath);
            }
        }
    }

    private function loadStoredAudio(AudioRecitation $audioRecitation): string
    {
        // A local copy is the canonical re-analysis source. Checking it before
        // Firebase prevents a slow or unavailable network from consuming the
        // entire PHP request timeout when the audio is already on disk.
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

        $downloadTimeout = max(1, (int) config('tajweed.firebase_download_timeout', 10));
        $connectTimeout = max(1, (int) config('tajweed.firebase_connect_timeout', 3));

        if ($this->storage && strpos((string) $audioRecitation->audio_file_path, 'users/') === 0) {
            try {
                $bucket = $this->storage->getBucket();
                $object = $bucket->object($audioRecitation->audio_file_path);
                $requestOptions = [
                    'requestTimeout' => $downloadTimeout,
                    'retries' => 0,
                    'restOptions' => [
                        'connect_timeout' => $connectTimeout,
                        'timeout' => $downloadTimeout,
                    ],
                ];

                if ($object->exists($requestOptions)) {
                    return $object->downloadAsString($requestOptions);
                }
            } catch (\Throwable $e) {
                \Log::warning('Firebase audio download failed during re-analysis; trying local fallback', [
                    'audio_id' => $audioRecitation->id,
                    'path' => $audioRecitation->audio_file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $firebaseUrl = trim((string) $audioRecitation->firebase_url);

        if ($firebaseUrl !== '') {
            $urlPath = rawurldecode((string) parse_url($firebaseUrl, PHP_URL_PATH));
            $isLocalStorageUrl = str_starts_with($urlPath, '/storage/');

            if ($isLocalStorageUrl) {
                $urlLocalPath = str_replace('\\', '/', ltrim(substr($urlPath, strlen('/storage/')), '/'));

                if ($urlLocalPath !== '' && !str_contains($urlLocalPath, '..') && Storage::disk('public')->exists($urlLocalPath)) {
                    return Storage::disk('public')->get($urlLocalPath);
                }

                // Laravel's development server is single-threaded. Calling its
                // own /storage URL from this request deadlocks until PHP dies.
                Log::warning('Local storage URL could not be resolved during re-analysis', [
                    'audio_id' => $audioRecitation->id,
                    'path' => $urlLocalPath,
                ]);
            }

            try {
                $response = $isLocalStorageUrl
                    ? null
                    : Http::connectTimeout($connectTimeout)
                        ->timeout($downloadTimeout)
                        ->get($firebaseUrl);

                if ($response && $response->successful() && $response->body() !== '') {
                    \Log::info('Loaded Firebase audio through signed URL fallback for re-analysis', [
                        'audio_id' => $audioRecitation->id,
                    ]);

                    return $response->body();
                }

                if ($response) {
                    \Log::warning('Firebase signed URL fallback failed during re-analysis', [
                        'audio_id' => $audioRecitation->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Firebase signed URL fallback errored during re-analysis', [
                    'audio_id' => $audioRecitation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (strpos((string) $audioRecitation->audio_file_path, 'users/') === 0) {
            throw new \RuntimeException('Stored audio is only available in Firebase right now, and Firebase/Google OAuth could not be reached. Please record this attempt again so the app can keep a local re-analysis copy.');
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

    private function shouldRetryQuranPronunciationAnalysis(?array $payload, string $output): bool
    {
        $message = strtolower((string) ($payload['error'] ?? $payload['reason'] ?? $output));

        return str_contains($message, 'autofeatureextractor')
            || str_contains($message, 'requirements defined correctly');
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

    private function detectTajweedTargets(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $targets = [];
        // ===== REPORT SCREENSHOT START: Section 4.3.10A - Supported Tajweed Target Letters =====
        $ikhfaLetters = array_flip([
            "\u{062A}", "\u{062B}", "\u{062C}", "\u{062F}", "\u{0630}",
            "\u{0632}", "\u{0633}", "\u{0634}", "\u{0635}", "\u{0636}",
            "\u{0637}", "\u{0638}", "\u{0641}", "\u{0642}", "\u{0643}",
        ]);
        $izharLetters = array_flip([
            "\u{0621}", "\u{0627}", "\u{0647}", "\u{0639}", "\u{062D}", "\u{063A}", "\u{062E}",
        ]);
        $count = count($chars);
        $totalLetters = count(array_filter($chars, fn(string $item): bool => $this->isArabicLetterForTajweed($item)));
        // ===== REPORT SCREENSHOT END: Section 4.3.10A - Supported Tajweed Target Letters =====

        foreach ($chars as $index => $char) {
            $isTanween = (bool) preg_match('/[\x{064B}\x{064C}\x{064D}]/u', $char);
            $isNoon = $this->normalizeArabicLetterForTajweed($char) === "\u{0646}";
            $hasSukun = false;
            $hasNoonVowel = false;
            $markEnd = $index;

            if ($isNoon) {
                for ($i = $index + 1; $i < $count && $this->isArabicMarkForTajweed($chars[$i]); $i++) {
                    $markEnd = $i;
                    $hasSukun = $hasSukun || in_array($chars[$i], ["\u{0652}", "\u{06E1}"], true);
                    $hasNoonVowel = $hasNoonVowel || (bool) preg_match('/[\x{064B}-\x{0650}\x{0654}\x{0655}]/u', $chars[$i]);
                }
            }

            if (!$isTanween && !$isNoon) {
                continue;
            }

            // ===== REPORT SCREENSHOT START: Section 4.3.10B - Tajweed Target Rule Detection =====
            $nextIndex = $this->findNextArabicLetterIndex($chars, $markEnd + 1);

            if ($nextIndex === null) {
                continue;
            }

            // Fathatan is commonly written with a silent carrier alif (for
            // example, the alif in "حَقًّا").  That alif is orthography, not
            // the following spoken Tajweed letter.  Scan past it to the first
            // letter of the next word before deciding the rule.
            if ($isTanween
                && $char === "\u{064B}"
                && $this->isSameWordFathatanCarrier($chars, $markEnd, $nextIndex)) {
                $nextIndex = $this->findNextArabicLetterIndex($chars, $nextIndex + 1);

                if ($nextIndex === null) {
                    continue;
                }
            }

            $nextLetter = $this->normalizeArabicLetterForTajweed($chars[$nextIndex]);
            $rule = null;

            if (isset($ikhfaLetters[$nextLetter])) {
                $rule = 'ikhfa';
            } elseif (isset($izharLetters[$nextLetter])) {
                $rule = 'izhar';
            }

            if ($rule === null) {
                continue;
            }
            // ===== REPORT SCREENSHOT END: Section 4.3.10B - Tajweed Target Rule Detection =====

            $hasImplicitNoonSakinah = $isNoon && !$hasSukun && !$hasNoonVowel;

            if (!$isTanween && !$hasSukun && !$hasImplicitNoonSakinah) {
                continue;
            }

            // A tanween mark belongs to its preceding base letter.  Noon
            // sakinah is itself the target and must not absorb the preceding
            // vowel/letter into the pronunciation decision span.
            $start = $isTanween
                ? ($this->findPreviousArabicLetterIndex($chars, $index - 1) ?? $index)
                : $index;
            $snippetStart = max(0, $start - 2);
            $snippetEnd = min($count - 1, $nextIndex + 2);

            $letterPosition = $this->countArabicLettersBefore($chars, $start);
            $positionRatio = min(1.0, max(0.0, ($letterPosition + 0.5) / max(1, $totalLetters)));

            $targets[] = [
                'rule' => $rule,
                'expected_rule' => $rule,
                'source' => $isTanween ? 'tanwin' : 'noon_sakinah',
                'trigger' => $isTanween ? 'tanwin + ' . $chars[$nextIndex] : "\u{0646}\u{0652}" . ' + ' . $chars[$nextIndex],
                'next_letter' => $chars[$nextIndex],
                'snippet' => implode('', array_slice($chars, $snippetStart, $snippetEnd - $snippetStart + 1)),
                'position' => $start,
                'end_position' => $nextIndex,
                'letter_position' => $letterPosition,
                'total_letters' => $totalLetters,
                'position_ratio' => $positionRatio,
            ];
        }

        return $targets;
    }

    private function detectAudioInputIssue(array $quality): ?array
    {
        $status = (string) data_get($quality, 'audio_activity_status', '');
        $isSilent = (bool) data_get($quality, 'is_silent', false);
        $isTooQuiet = (bool) data_get($quality, 'is_too_quiet', false);
        $isTooShort = (bool) data_get($quality, 'is_too_short', false);
        $rawRms = (float) data_get($quality, 'raw_rms', 0);
        $rawPeak = (float) data_get($quality, 'raw_peak_amplitude', 0);
        $activeRatio = (float) data_get($quality, 'raw_active_frame_ratio', 1);
        $durationMs = (float) data_get($quality, 'raw_duration_ms', data_get($quality, 'duration_ms', 0));
        $minimumDurationMs = (float) data_get($quality, 'minimum_duration_ms', 750);

        // Backward-compatible fallback if the Python file has not been updated yet.
        if ($status === '' && $rawRms <= 0 && $rawPeak <= 0 && $activeRatio >= 1) {
            return null;
        }

        if ($isTooShort || $status === 'too_short') {
            return [
                'type' => 'audio_too_short',
                'message' => sprintf(
                    'The recording is too short for a reliable Tajweed analysis (%.2f seconds recorded; at least %.2f seconds required). Please record the complete recitation.',
                    $durationMs / 1000,
                    $minimumDurationMs / 1000
                ),
                'duration_ms' => round($durationMs, 2),
                'minimum_duration_ms' => round($minimumDurationMs, 2),
                'raw_active_frame_ratio' => round($activeRatio, 4),
            ];
        }

        if ($isSilent || $status === 'silent') {
            return [
                'type' => 'silent_audio',
                'message' => 'The recording is silent or has too little voice signal, so Tajweed correctness cannot be judged. Please record the Quran recitation again clearly.',
                'raw_rms' => round($rawRms, 6),
                'raw_peak_amplitude' => round($rawPeak, 6),
                'raw_active_frame_ratio' => round($activeRatio, 4),
            ];
        }

        if ($isTooQuiet || $status === 'too_quiet') {
            return [
                'type' => 'unclear_audio',
                'message' => 'The recording is too quiet or unclear, so Tajweed correctness cannot be judged reliably. Please record closer to the microphone with less background noise.',
                'raw_rms' => round($rawRms, 6),
                'raw_peak_amplitude' => round($rawPeak, 6),
                'raw_active_frame_ratio' => round($activeRatio, 4),
            ];
        }

        return null;
    }

    private function isSilentOrNoRecitation(array $quality, ?array $audioInputIssue = null): bool
    {
        $activityStatus = strtolower((string) data_get($quality, 'audio_activity_status', ''));

        return (bool) data_get($quality, 'is_silent', false)
            || in_array($activityStatus, ['silent', 'no_speech', 'no_recitation'], true)
            || ($audioInputIssue['type'] ?? null) === 'silent_audio';
    }

    /**
     * Compare actual browser/Whisper speech with the selected Quran reference.
     * Return mismatch only when the evidence is decisive; ambiguous ASR remains
     * unknown and is never mislabeled as a different ayah.
     */
    private function assessSelectedAyahMatch(
        ?string $selectedAyah,
        ?string $speechTranscript,
        ?array $speechQuranMatch = null,
        ?int $sourceSurah = null,
        ?int $sourceAyah = null
    ): array {
        $selected = $this->normalizeArabicForAyahMatch((string) $selectedAyah);
        $speech = $this->normalizeArabicForAyahMatch((string) $speechTranscript);
        $minimumLetters = max(4, (int) config('tajweed.min_selected_ayah_match_letters', 8));

        if (mb_strlen(str_replace(' ', '', $selected)) < $minimumLetters
            || mb_strlen(str_replace(' ', '', $speech)) < $minimumLetters) {
            return [
                'status' => 'unknown',
                'method' => 'insufficient_actual_speech',
                'score' => null,
            ];
        }

        $score = $this->selectedAyahSimilarity($selected, $speech);
        $matchThreshold = (float) config('tajweed.selected_ayah_match_threshold', 62);
        $nearExactThreshold = (float) config('tajweed.selected_ayah_near_exact_match_threshold', 90);

        // An exact/near-exact textual match wins even when a repeated Quran
        // phrase makes the corpus matcher choose another coordinate.
        if ($score >= $nearExactThreshold) {
            return [
                'status' => 'match',
                'method' => 'direct_transcript_similarity',
                'score' => $score,
            ];
        }

        if ($speechQuranMatch !== null && $sourceSurah !== null && $sourceAyah !== null) {
            $matchedSurah = (int) ($speechQuranMatch['surah'] ?? 0);
            $matchedAyah = (int) ($speechQuranMatch['ayah'] ?? 0);

            if ($matchedSurah === $sourceSurah && $matchedAyah === $sourceAyah) {
                return [
                    'status' => 'match',
                    'method' => 'quran_coordinate_match',
                    'score' => (float) ($speechQuranMatch['score'] ?? $score),
                    'matched_surah' => $matchedSurah,
                    'matched_ayah' => $matchedAyah,
                ];
            }

            $quranMatchScore = (float) ($speechQuranMatch['score'] ?? 0);
            $quranMatchMargin = (float) ($speechQuranMatch['margin'] ?? 0);
            if ($matchedSurah > 0
                && $matchedAyah > 0
                && $quranMatchScore >= (float) config('tajweed.selected_ayah_coordinate_mismatch_min_score', 85)
                && $quranMatchMargin >= (float) config('tajweed.selected_ayah_coordinate_mismatch_min_margin', 5)) {
                return [
                    'status' => 'mismatch',
                    'method' => 'quran_coordinate_mismatch',
                    'score' => $quranMatchScore,
                    'margin' => $quranMatchMargin,
                    'expected_surah' => $sourceSurah,
                    'expected_ayah' => $sourceAyah,
                    'matched_surah' => $matchedSurah,
                    'matched_ayah' => $matchedAyah,
                ];
            }
        }

        // A merely similar transcript can verify the selected words only after
        // any decisive Quran-coordinate conflict has been considered.
        if ($score >= $matchThreshold) {
            return [
                'status' => 'match',
                'method' => 'direct_transcript_similarity',
                'score' => $score,
            ];
        }

        return [
            'status' => 'unknown',
            'method' => 'ambiguous_transcript_similarity',
            'score' => $score,
        ];
    }

    private function resolveSelectedAyahCoordinates(
        string $selectedAyah,
        ?int $sourceSurah,
        ?int $sourceAyah
    ): array {
        if (($sourceSurah !== null && $sourceAyah !== null)
            || trim($selectedAyah) === ''
            || ! config('tajweed.enable_quran_matching', true)) {
            return [$sourceSurah, $sourceAyah, null];
        }

        $match = $this->quranTranscriptionMatcher->match($selectedAyah);

        if ($match === null) {
            return [$sourceSurah, $sourceAyah, null];
        }

        $resolvedSurah = (int) ($match['surah'] ?? 0);
        $resolvedAyah = (int) ($match['ayah'] ?? 0);

        return [
            $resolvedSurah > 0 ? $resolvedSurah : $sourceSurah,
            $resolvedAyah > 0 ? $resolvedAyah : $sourceAyah,
            $match,
        ];
    }

    private function hasSelectedAyahMismatch(
        bool $referenceContentAuthoritative,
        bool $referenceContentMismatch,
        array $transcriptAssessment
    ): bool {
        if ($referenceContentMismatch) {
            return true;
        }

        if ($referenceContentAuthoritative) {
            return false;
        }

        return ($transcriptAssessment['status'] ?? 'unknown') === 'mismatch';
    }

    private function hasUsableReferenceContentEvidence(array $evidence): bool
    {
        $globalPer = $evidence['global_per'] ?? null;

        return (string) ($evidence['model_status'] ?? '') === 'loaded'
            && array_key_exists('content_mismatch', $evidence)
            && is_numeric($globalPer)
            && is_finite((float) $globalPer)
            && (float) $globalPer >= 0.0;
    }

    private function isRecitationContentVerified(
        bool $referencePronunciationVerified,
        bool $referenceContentAuthoritative,
        bool $referenceContentMismatch,
        array $transcriptAssessment
    ): bool {
        return $referencePronunciationVerified
            || ($referenceContentAuthoritative && ! $referenceContentMismatch)
            || ($transcriptAssessment['status'] ?? 'unknown') === 'match';
    }

    private function selectedAyahSimilarity(string $selected, string $speech): float
    {
        similar_text($selected, $speech, $characterSimilarity);

        $selectedWords = collect(explode(' ', $selected))->filter()->values();
        $speechWords = collect(explode(' ', $speech))->filter()->values();
        $speechWordCoverage = $speechWords->isNotEmpty()
            ? $speechWords->intersect($selectedWords)->count() / $speechWords->count()
            : 0.0;
        $selectedLetters = max(1, mb_strlen(str_replace(' ', '', $selected)));
        $speechLetters = max(1, mb_strlen(str_replace(' ', '', $speech)));
        $lengthRatio = min($selectedLetters, $speechLetters) / max($selectedLetters, $speechLetters);
        $score = ($characterSimilarity * 0.65)
            + ($speechWordCoverage * 100 * 0.25)
            + ($lengthRatio * 100 * 0.10);

        if ($lengthRatio >= 0.45
            && (str_contains($selected, $speech) || str_contains($speech, $selected))) {
            $score = max($score, 90.0);
        }

        return round(min(100.0, max(0.0, $score)), 2);
    }

    private function normalizeArabicForAyahMatch(string $text): string
    {
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{08D3}-\x{08FF}]/u', '', $text) ?? $text;
        $text = str_replace("\u{0640}", '', $text);
        $text = strtr($text, [
            "\u{0623}" => "\u{0627}",
            "\u{0625}" => "\u{0627}",
            "\u{0622}" => "\u{0627}",
            "\u{0671}" => "\u{0627}",
            "\u{0624}" => "\u{0621}",
            "\u{0626}" => "\u{0621}",
            "\u{0649}" => "\u{064A}",
            "\u{0629}" => "\u{0647}",
        ]);
        $text = preg_replace('/[^\x{0621}-\x{064A}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function pronunciationAnalysisFailed(array $evidence, bool $silentOrNoRecitation): bool
    {
        if ($evidence === []) {
            return false;
        }

        $status = strtolower((string) ($evidence['status'] ?? ''));
        $modelStatus = strtolower((string) ($evidence['model_status'] ?? ''));
        $explanationError = $evidence['error_explanation_error'] ?? null;

        if ((is_string($explanationError) && trim($explanationError) !== '')
            || (! is_string($explanationError) && ! empty($explanationError))) {
            return true;
        }

        if ($modelStatus === 'not_run_unusable_audio') {
            return ! $silentOrNoRecitation;
        }

        if (in_array($modelStatus, [
            'failed',
            'error',
            'unavailable',
            'timeout',
            'script_missing',
            'not_run_no_targets',
        ], true)) {
            return true;
        }

        return in_array($status, ['failed', 'error', 'timeout', 'script_missing'], true);
    }

    private function shouldFailAnalysisPipeline(
        array $pronunciationEvidence,
        bool $silentOrNoRecitation,
        ?array $audioInputIssue,
        bool $referencePronunciationVerified,
        bool $hasCompleteTrustedTargetEvidence
    ): bool {
        $unrecoveredReferenceFailure = $this->pronunciationAnalysisFailed(
            $pronunciationEvidence,
            $silentOrNoRecitation
        ) && ! $hasCompleteTrustedTargetEvidence;
        $unusableNonSilentCapture = $audioInputIssue !== null
            && ! $silentOrNoRecitation
            && ! $referencePronunciationVerified
            && ! $hasCompleteTrustedTargetEvidence;

        return $unrecoveredReferenceFailure || $unusableNonSilentCapture;
    }

    private function transcriptLooksNonArabicSpeech(?string $text): bool
    {
        $text = trim((string) $text);

        if ($text === '' || $text === 'Unable to transcribe audio' || $text === 'Transcription skipped.') {
            return false;
        }

        $arabicLetters = $this->countArabicLetters($text);
        $hasLatinOrDigit = (bool) preg_match('/[A-Za-z0-9]/u', $text);
        $hasArabicScript = (bool) preg_match('/\p{Arabic}/u', $text);

        return $arabicLetters < 3 && $hasLatinOrDigit && !$hasArabicScript;
    }

    private function summarizeTajweedTargets(array $targets): array
    {
        return array_map(
            fn(array $target): string => ($target['trigger'] ?? 'target') . ' in "' . ($target['snippet'] ?? '') . '"',
            $targets
        );
    }

    private function analyzeTajweedTargetResults(
        array $targets,
        array $quality,
        int $ikhfaMinGhunnahMs,
        int $ikhfaMinLocalGhunnahMs,
        int $izharMaxGhunnahMs
    ): array
    {
        $audioDurationMs = max(1.0, (float) data_get($quality, 'duration_ms', 0));
        $globalGhunnahMs = (float) data_get($quality, 'ghunnah_duration_ms', 0);
        $segments = array_values(array_filter(
            (array) data_get($quality, 'ghunnah_segments', []),
            fn($segment): bool => is_array($segment)
        ));
        $matchWindowMs = (int) config('tajweed.target_match_window_ms', 900);

        return array_map(function (array $target) use ($audioDurationMs, $globalGhunnahMs, $segments, $matchWindowMs, $ikhfaMinGhunnahMs, $ikhfaMinLocalGhunnahMs, $izharMaxGhunnahMs): array {
            $ratio = $this->targetPositionRatio($target);
            $expectedMs = $ratio * $audioDurationMs;
            $nearbyGhunnahMs = $this->longestGhunnahNearTime($segments, $expectedMs, $matchWindowMs);
            $rule = $target['rule'] ?? 'unknown';
            $status = 'unknown';
            $reason = 'Could not evaluate this target.';

            if ($rule === 'ikhfa') {
                $hasLocalGhunnah = $nearbyGhunnahMs >= $ikhfaMinLocalGhunnahMs;
                $hasGlobalGhunnah = $globalGhunnahMs >= $ikhfaMinGhunnahMs;
                $status = ($hasLocalGhunnah || $hasGlobalGhunnah) ? 'correct' : 'incorrect';
                $reason = $hasLocalGhunnah
                    ? 'Ghunnah detected near this Ikhfa target.'
                    : ($hasGlobalGhunnah
                        ? 'Ghunnah detected in the recording; local timing is approximate for this Ikhfa target.'
                        : 'No ghunnah was detected for this Ikhfa target.');
            } elseif ($rule === 'izhar') {
                $hasLocalNasalHold = $nearbyGhunnahMs >= $izharMaxGhunnahMs;
                $status = $hasLocalNasalHold ? 'incorrect' : 'correct';
                $reason = !$hasLocalNasalHold
                    ? 'Pronunciation looks clear near this Izhar target.'
                    : 'Nasal sound was detected near this Izhar target; Izhar should be clear.';
            }

            return array_merge($target, [
                'status' => $status,
                'reason' => $reason,
                'expected_time_ms' => round($expectedMs, 2),
                'nearby_ghunnah_ms' => round($nearbyGhunnahMs, 2),
                'global_ghunnah_ms' => round($globalGhunnahMs, 2),
            ]);
        }, $targets);
    }

    private function enforcePresenceBasedGhunnahRules(
        array $targetResults,
        array $quality,
        string $selectedRule,
        int $ikhfaMinGhunnahMs,
        int $ikhfaMinLocalGhunnahMs,
        int $izharMaxGhunnahMs
    ): array {
        if (count($targetResults) === 0) {
            return $targetResults;
        }

        $globalGhunnahMs = (float) data_get($quality, 'ghunnah_duration_ms', 0);
        $globalGhunnahRatio = (float) data_get($quality, 'ghunnah_frame_ratio', 0);
        $globalGhunnahStrength = (float) data_get($quality, 'ghunnah_strength', 0);

        return array_map(function (array $targetResult) use ($ikhfaMinGhunnahMs, $ikhfaMinLocalGhunnahMs, $izharMaxGhunnahMs, $globalGhunnahMs, $globalGhunnahRatio, $globalGhunnahStrength): array {
            // The Python target-window layer already reconciles its trained model
            // with acoustic evidence. Preserve that result and its provenance;
            // these Laravel-only rules are a diagnostic fallback for when no
            // target model is available.
            if (($targetResult['target_window_model_status'] ?? null) === 'loaded') {
                return $targetResult;
            }

            $rule = $targetResult['rule'] ?? null;
            $targetWindowQuality = (array) ($targetResult['target_window_quality'] ?? []);
            $nearbyGhunnahMs = (float) ($targetResult['nearby_ghunnah_ms'] ?? 0);
            $windowGhunnahMs = (float) data_get($targetWindowQuality, 'ghunnah_duration_ms', 0);
            $windowGhunnahRatio = (float) data_get($targetWindowQuality, 'ghunnah_frame_ratio', 0);
            $windowGhunnahStrength = (float) data_get($targetWindowQuality, 'ghunnah_strength', 0);
            $windowNasalScore = (float) data_get($targetWindowQuality, 'nasal_excess_score', 0);
            $windowTransitionSmoothness = (float) data_get($targetWindowQuality, 'transition_smoothness', 0);
            $windowRmsStability = (float) data_get($targetWindowQuality, 'rms_stability', 0);
            $smoothNasalEvidence = $windowNasalScore >= 0.12
                && $windowTransitionSmoothness >= 0.35
                && $windowRmsStability >= 0.25;

            $localHasGhunnah = $nearbyGhunnahMs >= $ikhfaMinLocalGhunnahMs
                || $windowGhunnahMs >= $ikhfaMinLocalGhunnahMs
                || $windowGhunnahRatio >= 0.018
                || $windowGhunnahStrength >= 0.16
                || $windowNasalScore >= 0.12
                || $smoothNasalEvidence;
            $globalHasGhunnah = $globalGhunnahMs >= $ikhfaMinGhunnahMs
                || $globalGhunnahRatio >= 0.018
                || $globalGhunnahStrength >= 0.16;

            if ($rule === 'ikhfa') {
                if ($localHasGhunnah || $globalHasGhunnah) {
                    return array_merge($targetResult, [
                        'status' => 'correct',
                        'reason' => 'Ghunnah/nasal sound was detected for this Ikhfa target, even if it was short.',
                        'presence_based_decision' => [
                            'local_has_ghunnah' => $localHasGhunnah,
                            'global_has_ghunnah' => $globalHasGhunnah,
                        ],
                    ]);
                }

                return array_merge($targetResult, [
                    'status' => 'incorrect',
                    'reason' => 'No ghunnah/nasal sound was detected for this Ikhfa target.',
                    'presence_based_decision' => [
                        'local_has_ghunnah' => false,
                        'global_has_ghunnah' => false,
                    ],
                ]);
            }

            if ($rule === 'izhar') {
                $localHasNasal = $nearbyGhunnahMs >= $izharMaxGhunnahMs
                    || $windowGhunnahMs >= $izharMaxGhunnahMs
                    || $windowGhunnahRatio >= 0.04
                    || $windowGhunnahStrength >= 0.22
                    || $windowNasalScore >= 0.22
                    || ($windowNasalScore >= 0.16 && $windowTransitionSmoothness >= 0.40);

                if ($localHasNasal) {
                    return array_merge($targetResult, [
                        'status' => 'incorrect',
                        'reason' => 'Ghunnah/nasal sound was detected for this Izhar target. Izhar should be clear without nasal hold.',
                        'presence_based_decision' => [
                            'local_has_nasal' => true,
                        ],
                    ]);
                }

                return array_merge($targetResult, [
                    'status' => 'correct',
                    'reason' => 'No ghunnah/nasal hold was detected for this Izhar target; pronunciation looks clear.',
                    'presence_based_decision' => [
                        'local_has_nasal' => false,
                    ],
                ]);
            }

            return $targetResult;
        }, $targetResults);
    }

    /**
     * Final safety layer for Izhar.
     *
     * Keep local Izhar nasal diagnostics attached to untrusted fallback results.
     * Recording-wide nasal energy is not target-specific: another natural nun or
     * mim elsewhere in the ayah must never veto an individual Izhar target.
     */
    private function enforceIzharNasalSafety(array $targetResults): array
    {
        if (count($targetResults) === 0) {
            return $targetResults;
        }

        return array_map(function (array $targetResult): array {
            if (($targetResult['target_window_model_status'] ?? null) === 'loaded') {
                return $targetResult;
            }

            if (($targetResult['rule'] ?? null) !== 'izhar') {
                return $targetResult;
            }

            $targetWindowQuality = (array) ($targetResult['target_window_quality'] ?? []);
            $windowGhunnahMs = (float) data_get($targetWindowQuality, 'ghunnah_duration_ms', $targetResult['nearby_ghunnah_ms'] ?? 0);
            $windowGhunnahRatio = (float) data_get($targetWindowQuality, 'ghunnah_frame_ratio', 0);
            $windowGhunnahStrength = (float) data_get($targetWindowQuality, 'ghunnah_strength', 0);
            $windowNasalScore = (float) data_get($targetWindowQuality, 'nasal_excess_score', 0);
            $windowTransitionSmoothness = (float) data_get($targetWindowQuality, 'transition_smoothness', 0);

            $windowHasNasal = $windowNasalScore >= 0.22
                || $windowGhunnahRatio >= 0.04
                || $windowGhunnahStrength >= 0.22
                || ($windowNasalScore >= 0.16 && $windowTransitionSmoothness >= 0.40)
                || $windowGhunnahMs >= (float) config('tajweed.izhar_max_ghunnah_ms', 30);

            if (!$windowHasNasal) {
                return $targetResult;
            }

            return array_merge($targetResult, [
                'status' => 'incorrect',
                'reason' => 'Nasal/ghunnah sound was detected for this Izhar target. Izhar should be pronounced clearly without holding a nasal sound.',
                'target_window_decision_source' => ($targetResult['target_window_decision_source'] ?? 'unknown') . '+laravel_izhar_nasal_safety',
                'izhar_nasal_safety' => [
                    'window_ghunnah_ms' => round($windowGhunnahMs, 2),
                    'window_ghunnah_ratio' => round($windowGhunnahRatio, 4),
                    'window_ghunnah_strength' => round($windowGhunnahStrength, 4),
                    'window_nasal_score' => round($windowNasalScore, 4),
                    'window_transition_smoothness' => round($windowTransitionSmoothness, 4),
                ],
            ]);
        }, $targetResults);
    }

    private function applyHybridRuleAudioFallback(array $targetResults, array $quality): array
    {
        if (! config('tajweed.enable_hybrid_rule_audio_fallback', true) || count($targetResults) === 0) {
            return $targetResults;
        }

        if ($this->detectAudioInputIssue($quality) !== null) {
            return $targetResults;
        }

        $minimumConfidence = (float) config('tajweed.hybrid_rule_audio_min_confidence', 68);

        return array_map(function (array $targetResult) use ($quality, $minimumConfidence): array {
            if ($this->tajweedCorrectnessService->targetHasAnalysisFailure($targetResult)) {
                return $targetResult;
            }

            if ($this->hasTrustedTargetDecision($targetResult)) {
                return $targetResult;
            }

            $rule = (string) ($targetResult['rule'] ?? '');

            // Izhar is resolved later by the conservative binary policy. This
            // promotion remains limited to the explicitly calibrated Ikhfa path.
            if (! $this->supportsHybridRuleAudioVerdict($rule)) {
                return $targetResult;
            }

            if ((bool) data_get($targetResult, 'target_window_quality.content_mismatch', false)) {
                return $targetResult;
            }

            $heuristicStatus = (string) ($targetResult['heuristic_status'] ?? '');

            if (! in_array($heuristicStatus, ['correct', 'incorrect'], true)) {
                $currentStatus = (string) ($targetResult['status'] ?? '');
                $currentSource = (string) ($targetResult['target_window_decision_source'] ?? '');

                if (in_array($currentStatus, ['correct', 'incorrect'], true)
                    && ! in_array($currentSource, ['trusted_ml', 'ml_and_heuristic_agree', 'strong_ml_with_borderline_heuristic'], true)) {
                    $heuristicStatus = $currentStatus;
                }
            }

            if (! in_array($heuristicStatus, ['correct', 'incorrect'], true)) {
                return $targetResult;
            }

            $confidence = $this->hybridRuleAudioConfidence($targetResult, $quality, $heuristicStatus);

            if ($confidence < $minimumConfidence) {
                return $targetResult;
            }

            $source = (string) ($targetResult['source'] ?? 'target');
            $trigger = (string) ($targetResult['trigger'] ?? '');
            $heuristicReason = (string) ($targetResult['heuristic_reason'] ?? $targetResult['reason'] ?? '');
            $hybridReason = ucfirst($rule)
                . " is expected by the Quran text"
                . ($trigger !== '' ? " ({$trigger})" : '')
                . ". The audio rule check "
                . ($heuristicStatus === 'correct' ? 'supports this target.' : 'does not support this target.')
                . ($heuristicReason !== '' ? " {$heuristicReason}" : '');

            return array_merge($targetResult, [
                'status' => $heuristicStatus,
                'reason' => $hybridReason,
                'target_window_model_status' => 'hybrid_rule_audio',
                'target_window_label' => "hybrid_{$rule}_{$heuristicStatus}",
                'target_window_confidence' => round($confidence, 2),
                'target_window_decision_source' => 'hybrid_rule_audio',
                'hybrid_evidence' => [
                    'text_rule' => $rule,
                    'target_source' => $source,
                    'trigger' => $trigger,
                    'audio_heuristic_status' => $heuristicStatus,
                    'audio_heuristic_reason' => $heuristicReason,
                    'minimum_confidence' => $minimumConfidence,
                ],
            ]);
        }, $targetResults);
    }

    private function hybridRuleAudioConfidence(array $targetResult, array $quality, string $status): float
    {
        $rule = (string) ($targetResult['rule'] ?? '');
        $targetWindowQuality = (array) (
            $targetResult['heuristic_target_window_quality']
            ?? $targetResult['target_window_quality']
            ?? []
        );

        $nearbyGhunnahMs = (float) ($targetResult['nearby_ghunnah_ms'] ?? 0);
        $globalGhunnahMs = (float) data_get($quality, 'ghunnah_duration_ms', $targetResult['global_ghunnah_ms'] ?? 0);
        $windowGhunnahMs = (float) data_get($targetWindowQuality, 'ghunnah_duration_ms', 0);
        $windowGhunnahRatio = (float) data_get($targetWindowQuality, 'ghunnah_frame_ratio', 0);
        $windowGhunnahStrength = (float) data_get($targetWindowQuality, 'ghunnah_strength', 0);
        $windowNasalScore = (float) data_get($targetWindowQuality, 'nasal_excess_score', 0);

        if ($rule === 'ikhfa') {
            $ikhfaMinGhunnahMs = max(1.0, (float) config('tajweed.ikhfa_min_ghunnah_ms', 80));
            $ikhfaMinLocalGhunnahMs = max(1.0, (float) config('tajweed.ikhfa_min_local_ghunnah_ms', 50));
            $evidence = max(
                $nearbyGhunnahMs / $ikhfaMinLocalGhunnahMs,
                $windowGhunnahMs / $ikhfaMinLocalGhunnahMs,
                $globalGhunnahMs / $ikhfaMinGhunnahMs,
                $windowGhunnahRatio / 0.018,
                $windowGhunnahStrength / 0.16,
                $windowNasalScore / 0.12
            );

            return $status === 'correct'
                ? min(88.0, 70.0 + min(18.0, $evidence * 6.0))
                : min(84.0, 70.0 + min(14.0, max(0.0, 1.0 - $evidence) * 14.0));
        }

        return 0.0;
    }

    private function supportsHybridRuleAudioVerdict(string $rule): bool
    {
        $allowedRules = (array) config('tajweed.hybrid_rule_audio_fallback_rules', ['ikhfa']);

        // Izhar requires calibrated target-local evidence. Keep this explicit so
        // an overly broad configuration cannot accidentally re-enable heuristic
        // Izhar verdicts.
        return $rule !== 'izhar' && in_array($rule, $allowedRules, true);
    }

    private function hasTrustedTargetDecision(array $targetResult): bool
    {
        return $this->tajweedCorrectnessService->hasReliableTargetEvidence($targetResult);
    }

    /**
     * Resolve each usable matching target to the same binary policy as the
     * overall result. Runtime failures remain explicit; a corroborated local
     * heuristic may prove an error; every other untrusted/borderline target is a
     * conservative pass because no specific error was established.
     */
    private function neutralizeUntrustedTargetDecisions(array $targetResults): array
    {
        return array_map(function (array $targetResult): array {
            if ($this->tajweedCorrectnessService->targetHasAnalysisFailure($targetResult)) {
                return array_merge($targetResult, [
                    'heuristic_status' => $targetResult['heuristic_status'] ?? $targetResult['status'] ?? null,
                    'heuristic_reason' => $targetResult['heuristic_reason'] ?? $targetResult['reason'] ?? null,
                    'status' => 'analysis_failed',
                    'reason' => 'Target pronunciation analysis failed. No target verdict was assigned.',
                ]);
            }

            if ($this->hasTrustedTargetDecision($targetResult)) {
                return $targetResult;
            }

            $rawStatus = $targetResult['status'] ?? null;
            $rawReason = $targetResult['reason'] ?? null;
            $rawDecisionSource = $targetResult['target_window_decision_source'] ?? null;
            $rawConfidence = $targetResult['target_window_confidence'] ?? null;

            if ($this->tajweedCorrectnessService->hasStrongTargetError($targetResult)) {
                return array_merge($targetResult, [
                    'heuristic_status' => $targetResult['heuristic_status'] ?? $rawStatus,
                    'heuristic_reason' => $targetResult['heuristic_reason'] ?? $rawReason,
                    'raw_target_status' => $rawStatus,
                    'raw_target_reason' => $rawReason,
                    'raw_target_decision_source' => $rawDecisionSource,
                    'raw_target_confidence' => $rawConfidence,
                    'status' => 'incorrect',
                    'reason' => $targetResult['heuristic_reason']
                        ?? 'A corroborated target-local acoustic check found a specific Tajweed error.',
                    'target_window_confidence' => null,
                    'target_window_decision_source' => 'strong_target_error_fallback',
                ]);
            }

            return array_merge($targetResult, [
                'heuristic_status' => $targetResult['heuristic_status'] ?? $rawStatus,
                'heuristic_reason' => $targetResult['heuristic_reason'] ?? $rawReason,
                'raw_target_status' => $rawStatus,
                'raw_target_reason' => $rawReason,
                'raw_target_decision_source' => $rawDecisionSource,
                'raw_target_confidence' => $rawConfidence,
                'status' => 'correct',
                'reason' => 'No specific, strong Tajweed error was detected at this target; it receives a conservative pass.',
                'target_window_confidence' => null,
                'target_window_decision_source' => 'conservative_no_error_fallback',
            ]);
        }, $targetResults);
    }

    /**
     * Make the elongation rule authoritative after every optional model and
     * fallback has run. Target-aligned Quran phonemes take priority; the local
     * acoustic duration is used only when those phonemes are unavailable.
     * Input/analysis failures remain untouched.
     */
    private function applyElongationDurationRules(array $targetResults): array
    {
        return array_map(function (array $targetResult): array {
            if ($this->tajweedCorrectnessService->targetHasAnalysisFailure($targetResult)) {
                return $targetResult;
            }

            $decision = $this->tajweedCorrectnessService->targetElongationDecision($targetResult);

            if ($decision === null) {
                return $targetResult;
            }

            $currentStatus = $targetResult['status'] ?? null;
            $currentReason = $targetResult['reason'] ?? null;
            $currentSource = $targetResult['target_window_decision_source'] ?? null;
            $currentConfidence = $targetResult['target_window_confidence'] ?? null;
            $elongationRule = [
                'rule' => $decision['rule'],
                'status' => $decision['status'],
                'source' => $decision['source'] ?? 'target_acoustic_duration',
                'comparison' => ($decision['source'] ?? null) === 'quran_muaalem_phoneme_alignment'
                    ? 'rule_specific_phoneme_contrast'
                    : ($decision['rule'] === 'ikhfa' ? 'minimum' : 'maximum'),
            ];

            if (isset($decision['ghunnah_duration_ms']) && is_numeric($decision['ghunnah_duration_ms'])) {
                $elongationRule['ghunnah_duration_ms'] = round((float) $decision['ghunnah_duration_ms'], 2);
            }

            if (isset($decision['threshold_ms']) && is_numeric($decision['threshold_ms'])) {
                $elongationRule['threshold_ms'] = round((float) $decision['threshold_ms'], 2);
            }

            if (array_key_exists('error_code', $decision)) {
                $elongationRule['error_code'] = $decision['error_code'];
            }

            if (array_key_exists('model_confidence', $decision)) {
                $elongationRule['model_confidence'] = $decision['model_confidence'];
            }

            if (array_key_exists('phoneme_error', $decision)) {
                $elongationRule['phoneme_error'] = $decision['phoneme_error'];
            }

            return array_merge($targetResult, [
                'raw_target_status' => $targetResult['raw_target_status'] ?? $currentStatus,
                'raw_target_reason' => $targetResult['raw_target_reason'] ?? $currentReason,
                'raw_target_decision_source' => $targetResult['raw_target_decision_source'] ?? $currentSource,
                'raw_target_confidence' => $targetResult['raw_target_confidence'] ?? $currentConfidence,
                'status' => $decision['status'],
                'reason' => $decision['reason'],
                'heuristic_status' => $decision['status'],
                'heuristic_reason' => $decision['reason'],
                'target_window_confidence' => null,
                'target_window_decision_source' => 'target_elongation_rule',
                'elongation_rule' => $elongationRule,
            ]);
        }, $targetResults);
    }

    private function applyTargetWindowModel(string $pythonBinary, string $audioPath, array $ruleTargets, array $targetResults): array
    {
        if (count($ruleTargets) === 0) {
            return $targetResults;
        }

        $scriptPath = base_path('python/predict_target_windows.py');

        if (!is_file($scriptPath)) {
            \Log::warning('Target-window script missing', [
                'script' => $scriptPath,
            ]);

            return array_map(function (array $targetResult): array {
                return array_merge($targetResult, [
                    'target_window_model_status' => 'script_missing',
                ]);
            }, $targetResults);
        }

        $targetsJson = json_encode($ruleTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        \Log::info('Running target-window Tajweed analysis', [
            'audio_path' => $audioPath,
            'targets' => $ruleTargets,
            'targets_json' => $targetsJson,
        ]);

        $process = new Process([
            $pythonBinary,
            $scriptPath,
            $audioPath,
            $targetsJson,
        ]);
        $process->setTimeout(config('tajweed.prediction_timeout', 60));
        $process->setEnv($this->pythonProcessEnvironment());

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            \Log::warning('Target-window script timed out', [
                'script' => $scriptPath,
                'timeout_seconds' => config('tajweed.prediction_timeout', 60),
            ]);

            return array_map(function (array $targetResult): array {
                return array_merge($targetResult, [
                    'target_window_model_status' => 'timeout',
                    'target_window_model_error' => 'Target-window analysis timed out.',
                ]);
            }, $targetResults);
        }

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        $jsonOutput = $this->extractJsonObject($output) ?: $process->getOutput();
        $payload = json_decode($jsonOutput, true);

        \Log::info('Target-window Python raw output', [
            'output' => $output,
            'payload' => $payload,
        ]);

        if (!is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            \Log::warning('Target-window prediction unavailable', [
                'output' => $output,
                'payload' => $payload,
            ]);

            return array_map(function (array $targetResult) use ($payload): array {
                return array_merge($targetResult, [
                    'target_window_model_status' => data_get($payload, 'status', 'failed'),
                    'target_window_model_error' => data_get($payload, 'error'),
                ]);
            }, $targetResults);
        }

        $predictions = collect($payload['targets'] ?? [])
            ->filter(fn($prediction): bool => is_array($prediction))
            ->keyBy(fn(array $prediction): int => (int) ($prediction['target_index'] ?? -1));

        return array_map(function (array $targetResult, int $index) use ($predictions, $payload): array {
            $prediction = $predictions->get($index);

            if (!$prediction) {
                return array_merge($targetResult, [
                    'target_window_model_status' => 'missing_target_prediction',
                ]);
            }

            return array_merge($targetResult, [
                'status' => $prediction['status'] ?? $targetResult['status'],
                'reason' => $prediction['reason'] ?? $targetResult['reason'],
                'heuristic_status' => $prediction['heuristic_status'] ?? $prediction['status'] ?? null,
                'heuristic_reason' => $prediction['heuristic_reason'] ?? $prediction['reason'] ?? null,
                'target_window_model_status' => $payload['model_status'] ?? 'success',
                'target_window_label' => $prediction['label'] ?? null,
                'target_window_confidence' => isset($prediction['confidence'])
                    ? round(((float) $prediction['confidence']) * 100, 2)
                    : null,
                'target_window_probabilities' => $prediction['probabilities'] ?? null,
                'target_window_decision_source' => $prediction['decision_source'] ?? null,
                'target_window_quality' => $prediction['quality'] ?? null,
                'elongation_quality' => $prediction['elongation_quality'] ?? $prediction['quality'] ?? null,
                'target_window_checked_windows' => $prediction['checked_windows'] ?? null,
                'target_window_target_ratio' => $prediction['target_ratio'] ?? null,
                'target_window_error' => $payload['error'] ?? null,
            ]);
        }, $targetResults, array_keys($targetResults));
    }

    /**
     * Compare the recorded phonemes directly with the selected Uthmani ayah.
     *
     * Unlike the general Ikhfa/Izhar/Other classifier, Quran Muaalem is
     * reference-aware and can localize explained pronunciation errors to the
     * exact target character span. Content mismatches remain inconclusive and
     * are never converted into pronunciation errors.
     */
    private function applyQuranPronunciationModel(
        string $pythonBinary,
        string $audioPath,
        string $selectedAyah,
        array $ruleTargets,
        array $targetResults,
        array &$evidence = [],
        ?int $sourceSurah = null,
        ?int $sourceAyah = null
    ): array {
        $evidence = [
            'status' => 'not_run',
            'reference_verified' => false,
        ];

        if (! config('tajweed.enable_quran_pronunciation_model', true) || count($ruleTargets) === 0 || trim($selectedAyah) === '') {
            return $targetResults;
        }

        $scriptPath = base_path('python/predict_quran_pronunciation.py');

        if (! is_file($scriptPath)) {
            $evidence = [
                'status' => 'script_missing',
                'reference_verified' => false,
                'reason' => 'Quran pronunciation analysis script is missing.',
            ];

            return array_map(fn (array $targetResult): array => $this->mergeReferenceModelFailure(
                $targetResult,
                'script_missing',
                $evidence['reason']
            ), $targetResults);
        }

        $targetsJson = json_encode($ruleTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timeout = max(30, (int) config('tajweed.quran_pronunciation_timeout', 120));
        $command = [
            $pythonBinary,
            $scriptPath,
            $audioPath,
            $selectedAyah,
            $targetsJson,
        ];

        if ($sourceSurah !== null && $sourceAyah !== null) {
            $command[] = (string) $sourceSurah;
            $command[] = (string) $sourceAyah;
        }

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->setEnv($this->pythonProcessEnvironment());

        \Log::info('Running reference-aware Quran pronunciation analysis', [
            'audio_path' => $audioPath,
            'target_count' => count($ruleTargets),
            'timeout_seconds' => $timeout,
            'source_surah' => $sourceSurah,
            'source_ayah' => $sourceAyah,
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $evidence = [
                'status' => 'timeout',
                'reference_verified' => false,
                'reason' => "Quran pronunciation analysis timed out after {$timeout} seconds.",
            ];

            \Log::warning('Quran pronunciation analysis timed out', [
                'script' => $scriptPath,
                'timeout_seconds' => $timeout,
            ]);

            return array_map(fn (array $targetResult): array => $this->mergeReferenceModelFailure(
                $targetResult,
                'timeout',
                $evidence['reason']
            ), $targetResults);
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $combinedOutput = trim($stdout . "\n" . $stderr);
        $payload = json_decode($stdout, true);

        if (! is_array($payload)) {
            $jsonOutput = $this->extractJsonObject($combinedOutput);
            $payload = $jsonOutput ? json_decode($jsonOutput, true) : null;
        }

        if ($this->shouldRetryQuranPronunciationAnalysis(is_array($payload) ? $payload : null, $combinedOutput)) {
            \Log::warning('Retrying reference-aware Quran pronunciation analysis after transient model import failure', [
                'audio_path' => $audioPath,
                'target_count' => count($ruleTargets),
                'reason' => $payload['error'] ?? $payload['reason'] ?? null,
            ]);

            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->setEnv($this->pythonProcessEnvironment());

            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                $evidence = [
                    'status' => 'timeout',
                    'reference_verified' => false,
                    'reason' => "Quran pronunciation analysis timed out after {$timeout} seconds.",
                ];

                \Log::warning('Quran pronunciation analysis timed out during retry', [
                    'script' => $scriptPath,
                    'timeout_seconds' => $timeout,
                ]);

                return array_map(fn (array $targetResult): array => $this->mergeReferenceModelFailure(
                    $targetResult,
                    'timeout',
                    $evidence['reason']
                ), $targetResults);
            }

            $stdout = trim($process->getOutput());
            $stderr = trim($process->getErrorOutput());
            $combinedOutput = trim($stdout . "\n" . $stderr);
            $payload = json_decode($stdout, true);

            if (! is_array($payload)) {
                $jsonOutput = $this->extractJsonObject($combinedOutput);
                $payload = $jsonOutput ? json_decode($jsonOutput, true) : null;
            }
        }

        if (! is_array($payload)) {
            $evidence = [
                'status' => 'failed',
                'reference_verified' => false,
                'reason' => 'Quran pronunciation model returned an invalid response.',
            ];

            \Log::warning('Invalid Quran pronunciation model response', [
                'exit_code' => $process->getExitCode(),
                'output' => $combinedOutput,
            ]);

            return array_map(fn (array $targetResult): array => $this->mergeReferenceModelFailure(
                $targetResult,
                'failed',
                $evidence['reason']
            ), $targetResults);
        }

        $predictions = collect($payload['targets'] ?? [])
            ->filter(fn ($prediction): bool => is_array($prediction))
            ->keyBy(fn (array $prediction): int => (int) ($prediction['target_index'] ?? -1));
        $minimumModelConfidence = (float) config('tajweed.quran_pronunciation_min_model_confidence', 0.72);
        $minimumTargetConfidence = (float) config('tajweed.quran_pronunciation_high_target_confidence', 0.82);
        $referenceVerified = ($payload['status'] ?? null) === 'success'
            && ($payload['model_status'] ?? null) === 'loaded'
            && ! (bool) ($payload['content_mismatch'] ?? true)
            && (float) ($payload['model_confidence'] ?? 0) >= $minimumModelConfidence
            && count($targetResults) > 0
            && $predictions->count() === count($targetResults)
            && $predictions->every(fn (array $prediction): bool => in_array($prediction['status'] ?? null, ['correct', 'incorrect'], true)
                && (bool) ($prediction['aligned_expected_target'] ?? false)
                && is_numeric($prediction['confidence'] ?? null)
                && (float) $prediction['confidence'] >= $minimumTargetConfidence);

        $evidence = [
            'status' => $payload['status'] ?? 'failed',
            'model_status' => $payload['model_status'] ?? 'failed',
            'model_id' => $payload['model_id'] ?? null,
            'device' => $payload['device'] ?? null,
            'reference_verified' => $referenceVerified,
            'model_confidence' => $payload['model_confidence'] ?? null,
            'global_per' => $payload['global_per'] ?? null,
            'content_mismatch' => (bool) ($payload['content_mismatch'] ?? false),
            'reason' => $payload['reason'] ?? $payload['error'] ?? null,
            'error' => $payload['error'] ?? null,
            'error_explanation_error' => $payload['error_explanation_error'] ?? null,
            'reference_phonemes' => $payload['reference_phonemes'] ?? null,
            'predicted_phonemes' => $payload['predicted_phonemes'] ?? null,
            'audio_quality' => $payload['audio_quality'] ?? null,
            'audio_preprocessing' => $payload['audio_preprocessing'] ?? null,
            'reference_normalization' => $payload['reference_normalization'] ?? null,
            'source_surah' => $sourceSurah,
            'source_ayah' => $sourceAyah,
            'errors' => $payload['errors'] ?? [],
            'thresholds' => $payload['thresholds'] ?? null,
        ];

        \Log::info('Reference-aware Quran pronunciation analysis completed', [
            'status' => $evidence['status'],
            'model_status' => $evidence['model_status'],
            'reference_verified' => $referenceVerified,
            'model_confidence' => $evidence['model_confidence'],
            'global_per' => $evidence['global_per'],
            'content_mismatch' => $evidence['content_mismatch'],
            'reason' => $evidence['reason'],
            'target_count' => $predictions->count(),
        ]);

        return array_map(function (array $targetResult, int $index) use ($predictions, $payload, $referenceVerified, $minimumTargetConfidence): array {
            $prediction = $predictions->get($index);

            if ($this->pronunciationAnalysisFailed($payload, false)) {
                $referenceError = $payload['error_explanation_error']
                    ?? $payload['reason']
                    ?? $payload['error']
                    ?? 'Reference pronunciation analysis failed.';

                return $this->mergeReferenceModelFailure(
                    $targetResult,
                    (string) ($payload['model_status'] ?? $payload['status'] ?? 'failed'),
                    is_scalar($referenceError)
                        ? (string) $referenceError
                        : (json_encode($referenceError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            ?: 'Reference pronunciation analysis failed.')
                );
            }

            if (! $prediction) {
                $modelError = $payload['reason']
                    ?? $payload['error']
                    ?? 'Quran pronunciation model returned no result for this target.';

                return $this->mergeReferenceModelFailure(
                    $targetResult,
                    (string) ($payload['model_status'] ?? 'missing_target_prediction'),
                    $modelError
                );
            }

            if (in_array((string) ($prediction['status'] ?? ''), ['failed', 'error'], true)
                || (bool) ($prediction['analysis_failure'] ?? false)
                || trim((string) ($prediction['failure_code'] ?? '')) !== '') {
                return $this->mergeReferenceTargetFailure($targetResult, $prediction, $payload);
            }

            $status = (string) ($prediction['status'] ?? 'uncertain');
            $confidence = isset($prediction['confidence']) && is_numeric($prediction['confidence'])
                ? round((float) $prediction['confidence'] * 100, 2)
                : null;
            $trusted = $referenceVerified
                && in_array($status, ['correct', 'incorrect'], true)
                && $confidence !== null
                && $confidence >= ($minimumTargetConfidence * 100)
                && (bool) ($prediction['aligned_expected_target'] ?? false);

            return array_merge($targetResult, [
                'heuristic_status' => $targetResult['heuristic_status'] ?? $targetResult['status'] ?? null,
                'heuristic_reason' => $targetResult['heuristic_reason'] ?? $targetResult['reason'] ?? null,
                'heuristic_target_window_quality' => $targetResult['elongation_quality']
                    ?? $targetResult['heuristic_target_window_quality']
                    ?? $targetResult['target_window_quality']
                    ?? null,
                'elongation_quality' => $targetResult['elongation_quality']
                    ?? $targetResult['target_window_quality']
                    ?? null,
                'status' => $status,
                'reason' => $prediction['reason'] ?? $targetResult['reason'],
                'target_window_model_status' => $payload['model_status'] ?? 'failed',
                'target_window_label' => 'quran_muaalem_' . $status,
                'target_window_confidence' => $confidence,
                'target_window_probabilities' => null,
                'target_window_decision_source' => $trusted ? 'trusted_ml' : 'quran_muaalem_inconclusive',
                'target_window_quality' => [
                    'model' => 'quran_muaalem',
                    'global_phoneme_error_rate' => $payload['global_per'] ?? null,
                    'target_phoneme_error_rate' => $prediction['phoneme_error_rate'] ?? null,
                    'alignment_coverage' => $prediction['alignment_coverage'] ?? null,
                    'content_mismatch' => (bool) ($payload['content_mismatch'] ?? false),
                    'audio' => $payload['audio_quality'] ?? null,
                ],
                'target_window_checked_windows' => 1,
                'target_window_target_ratio' => $targetResult['position_ratio'] ?? null,
                'target_window_error' => $prediction['errors'] ?? [],
                'quran_muaalem_character_span' => $prediction['character_span'] ?? null,
                'quran_muaalem_phoneme_span' => $prediction['phoneme_span'] ?? null,
                'quran_muaalem_elongation' => $prediction['elongation'] ?? null,
            ]);
        }, $targetResults, array_keys($targetResults));
    }

    private function mergeReferenceTargetFailure(
        array $targetResult,
        array $prediction,
        array $payload
    ): array {
        $failureCode = trim((string) ($prediction['failure_code'] ?? 'target_analysis_failed'));
        $reason = trim((string) ($prediction['reason'] ?? ''));
        $reason = $reason !== ''
            ? $reason
            : 'The reference pronunciation model could not align this Tajweed target.';

        return array_merge($targetResult, [
            'heuristic_status' => $targetResult['heuristic_status'] ?? $targetResult['status'] ?? null,
            'heuristic_reason' => $targetResult['heuristic_reason'] ?? $targetResult['reason'] ?? null,
            'heuristic_target_window_quality' => $targetResult['elongation_quality']
                ?? $targetResult['heuristic_target_window_quality']
                ?? $targetResult['target_window_quality']
                ?? null,
            'raw_target_status' => $prediction['status'] ?? 'failed',
            'status' => 'analysis_failed',
            'analysis_failure' => true,
            'failure_code' => $failureCode,
            'reason' => $reason,
            'target_window_model_status' => $payload['model_status'] ?? 'failed',
            'target_window_label' => 'quran_muaalem_failed',
            'target_window_confidence' => null,
            'target_window_probabilities' => null,
            'target_window_decision_source' => 'quran_muaalem_inconclusive',
            'target_window_model_error' => $reason,
            'target_window_quality' => [
                'model' => 'quran_muaalem',
                'global_phoneme_error_rate' => $payload['global_per'] ?? null,
                'target_phoneme_error_rate' => $prediction['phoneme_error_rate'] ?? null,
                'alignment_coverage' => $prediction['alignment_coverage'] ?? null,
                'content_mismatch' => (bool) ($payload['content_mismatch'] ?? false),
                'audio' => $payload['audio_quality'] ?? null,
            ],
            'target_window_checked_windows' => 1,
            'target_window_target_ratio' => $targetResult['position_ratio'] ?? null,
            'target_window_error' => $prediction['errors'] ?? [],
            'quran_muaalem_character_span' => $prediction['character_span'] ?? null,
            'quran_muaalem_phoneme_span' => $prediction['phoneme_span'] ?? null,
        ]);
    }

    private function mergeReferenceModelFailure(
        array $targetResult,
        string $referenceStatus,
        string $referenceError
    ): array {
        $referenceMetadata = [
            'reference_model_status' => $referenceStatus,
            'reference_model_error' => $referenceError,
        ];

        // The Quran reference model is optional when a calibrated target-window
        // model has already supplied a complete binary target decision. Preserve
        // that successful evidence while retaining the reference failure for
        // audit/debugging.
        if ($this->hasTrustedTargetDecision($targetResult)) {
            return array_merge($targetResult, $referenceMetadata);
        }

        return array_merge($targetResult, $referenceMetadata, [
            'heuristic_status' => $targetResult['heuristic_status'] ?? $targetResult['status'] ?? null,
            'heuristic_reason' => $targetResult['heuristic_reason'] ?? $targetResult['reason'] ?? null,
            'status' => 'analysis_failed',
            'reason' => 'The reference pronunciation model failed and no trusted alternate target decision was available.',
            'target_window_model_status' => $referenceStatus,
            'target_window_decision_source' => 'quran_muaalem_inconclusive',
            'target_window_model_error' => $referenceError,
        ]);
    }

    private function targetPositionRatio(array $target): float
    {
        $totalLetters = max(1, (int) ($target['total_letters'] ?? 1));
        $letterPosition = max(0, (int) ($target['letter_position'] ?? 0));

        return min(1.0, max(0.0, ($letterPosition + 0.5) / $totalLetters));
    }

    private function longestGhunnahNearTime(array $segments, float $expectedMs, int $matchWindowMs): float
    {
        $longest = 0.0;

        foreach ($segments as $segment) {
            $startMs = (float) ($segment['start_ms'] ?? 0);
            $endMs = (float) ($segment['end_ms'] ?? $startMs);
            $durationMs = (float) ($segment['duration_ms'] ?? max(0, $endMs - $startMs));
            $centerMs = ($startMs + $endMs) / 2;

            if (abs($centerMs - $expectedMs) <= $matchWindowMs || ($expectedMs >= $startMs && $expectedMs <= $endMs)) {
                $longest = max($longest, $durationMs);
            }
        }

        return $longest;
    }

    private function normalizeArabicLetterForTajweed(string $letter): string
    {
        return strtr($letter, [
            "\u{0623}" => "\u{0621}",
            "\u{0625}" => "\u{0621}",
            "\u{0622}" => "\u{0621}",
            "\u{0624}" => "\u{0621}",
            "\u{0626}" => "\u{0621}",
            "\u{0671}" => "\u{0627}",
        ]);
    }

    private function isArabicMarkForTajweed(string $char): bool
    {
        return (bool) preg_match('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{08D3}-\x{08FF}\x{0640}]/u', $char);
    }

    private function isArabicLetterForTajweed(string $char): bool
    {
        return (bool) preg_match('/[\x{0621}-\x{064A}\x{0671}]/u', $char);
    }

    private function findPreviousArabicLetterIndex(array $chars, int $from): ?int
    {
        for ($i = $from; $i >= 0; $i--) {
            if ($this->isArabicLetterForTajweed($chars[$i])) {
                return $i;
            }
        }

        return null;
    }

    private function findNextArabicLetterIndex(array $chars, int $from): ?int
    {
        $count = count($chars);

        for ($i = $from; $i < $count; $i++) {
            if ($this->isArabicLetterForTajweed($chars[$i])) {
                return $i;
            }
        }

        return null;
    }

    private function isSameWordFathatanCarrier(array $chars, int $tanweenIndex, int $candidateIndex): bool
    {
        if (! isset($chars[$candidateIndex])
            || ! in_array($chars[$candidateIndex], ["\u{0627}", "\u{0649}"], true)) {
            return false;
        }

        for ($index = $tanweenIndex + 1; $index < $candidateIndex; $index++) {
            if (! isset($chars[$index])) {
                return false;
            }

            if ((bool) preg_match('/\s/u', $chars[$index])) {
                return false;
            }

            if (! $this->isArabicMarkForTajweed($chars[$index])) {
                return false;
            }
        }

        return true;
    }

    private function countArabicLettersBefore(array $chars, int $position): int
    {
        $count = 0;

        for ($i = 0; $i < $position; $i++) {
            if (isset($chars[$i]) && $this->isArabicLetterForTajweed($chars[$i])) {
                $count++;
            }
        }

        return $count;
    }

    private function countArabicLetters(string $text): int
    {
        preg_match_all('/[\x{0621}-\x{064A}\x{0671}]/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    private function isLikelyGarbageTranscription(string $text): bool
    {
        preg_match_all('/[\x{0621}-\x{064A}\x{0671}]/u', $text, $matches);
        $letters = $matches[0] ?? [];
        $letterCount = count($letters);

        if ($letterCount < 12) {
            return false;
        }

        $normalizedLetters = array_map(
            fn(string $letter): string => $this->normalizeArabicLetterForTajweed($letter),
            $letters
        );
        $frequencies = array_count_values($normalizedLetters);
        arsort($frequencies);

        $topCount = (int) reset($frequencies);
        $topRatio = $topCount / max(1, $letterCount);

        return $topRatio >= 0.72 || (count($frequencies) <= 2 && $letterCount >= 18);
    }

    /**
     * Provide a predictable Windows-friendly environment for Python, TensorFlow, and Whisper.
     */
    private function pythonProcessEnvironment(): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
        $path = getenv('PATH') ?: getenv('Path') ?: '';
        $pathParts = array_filter(explode(PATH_SEPARATOR, $path));

        foreach (['/usr/local/bin', '/usr/bin', '/bin', '/snap/bin'] as $binaryPath) {
            if (is_dir($binaryPath) && !in_array($binaryPath, $pathParts, true)) {
                $pathParts[] = $binaryPath;
            }
        }

        $path = implode(PATH_SEPARATOR, $pathParts);
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
            'QURAN_MUAALEM_MODEL' => (string) config('tajweed.quran_pronunciation_model', base_path('python/models/muaalem-model-v3_2')),
            'QURAN_MUAALEM_DEVICE' => (string) config('tajweed.quran_pronunciation_device', ''),
            'QURAN_MUAALEM_MIN_MODEL_CONFIDENCE' => (string) config('tajweed.quran_pronunciation_min_model_confidence', 0.72),
            'QURAN_MUAALEM_HIGH_TARGET_CONFIDENCE' => (string) config('tajweed.quran_pronunciation_high_target_confidence', 0.82),
            'QURAN_MUAALEM_MIN_TARGET_ALIGNMENT_COVERAGE' => (string) config('tajweed.quran_pronunciation_min_target_alignment', 0.85),
            'QURAN_MUAALEM_MAX_CONTENT_PER' => (string) config('tajweed.quran_pronunciation_max_content_per', 0.35),
            'QURAN_MUAALEM_MAX_CORRECT_TARGET_PER' => (string) config('tajweed.quran_pronunciation_max_correct_target_per', 0.10),
            'QURAN_MUAALEM_ENABLE_AUDIO_CLEANING' => config('tajweed.quran_pronunciation_audio_cleaning', true) ? '1' : '0',
            'QURAN_MUAALEM_NOISE_REDUCTION_AMOUNT' => (string) config('tajweed.quran_pronunciation_noise_reduction_amount', 0.18),
            'QURAN_MUAALEM_TARGET_RMS' => (string) config('tajweed.quran_pronunciation_target_rms', 0.08),
            'TAJWEED_TARGET_ML_TRUST_THRESHOLD' => (string) config('tajweed.target_ml_trust_threshold', 0.78),
            'TAJWEED_TARGET_ML_STRONG_THRESHOLD' => (string) config('tajweed.target_ml_strong_threshold', 0.88),
            'TAJWEED_ELONGATION_THRESHOLD_MS' => (string) config('tajweed.elongation_threshold_ms', 50),
            'TAJWEED_ELONGATION_LOCAL_WINDOW_SECONDS' => (string) config('tajweed.elongation_local_window_seconds', 0.60),
            'TAJWEED_USE_NOISEREDUCE_LIBRARY' => config('tajweed.use_noisereduce_library', false) ? '1' : '0',
            'TAJWEED_LIBROSA_TRIM_TOP_DB' => (string) config('tajweed.librosa_trim_top_db', 28),
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
        $this->extendExecutionLimit();

        try {
            $audioData = $this->loadStoredAudio($audioRecitation);
            $previousResult = $audioRecitation->analysisResult;
            $previousText = trim((string) optional($previousResult)->transcribed_text);
            $previousPredictions = (array) optional($previousResult)->model_predictions;
            $previousReferenceText = trim((string) data_get($previousPredictions, 'transcription.reference_text', $previousText));
            $previousSpeechText = trim((string) data_get($previousPredictions, 'transcription.speech_text', ''));
            $sourceSurah = data_get($previousPredictions, 'pronunciation.source_surah');
            $sourceAyah = data_get($previousPredictions, 'pronunciation.source_ayah');
            $selectedAyah = $previousReferenceText !== ''
                && $previousReferenceText !== 'Unable to transcribe audio'
                && !$this->isLikelyGarbageTranscription($previousReferenceText)
                ? $previousReferenceText
                : null;

            // Older submissions from the dedicated Ikhfa/Izhar pages stored the
            // selected ayah text but omitted its coordinates. Recover an exact or
            // high-confidence corpus match so re-analysis can use the canonical
            // bundled Quran reference instead of the less reliable text fallback.
            if ($selectedAyah !== null && (!is_numeric($sourceSurah) || !is_numeric($sourceAyah))) {
                $matchedAyah = $this->quranTranscriptionMatcher->match($selectedAyah);

                if ($matchedAyah) {
                    $sourceSurah = (int) $matchedAyah['surah'];
                    $sourceAyah = (int) $matchedAyah['ayah'];

                    \Log::info('Recovered Quran coordinates for Tajweed re-analysis', [
                        'audio_id' => $audioRecitation->id,
                        'source_surah' => $sourceSurah,
                        'source_ayah' => $sourceAyah,
                        'match_score' => $matchedAyah['score'] ?? null,
                        'match_margin' => $matchedAyah['margin'] ?? null,
                    ]);
                }
            }

            $outcome = $this->analyzeRecitation(
                $audioRecitation,
                $audioData,
                $selectedAyah,
                $previousSpeechText !== '' ? $previousSpeechText : null,
                is_numeric($sourceSurah) ? (int) $sourceSurah : null,
                is_numeric($sourceAyah) ? (int) $sourceAyah : null
            );

            if (($outcome['status'] ?? null) === 'timeout') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation)
                    ->with('error', 'Re-analysis took too long. Please try again later.');
            }

            if (($outcome['status'] ?? null) === 'failed') {
                return redirect()
                    ->route('tajweed.result', $audioRecitation)
                    ->with('error', 'Re-analysis could not be completed. Please check the ML configuration and try again.');
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

    // ===== REPORT SCREENSHOT START: Section 4.3.14A - User Correction Submission =====
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
    // ===== REPORT SCREENSHOT END: Section 4.3.14A - User Correction Submission =====

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

        $displayOutcome = $result->displayOutcomeKey();
        $legacyInvalidUncertain = $result->correctness === 'uncertain'
            && $displayOutcome === 'unavailable';
        $displayStatus = match ($displayOutcome) {
            'analysis_failed' => 'failed',
            'processing', 'pending' => $displayOutcome,
            default => $legacyInvalidUncertain ? 'failed' : $result->processing_status,
        };
        $displayCorrectness = in_array($displayOutcome, ['correct', 'incorrect', 'uncertain'], true)
            ? $displayOutcome
            : null;

        return response()->json([
            'status' => $displayStatus,
            'correctness' => $displayCorrectness,
            'predicted_rule' => $result->predicted_rule,
            'classification_status' => $legacyInvalidUncertain
                ? 'failed'
                : $result->classification_status,
            'classification_method' => $result->classification_method,
            'class_probabilities' => $result->class_probabilities,
            'confidence' => $result->confidence_score,
            'feedback' => $result->feedback_message,
            'transcribed_text' => $result->transcribed_text,
            'transcription' => data_get($result->model_predictions, 'transcription'),
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

    // Show combined Ikhfa + Izhar checker page
    public function checker()
    {
        return view('tajweed.checker');
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
