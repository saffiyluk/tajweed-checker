<?php

namespace App\Services;

use App\Models\AudioRecitation;
use App\Models\AnalysisResult;
use App\Helpers\Firebase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TajweedAnalysisService
{
    /**
     * ML Service endpoint (Flask/Python backend)
     * Replace with your actual ML service URL
     */
    protected $mlServiceUrl = 'http://localhost:5000/api/analyze';
    protected $firestore = null;

    public function __construct()
    {
        // Try to initialize Firestore
        try {
            if (config('firebase.credentials') && file_exists(base_path(config('firebase.credentials')))) {
                $this->firestore = Firebase::firestore();
                Log::info('✓ Firestore initialized successfully');
            }
        } catch (\Exception $e) {
            Log::warning('Firestore not configured: ' . $e->getMessage());
            $this->firestore = null;
        }
    }

    /**
     * Analyze audio using ML model
     * 
     * @param AudioRecitation $audioRecitation
     * @return void
     */
    public function analyzeAudio(AudioRecitation $audioRecitation)
    {
        try {
            // Update status to processing
            $result = $audioRecitation->analysisResult;
            $result->update(['processing_status' => 'processing']);

            // Get audio from Firebase (in production, use signed URL or stream)
            // For now, we'll use mock analysis
            $analysisData = $this->performAnalysis($audioRecitation);

            // Update analysis result with findings
            $result->update([
                'correctness' => $analysisData['correctness'],
                'confidence_score' => $analysisData['confidence_score'],
                'feedback_message' => $analysisData['feedback_message'],
                'detected_errors' => $analysisData['detected_errors'],
                'suggestions' => $analysisData['suggestions'],
                'processing_status' => 'completed',
            ]);

            Log::info("Tajweed analysis completed for audio {$audioRecitation->id}");

            // ✅ SAVE TO FIRESTORE AS WELL
            if ($this->firestore) {
                try {
                    $this->saveAnalysisToFirestore($audioRecitation, $result, $analysisData);
                } catch (\Exception $e) {
                    Log::warning('Failed to save to Firestore: ' . $e->getMessage());
                    // Continue anyway - Firestore is optional
                }
            }

        } catch (\Exception $e) {
            Log::error("Tajweed analysis failed: " . $e->getMessage());
            
            $result = $audioRecitation->analysisResult;
            $result->update([
                'processing_status' => 'failed',
                'feedback_message' => 'Analysis failed. Please try again.',
            ]);
        }
    }

    /**
     * Save analysis results to Firestore
     * 
     * @param AudioRecitation $audioRecitation
     * @param AnalysisResult $result
     * @param array $analysisData
     * @return void
     */
    protected function saveAnalysisToFirestore(AudioRecitation $audioRecitation, AnalysisResult $result, array $analysisData)
    {
        if (!$this->firestore) {
            return;
        }

        try {
            $analysisCollection = $this->firestore->collection('analyses');
            
            $analysisCollection->document((string)$result->id)->set([
                'audio_recitation_id' => $audioRecitation->id,
                'user_id' => $audioRecitation->user_id,
                'tajweed_rule' => $audioRecitation->tajweed_rule,
                'correctness' => $result->correctness,
                'confidence_score' => $result->confidence_score,
                'feedback_message' => $result->feedback_message,
                'detected_errors' => $result->detected_errors ?? [],
                'suggestions' => $result->suggestions ?? [],
                'processing_status' => $result->processing_status,
                'analyzed_at' => now()->toIso8601String(),
                'original_filename' => $audioRecitation->original_filename,
                'duration_seconds' => $audioRecitation->duration_seconds,
            ]);

            Log::info("✓ Analysis saved to Firestore for audio {$audioRecitation->id}");
        } catch (\Exception $e) {
            Log::error("Failed to save analysis to Firestore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform actual ML analysis
     * Currently uses mock data - replace with actual ML API call
     * 
     * @param AudioRecitation $audioRecitation
     * @return array
     */
    protected function performAnalysis(AudioRecitation $audioRecitation): array
    {
        // TODO: Replace with actual ML service call
        // This is a mock implementation for demonstration

        $rule = $audioRecitation->tajweed_rule;

        // Example mock responses
        $mockResponses = [
            'ikhfa' => [
                'correct' => [
                    'correctness' => 'correct',
                    'confidence_score' => 0.92,
                    'feedback_message' => 'Excellent! Your Ikhfa\' Haqiqi pronunciation is correct. You demonstrated proper nasalization (ghunnah) for 2 harakah.',
                    'detected_errors' => [],
                    'suggestions' => ['Continue practicing to maintain consistency', 'Record more examples to build confidence'],
                ],
                'incorrect' => [
                    'correctness' => 'incorrect',
                    'confidence_score' => 0.78,
                    'feedback_message' => 'Your pronunciation shows signs of incorrect Ikhfa\'. The nasalization appears too strong, making it sound like Idgham.',
                    'detected_errors' => [
                        ['error' => 'Over-nasalization', 'type' => 'excessive_ghunnah'],
                        ['error' => 'Weak throat articulation', 'type' => 'poor_articulation'],
                    ],
                    'suggestions' => [
                        'Reduce nasalization to approximately 2 harakah',
                        'Focus on clear pronunciation of the Ikhfa letter',
                        'Listen to reference recordings and compare',
                    ],
                ],
            ],
            'izhar' => [
                'correct' => [
                    'correctness' => 'correct',
                    'confidence_score' => 0.88,
                    'feedback_message' => 'Perfect! Your Izhar Halqi pronunciation is clear and correct. No nasalization detected, with proper throat articulation.',
                    'detected_errors' => [],
                    'suggestions' => ['Maintain this level of clarity', 'Practice with different throat letters'],
                ],
                'incorrect' => [
                    'correctness' => 'incorrect',
                    'confidence_score' => 0.65,
                    'feedback_message' => 'Your pronunciation needs improvement. Unwanted nasalization was detected in the Izhar position.',
                    'detected_errors' => [
                        ['error' => 'Nasalization present', 'type' => 'unwanted_ghunnah'],
                        ['error' => 'Unclear throat sound', 'type' => 'poor_clarity'],
                    ],
                    'suggestions' => [
                        'Remove nasalization - Izhar should have NO ghunnah',
                        'Articulate throat letters more clearly',
                        'Practice isolated throat letter sounds',
                    ],
                ],
            ],
        ];

        // Randomly select correct or incorrect for demo
        $isCorrect = rand(0, 1) === 1;
        $responseType = $isCorrect ? 'correct' : 'incorrect';

        return $mockResponses[$rule][$responseType] ?? $this->getDefaultMockResponse($rule);
    }

    /**
     * Get default mock response
     * 
     * @param string $rule
     * @return array
     */
    protected function getDefaultMockResponse(string $rule): array
    {
        return [
            'correctness' => 'correct',
            'confidence_score' => 0.75,
            'feedback_message' => 'Your ' . ucfirst($rule) . ' pronunciation has been analyzed.',
            'detected_errors' => [],
            'suggestions' => ['Record more examples', 'Compare with reference recordings'],
        ];
    }

    /**
     * Get audio duration in seconds
     * 
     * @param string $filePath
     * @return int|null
     */
    public function getAudioDuration($filePath): ?int
    {
        try {
            // Try to use FFmpeg if available
            if (shell_exec('which ffmpeg')) {
                $output = shell_exec("ffmpeg -i \"$filePath\" 2>&1 | grep Duration");
                if (preg_match('/Duration: (\d+):(\d+):(\d+)/', $output, $matches)) {
                    $hours = (int)$matches[1];
                    $minutes = (int)$matches[2];
                    $seconds = (int)$matches[3];
                    return ($hours * 3600) + ($minutes * 60) + $seconds;
                }
            }

            // Fallback: Try to read WebM/MP3 headers for rough estimation
            if (file_exists($filePath)) {
                $fileSize = filesize($filePath);
                // For WebM/MP3, estimate based on file size and typical bitrate
                // Assuming ~128kbps average bitrate
                if ($fileSize > 0) {
                    $estimatedSeconds = (int)($fileSize / 16000); // 128kbps = 16000 bytes/sec
                    return max(1, $estimatedSeconds); // At least 1 second
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Could not determine audio duration: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Call actual ML service (Flask/Python backend)
     * Uncomment and use when ML service is deployed
     * 
     * @param AudioRecitation $audioRecitation
     * @param string $firebaseUrl
     * @return array
     */
    protected function callMLService(AudioRecitation $audioRecitation, string $firebaseUrl): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->mlServiceUrl, [
                    'audio_url' => $firebaseUrl,
                    'rule' => $audioRecitation->tajweed_rule,
                    'user_id' => $audioRecitation->user_id,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('ML Service returned error: ' . $response->status());
            return $this->getDefaultMockResponse($audioRecitation->tajweed_rule);

        } catch (\Exception $e) {
            Log::error('ML Service call failed: ' . $e->getMessage());
            return $this->getDefaultMockResponse($audioRecitation->tajweed_rule);
        }
    }
}
