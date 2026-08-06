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
        if (!config('tajweed.enable_firestore_sync', false)) {
            Log::info('Firestore sync disabled for Tajweed analysis service');
            return;
        }

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

            // This legacy path deliberately fails closed unless a real backend is wired in.
            $analysisData = $this->performAnalysis($audioRecitation);

            // Update analysis result with findings
            $result->update([
                'correctness' => $analysisData['correctness'],
                'confidence_score' => $analysisData['confidence_score'],
                'feedback_message' => $analysisData['feedback_message'],
                'detected_errors' => $analysisData['detected_errors'],
                'suggestions' => $analysisData['suggestions'],
                'processing_status' => $analysisData['processing_status'] ?? 'completed',
                'classification_status' => $analysisData['classification_status'] ?? null,
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
                'classification_status' => 'failed',
                'correctness' => null,
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
     * Fail closed when the legacy service path is enabled.
     *
     * Real inference is performed by the local Python pipeline in
     * TajweedController. This service previously returned a random correct or
     * incorrect result, which could silently overwrite evidence with fabricated
     * analysis whenever TAJWEED_ENABLE_SERVICE_ANALYSIS was enabled.
     */
    protected function performAnalysis(AudioRecitation $audioRecitation): array
    {
        Log::warning('Legacy Tajweed analysis service invoked; returning an explicit failed result instead of mock data', [
            'audio_id' => $audioRecitation->id,
            'rule' => $audioRecitation->tajweed_rule,
        ]);

        return $this->getUnavailableResponse(
            $audioRecitation->tajweed_rule,
            'The legacy analysis service is disabled because it has no trained correctness model.'
        );
    }

    /**
     * Return a deterministic, non-claiming response when an ML backend is not
     * available. Never convert backend failure into a pronunciation verdict.
     */
    protected function getUnavailableResponse(string $rule, ?string $reason = null): array
    {
        $reason ??= 'No trained Tajweed correctness backend was available.';

        return [
            'correctness' => null,
            'confidence_score' => 0,
            'processing_status' => 'failed',
            'classification_status' => 'failed',
            'feedback_message' => $reason . ' Please use the main audio-analysis pipeline and try again.',
            'detected_errors' => [
                [
                    'error' => $reason,
                    'type' => 'analysis_backend_unavailable',
                    'rule' => $rule,
                ],
            ],
            'suggestions' => ['Try the analysis again after the trained Python pipeline is available.'],
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
            return $this->getUnavailableResponse(
                $audioRecitation->tajweed_rule,
                'The configured ML service returned HTTP ' . $response->status() . '.'
            );

        } catch (\Exception $e) {
            Log::error('ML Service call failed: ' . $e->getMessage());
            return $this->getUnavailableResponse(
                $audioRecitation->tajweed_rule,
                'The configured ML service could not be reached.'
            );
        }
    }
}
