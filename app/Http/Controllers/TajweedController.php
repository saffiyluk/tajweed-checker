<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\AudioRecitation;
use App\Models\AnalysisResult;
use App\Services\TajweedAnalysisService;
use Kreait\Firebase\Factory;

class TajweedController extends Controller
{
    protected $storage;
    protected $tajweedService;

    public function __construct(TajweedAnalysisService $tajweedService)
    {
        $this->middleware('auth');
        $this->tajweedService = $tajweedService;

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

                // v7 method: createStorage() returns a Storage instance
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
        $request->validate([
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/webm,audio/mp3,audio/x-wav|max:10240',
            'audio_base64' => 'nullable|string',
            'tajweed_rule' => 'required|in:ikhfa,izhar',
        ]);

        $user = Auth::user();
        $userId = $user->id;

        try {
            $audioData = null;
            $filename = null;

            if ($request->hasFile('audio')) {
                $file = $request->file('audio');
                $audioData = file_get_contents($file->getRealPath());
                $filename = $file->getClientOriginalName();
            } elseif ($request->filled('audio_base64')) {
                $base64Data = $request->input('audio_base64');
                if (preg_match('/^data:audio\/(\w+);base64,/', $base64Data, $matches)) {
                    $audioData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
                    $filename = 'recording_' . time() . '.' . $matches[1];
                } else {
                    return back()->with('error', 'Invalid audio data format');
                }
            } else {
                return back()->with('error', 'No audio file provided');
            }

            if (!$audioData) {
                return back()->with('error', 'Failed to process audio data');
            }

            $rule = $request->input('tajweed_rule');
            $firebaseStoragePath = "users/{$userId}/audios/{$rule}/" . uniqid() . '_' . $filename;
            $firebaseUrl = null;

            // --- Firebase Upload ---
            if ($this->storage) {
                try {
                    $contentType = $request->hasFile('audio')
                        ? $file->getMimeType()
                        : 'audio/' . pathinfo($filename, PATHINFO_EXTENSION);

                    $bucket = $this->storage->getBucket();

                    // Upload audio with metadata
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

                    // Verify upload and get object metadata
                    $object = $bucket->object($firebaseStoragePath);
                    if ($object->exists()) {
                        $objectInfo = $object->info();
                        $fileSize = isset($objectInfo['size']) ? (int) $objectInfo['size'] : strlen($audioData);
                        $uploadedAt = isset($objectInfo['timeCreated']) ? $objectInfo['timeCreated'] : now()->toIso8601String();

                        \Log::info("✓ Audio uploaded to Firebase", [
                            'path' => $firebaseStoragePath,
                            'size' => $fileSize,
                            'uploaded_at' => $uploadedAt,
                        ]);
                    } else {
                        \Log::error("✗ Firebase upload verification failed for: {$firebaseStoragePath}");
                    }

                    $object = $bucket->object($firebaseStoragePath);
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

            // Save audio metadata
            $tempPath = storage_path('app/temp_' . uniqid() . '.wav');
            file_put_contents($tempPath, $audioData);

            $duration = $this->tajweedService->getAudioDuration($tempPath);
            unlink($tempPath);

            $audioRecitation = AudioRecitation::create([
                'user_id' => Auth::id(),
                'audio_file_path' => $firebaseStoragePath,
                'tajweed_rule' => $rule,
                'original_filename' => $filename,
                'duration_seconds' => $duration,
                'firebase_url' => $firebaseUrl,
            ]);

            // Create pending analysis
            AnalysisResult::create([
                'audio_id' => $audioRecitation->id,
                'correctness' => null,
                'confidence_score' => 0,
                'processing_status' => 'pending',
                'feedback_message' => 'Your audio is being analyzed. Please wait...',
            ]);

            \Log::info("Audio recitation created for user {$userId} with rule {$rule}");

            // Run analysis
            $this->tajweedService->analyzeAudio($audioRecitation);

            $audioPath = storage_path('app/' . uniqid('predict_', true) . '_' . basename($filename));
            file_put_contents($audioPath, $audioData);
            $command = "python " . escapeshellarg(base_path("python/predict.py")) . " " . escapeshellarg($audioPath) . " 2>&1";
            $output = shell_exec($command);
            $jsonStart = strrpos($output, '{');
            $jsonOutput = $jsonStart !== false ? substr($output, $jsonStart) : $output;
            $result = json_decode($jsonOutput, true);

            if (!$result) {
                throw new \RuntimeException('Invalid response from Python prediction script: ' . $output);
            }

            if (isset($result['error'])) {
                throw new \RuntimeException('Python prediction failed: ' . $result['error']);
            }

            $prediction = $result['prediction'] ?? 'unknown';
            $confidence = round(($result['confidence'] ?? 0) * 100);

            $selectedRule = $rule; // user selected ikhfa or izhar

            if ($prediction == $selectedRule) {
                $feedback = $confidence >= 80
                    ? "Good recitation. " . ucfirst($rule) . " is correct."
                    : "Recitation matches " . ucfirst($rule) . ", but confidence is only {$confidence}%. Please try again for a clearer reading.";
            } else {
                $feedback = "Recitation appears incorrect. Detected: " . ucfirst($prediction) . " with {$confidence}% confidence.";
            }

            //Update DB result
            AnalysisResult::where('audio_id', $audioRecitation->id)->update([
                'processing_status' => 'completed',
                'feedback_message' => $feedback,
                'confidence_score' => $confidence,
                'correctness' => ($prediction == $selectedRule) ? 1 : 0,
            ]);

            // Save result into session
            session([
                'tajweed_result' => $result
            ]);

            \Log::info('Python raw output: ' . trim($output));
            \Log::info('Python result:', $result);

            if (file_exists($audioPath)) {
                @unlink($audioPath);
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

    // Test Firebase connection
    public function testFirebaseStorage()
    {
        try {
            $credentialsPath = base_path(config('firebase.credentials'));
            $bucketName = config('firebase.storage_bucket');

            // Debug info
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

    public function result($id)
    {
        // [AnalysisResult::with('audio')] -- That means: “Also load the related AudioRecitation data”
        $result = AnalysisResult::with('audio')
            ->where('audio_id', $id)
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
        $recitations = AudioRecitation::where('user_id', $user->id)
            ->with('analysisResult')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('tajweed.history', compact('recitations'));
    }

    /**
     * Get analysis result (AJAX)
     * 
     * @param AudioRecitation $audioRecitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalysisStatus(AudioRecitation $audioRecitation)
    {
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
            'errors' => $result->detected_errors,
            'suggestions' => $result->suggestions,
        ]);
    }

    public function debugFirebase()
    {
        $credentialsPath = base_path(config('firebase.credentials'));
        $bucketName = config('firebase.storage_bucket');

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

            // Verify bucket name
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

            // Try to upload test file
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
        // Delete associated files
        if ($audioRecitation->audio_file_path) {
            // If stored locally
            if (Storage::disk('public')->exists($audioRecitation->audio_file_path)) {
                Storage::disk('public')->delete($audioRecitation->audio_file_path);
            }

            // If stored in Firebase
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

        // Delete analysis results if exists
        if ($audioRecitation->analysisResult) {
            $audioRecitation->analysisResult->delete();
        }

        // Delete the audio recitation
        $audioRecitation->delete();

        return redirect()->route('tajweed.history')->with('success', 'Recording deleted successfully.');
    }

    public function download(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        if ($audioRecitation->firebase_url) {
            return redirect($audioRecitation->firebase_url);
        }

        if ($audioRecitation->audio_file_path && Storage::disk('public')->exists($audioRecitation->audio_file_path)) {
            return Storage::disk('public')->download($audioRecitation->audio_file_path, $audioRecitation->original_filename);
        }

        return back()->with('error', 'File not found.');
    }

    public function getAudioUrl(AudioRecitation $audioRecitation)
    {
        $this->authorize('view', $audioRecitation);

        $url = null;

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
            // If it's already a signed URL, redirect to it
            if (strpos($audioRecitation->firebase_url, 'sign=') !== false) {
                return redirect($audioRecitation->firebase_url);
            }

            // Otherwise generate a new signed URL
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

            // Fallback to the stored URL
            return redirect($audioRecitation->firebase_url);
        }

        // Handle local storage files
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

    public function analyzeAudio(Request $request)
    {
        $audioPath = $request->file('audio')->getPathname();
        $command = "python " . base_path("python/speech_to_text.py") . " " . $audioPath;
        $output = shell_exec($command);
        $result = json_decode($output, true);

        return response()->json($result);
    }


}
