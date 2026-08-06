<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFeedbackService
{
    public function diacritizeArabic(string $text): string
    {
        $text = trim($text);

        if ($text === '' || !$this->containsArabic($text) || $this->hasArabicDiacritics($text)) {
            return $text;
        }

        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            return $text;
        }

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            config('services.gemini.fallback_model', 'gemini-2.5-flash-lite'),
        ])));
        $endpoint = rtrim(config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $prompt = $this->buildDiacritizationPrompt($text);

        foreach ($models as $model) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("{$endpoint}/models/{$model}:generateContent", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0,
                            'maxOutputTokens' => 120,
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Gemini diacritization request failed', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $diacritized = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
                $diacritized = trim($diacritized, " \t\n\r\0\x0B`");

                if ($this->containsArabic($diacritized) && $this->hasArabicDiacritics($diacritized)) {
                    return $diacritized;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini diacritization exception', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $text;
    }

    public function generate(
        string $selectedRule,
        string $prediction,
        int $confidence,
        string $transcribedText,
        string $fallbackFeedback
    ): string {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            return $fallbackFeedback;
        }

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model', 'gemini-2.5-flash'),
            config('services.gemini.fallback_model', 'gemini-2.5-flash-lite'),
        ])));
        $endpoint = rtrim(config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $prompt = $this->buildPrompt($selectedRule, $prediction, $confidence, $transcribedText, $fallbackFeedback);

        foreach ($models as $model) {
            try {
                // ===== REPORT SCREENSHOT START: Section 4.3.13 - Feedback Generation =====
                $response = Http::timeout(25)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("{$endpoint}/models/{$model}:generateContent", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.35,
                            'maxOutputTokens' => 420,
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Gemini feedback request failed', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $responseJson = $response->json();
                $text = trim((string) data_get($responseJson, 'candidates.0.content.parts.0.text', ''));
                // ===== REPORT SCREENSHOT END: Section 4.3.13 - Feedback Generation =====

                if ($this->isUsableFeedback($text)) {
                    Log::info('Gemini feedback generated', [
                        'model' => $model,
                        'finish_reason' => data_get($responseJson, 'candidates.0.finishReason'),
                    ]);

                    return $text;
                }

                Log::warning('Gemini feedback response was too short or empty', [
                    'model' => $model,
                    'finish_reason' => data_get($responseJson, 'candidates.0.finishReason'),
                    'text' => $text,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Gemini feedback exception', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->buildDetailedFallback($selectedRule, $prediction, $confidence, $fallbackFeedback);
    }

    private function buildPrompt(
        string $selectedRule,
        string $prediction,
        int $confidence,
        string $transcribedText,
        string $fallbackFeedback
    ): string {
        $transcription = trim($transcribedText) !== '' ? $transcribedText : 'No transcription available';
        $ruleTip = $prediction === 'other'
            ? 'Tell the learner the recording does not appear to contain a clear Ikhfa or Izhar example, then ask them to record a relevant Quran recitation segment.'
            : "Give one practical improvement tip related to {$selectedRule}.";

        return <<<PROMPT
You are helping a Quran tajweed learner after an audio recitation analysis.

Use the CNN tajweed result as the main evidence. Whisper transcription is only an estimate and may be imperfect for Quran recitation.

Data:
- Selected rule: {$selectedRule}
- CNN detected rule: {$prediction}
- CNN confidence: {$confidence}%
- Whisper transcription: {$transcription}
- Current system feedback: {$fallbackFeedback}

Write helpful, respectful feedback for the learner.
Requirements:
- Write 3 complete sentences.
- Use at least 45 words.
- Mention whether the selected rule appears correct or needs practice.
- {$ruleTip}
- Do not invent exact ayah references.
- Do not claim certainty beyond the CNN confidence.
- Use plain English.
PROMPT;
    }

    private function buildDiacritizationPrompt(string $text): string
    {
        return <<<PROMPT
Add Arabic diacritical marks (harakat) to this short Quran recitation transcription.

Text:
{$text}

Return only the Arabic text with diacritical marks.
Do not add explanations, transliteration, punctuation, ayah numbers, or references.
Do not change the words. If a word is uncertain, keep that word unchanged.
PROMPT;
    }

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    private function hasArabicDiacritics(string $text): bool
    {
        return (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}]/u', $text);
    }

    private function isUsableFeedback(string $text): bool
    {
        if (mb_strlen($text) < 80) {
            return false;
        }

        return (bool) preg_match('/[.!?]$/u', $text);
    }

    private function buildDetailedFallback(
        string $selectedRule,
        string $prediction,
        int $confidence,
        string $fallbackFeedback
    ): string {
        $ruleName = ucfirst($selectedRule);
        $predictionName = ucfirst($prediction);

        if ($prediction === 'other') {
            return "{$fallbackFeedback} The model could not identify a clear Ikhfa or Izhar pattern in this recording with enough confidence. Please record a short Quran recitation segment that contains {$ruleName}, keep the microphone close, and avoid silence, background speech, or unrelated audio.";
        }

        if ($selectedRule === $prediction) {
            return "{$fallbackFeedback} The model detected {$predictionName} with {$confidence}% confidence, so your {$ruleName} is likely present but still needs a clearer recording to be more certain. Focus on keeping the sound steady and applying the rule consistently, then try another recitation with a calm pace and clean microphone input.";
        }

        return "{$fallbackFeedback} Since you selected {$ruleName} but the model detected {$predictionName} with {$confidence}% confidence, this recording likely needs more practice or clearer audio. Focus on the main sound quality for {$ruleName}, then re-record slowly so the system can hear the rule more clearly.";
    }
}
