<?php

namespace App\Services;

class TajweedCorrectnessService
{
    /**
     * Convert the analysis evidence into the user-facing result policy.
     *
     * "Uncertain" is reserved for two input-validation outcomes only:
     * no recitation/silence, or a confident mismatch with the selected ayah.
     * A usable, matching recitation is binary. It is Incorrect only when a
     * trusted model or a corroborated target-local heuristic found an error;
     * otherwise it receives a conservative Correct result. Pipeline failures
     * are kept separate from pronunciation correctness.
     */
    public function evaluate(array $context): array
    {
        $selectedRule = (string) ($context['selected_rule'] ?? '');
        $confidence = $this->boundedConfidence($context['confidence'] ?? 0);
        $modelStatus = strtolower((string) ($context['model_status'] ?? 'unknown'));
        $silentOrNoRecitation = (bool) ($context['silent_or_no_recitation'] ?? false);
        $selectedAyahMismatch = (bool) ($context['selected_ayah_mismatch'] ?? false);
        $selectedRuleMismatch = (bool) ($context['selected_rule_mismatch'] ?? false);
        $ruleContextValid = (bool) ($context['rule_context_valid'] ?? false);
        $recitationVerified = (bool) ($context['recitation_verified'] ?? false);
        $targetResults = array_values(array_filter(
            (array) ($context['target_results'] ?? []),
            fn ($targetResult): bool => is_array($targetResult)
        ));

        // ===== REPORT SCREENSHOT START: Section 4.3.11A - Correctness Evidence Validation =====
        if ($silentOrNoRecitation) {
            return $this->result(
                'uncertain',
                0,
                'The recording is silent or contains no recitation. Please record the selected ayah and try again.',
                'completed',
                'no_recitation'
            );
        }

        if ($selectedAyahMismatch) {
            return $this->result(
                'uncertain',
                $confidence,
                'The recitation does not match the selected ayah. Please select the ayah you intend to read, then record it again.',
                'completed',
                'selected_ayah_mismatch'
            );
        }

        $targetAnalysisFailed = collect($targetResults)->contains(
            fn (array $targetResult): bool => $this->targetHasAnalysisFailure($targetResult)
        );
        $pipelineFailed = (bool) ($context['analysis_failed'] ?? false)
            || in_array($modelStatus, ['failed', 'error', 'timeout', 'script_missing'], true)
            || $targetAnalysisFailed;

        if ($pipelineFailed) {
            return $this->failure(
                'The pronunciation analysis could not be completed. No correctness result was assigned; please re-analyze this recording.'
            );
        }

        // A selected rule which is absent from the selected ayah is an invalid
        // analysis setup, not evidence that the user read a different ayah.
        if ($selectedRuleMismatch || ! $ruleContextValid) {
            return $this->failure(
                'The selected ayah does not contain a valid target for the chosen Tajweed rule. Please choose the matching rule or ayah.'
            );
        }

        if (! $recitationVerified) {
            return $this->failure(
                'The app could not verify the recording against the selected ayah. No correctness result was assigned.'
            );
        }

        if ($targetResults === []) {
            return $this->failure(
                'No Tajweed target was evaluated in this matching recitation. No correctness result was assigned.'
            );
        }
        // ===== REPORT SCREENSHOT END: Section 4.3.11A - Correctness Evidence Validation =====

        // ===== REPORT SCREENSHOT START: Section 4.3.11B - Final Correctness Decision =====
        $targets = collect($targetResults);
        $incorrectTarget = $targets->first(function (array $targetResult): bool {
            // The target-aligned elongation verdict is authoritative. Quran
            // phoneme alignment is preferred, with local acoustic duration as
            // a fallback: short Ikhfa and long Izhar are errors.
            $elongationDecision = $this->targetElongationDecision($targetResult);

            if ($elongationDecision !== null) {
                return $elongationDecision['status'] === 'incorrect';
            }

            return ($this->hasReliableTargetEvidence($targetResult)
                    && ($targetResult['status'] ?? null) === 'incorrect')
                || $this->hasStrongTargetError($targetResult);
        });

        $targetConfidence = $targets
            ->map(fn (array $targetResult) => $targetResult['target_window_confidence'] ?? null)
            ->filter(fn ($value): bool => is_numeric($value) && is_finite((float) $value))
            ->map(fn ($value): float => min(100.0, max(0.0, (float) $value)))
            ->min();
        $allTargetsTrusted = $targets->isNotEmpty()
            && $targets->every(fn (array $targetResult): bool => $this->hasReliableTargetEvidence($targetResult));
        $allTargetsHaveElongationDecision = $targets->isNotEmpty()
            && $targets->every(
                fn (array $targetResult): bool => $this->targetElongationDecision($targetResult) !== null
            );
        $elongationModelConfidence = $targets
            ->map(function (array $targetResult): ?float {
                $decision = $this->targetElongationDecision($targetResult);

                if (($decision['source'] ?? null) !== 'quran_muaalem_phoneme_alignment') {
                    return null;
                }

                return $this->percentageConfidence($decision['model_confidence'] ?? null);
            })
            ->filter(fn ($value): bool => $value !== null)
            ->min();

        if (is_array($incorrectTarget)) {
            $elongationDecision = $this->targetElongationDecision($incorrectTarget);
            $isElongationError = ($elongationDecision['status'] ?? null) === 'incorrect';
            $trusted = ! $isElongationError && $this->hasReliableTargetEvidence($incorrectTarget);
            $alignedElongationConfidence = $isElongationError
                ? $this->percentageConfidence($elongationDecision['model_confidence'] ?? null)
                : null;
            $incorrectConfidence = $alignedElongationConfidence !== null
                ? (int) round($alignedElongationConfidence)
                : ($trusted && is_numeric($incorrectTarget['target_window_confidence'] ?? null)
                    ? $this->boundedConfidence($incorrectTarget['target_window_confidence'])
                    : $confidence);

            return $this->result(
                'incorrect',
                $incorrectConfidence,
                $isElongationError
                    ? (string) $elongationDecision['reason']
                    : ($trusted
                        ? 'A trusted target-aware pronunciation model found a specific Tajweed error.'
                        : 'A corroborated target-local acoustic check found a specific Tajweed error.'),
                'completed',
                $isElongationError
                    ? 'elongation_rule'
                    : ($trusted ? 'target_verified' : 'strong_target_error')
            );
        }

        if ($allTargetsHaveElongationDecision) {
            return $this->result(
                'correct',
                $elongationModelConfidence !== null
                    ? (int) round((float) $elongationModelConfidence)
                    : $confidence,
                'Every detected Ikhfa and Izhar target met the target-aligned elongation rule.',
                'completed',
                'elongation_rule'
            );
        }

        return $this->result(
            'correct',
            $allTargetsTrusted && $targetConfidence !== null
                ? (int) round((float) $targetConfidence)
                : $confidence,
            $allTargetsTrusted
                ? 'Trusted target-aware analysis found no pronunciation error at any detected Tajweed target.'
                : 'The recording matched the selected ayah and no specific, strong Tajweed error was detected. This is a conservative pass.',
            'completed',
            $allTargetsTrusted ? 'target_verified' : 'conservative_no_error'
        );
        // ===== REPORT SCREENSHOT END: Section 4.3.11B - Final Correctness Decision =====
    }

    private function result(
        ?string $correctness,
        int $confidence,
        string $reason,
        string $processingStatus,
        string $classificationStatus
    ): array {
        return [
            'correctness' => $correctness,
            'confidence' => $confidence,
            'reason' => $reason,
            'processing_status' => $processingStatus,
            'classification_status' => $classificationStatus,
        ];
    }

    private function failure(string $reason): array
    {
        return $this->result(null, 0, $reason, 'failed', 'failed');
    }

    public function hasReliableTargetEvidence(array $targetResult): bool
    {
        $decisionSource = (string) ($targetResult['target_window_decision_source'] ?? '');
        $modelStatus = (string) ($targetResult['target_window_model_status'] ?? '');
        $rawConfidence = $targetResult['target_window_confidence'] ?? null;
        $phonemeElongation = $targetResult['quran_muaalem_elongation'] ?? null;

        if ($decisionSource === 'target_elongation_rule'
            && is_array($phonemeElongation)
            && (bool) ($phonemeElongation['trusted'] ?? false)
            && in_array($targetResult['status'] ?? null, ['correct', 'incorrect'], true)
            && ($phonemeElongation['status'] ?? null) === ($targetResult['status'] ?? null)) {
            return true;
        }

        if (! is_numeric($rawConfidence)) {
            return false;
        }

        $confidence = (float) $rawConfidence;

        if (! is_finite($confidence) || $confidence < 0 || $confidence > 100) {
            return false;
        }

        if ($decisionSource === 'hybrid_rule_audio') {
            return ($targetResult['rule'] ?? null) === 'ikhfa'
                && $modelStatus === 'hybrid_rule_audio'
                && $confidence >= $this->confidenceFloor(
                    'tajweed.hybrid_rule_audio_min_confidence',
                    68
                )
                && in_array($targetResult['status'] ?? null, ['correct', 'incorrect'], true);
        }

        $trustedDecisionSources = [
            'ml_and_heuristic_agree',
            'strong_ml_with_borderline_heuristic',
            'trusted_ml',
        ];

        if ($modelStatus !== 'loaded'
            || ! in_array($decisionSource, $trustedDecisionSources, true)
            || ! in_array($targetResult['status'] ?? null, ['correct', 'incorrect'], true)) {
            return false;
        }

        $minimumConfidence = match ($decisionSource) {
            'strong_ml_with_borderline_heuristic' => $this->confidenceFloor(
                'tajweed.target_ml_strong_threshold',
                0.88
            ),
            'trusted_ml' => str_starts_with(
                (string) ($targetResult['target_window_label'] ?? ''),
                'quran_muaalem_'
            )
                ? $this->confidenceFloor('tajweed.quran_pronunciation_high_target_confidence', 0.82)
                : $this->confidenceFloor('tajweed.target_ml_trust_threshold', 0.78),
            default => $this->confidenceFloor('tajweed.target_ml_trust_threshold', 0.78),
        };

        return $confidence >= $minimumConfidence;
    }

    /**
     * Runtime failures are not pronunciation evidence and must never be shown as
     * "Not enough evidence". A later trusted decision may recover an earlier
     * optional-model error, so reliable evidence is checked first.
     */
    public function targetHasAnalysisFailure(array $targetResult): bool
    {
        if ($this->hasReliableTargetEvidence($targetResult)) {
            return false;
        }

        $targetStatus = strtolower((string) ($targetResult['status'] ?? ''));
        if (in_array($targetStatus, ['analysis_failed', 'failed', 'error'], true)
            || (bool) ($targetResult['analysis_failure'] ?? false)
            || trim((string) ($targetResult['failure_code'] ?? '')) !== '') {
            return true;
        }

        $modelStatus = strtolower((string) ($targetResult['target_window_model_status'] ?? ''));

        if (in_array($modelStatus, [
            'failed',
            'error',
            'timeout',
            'script_missing',
            'missing_target_prediction',
        ], true)) {
            return true;
        }

        return ($targetResult['target_window_decision_source'] ?? null) === 'quran_muaalem_inconclusive'
            && trim((string) ($targetResult['target_window_model_error'] ?? '')) !== '';
    }

    /**
     * Prefer the explicit target elongation rule. The older corroborated
     * acoustic fallback remains only for legacy rows which lack that evidence.
     */
    public function hasStrongTargetError(array $targetResult): bool
    {
        if ($this->targetHasAnalysisFailure($targetResult)) {
            return false;
        }

        $elongationDecision = $this->targetElongationDecision($targetResult);

        if ($elongationDecision !== null) {
            return $elongationDecision['status'] === 'incorrect';
        }

        if (($targetResult['target_window_decision_source'] ?? null) === 'strong_target_error_fallback') {
            return ($targetResult['status'] ?? null) === 'incorrect';
        }

        if (($targetResult['heuristic_status'] ?? null) !== 'incorrect') {
            return false;
        }

        $quality = (array) (
            $targetResult['elongation_quality']
            ?? $targetResult['heuristic_target_window_quality']
            ?? $targetResult['target_window_quality']
            ?? []
        );

        if ($quality === []
            || (bool) data_get($quality, 'window_is_silent', false)
            || (bool) data_get($quality, 'content_mismatch', false)
            || (array_key_exists('target_alignment_verified', $quality)
                && ! (bool) $quality['target_alignment_verified'])) {
            return false;
        }

        $rule = (string) ($targetResult['rule'] ?? '');
        $duration = (float) data_get($quality, 'ghunnah_duration_ms', 0);
        $ratio = (float) data_get($quality, 'ghunnah_frame_ratio', 0);
        $strength = (float) data_get($quality, 'ghunnah_strength', 0);
        $nasalScore = (float) data_get($quality, 'nasal_excess_score', 0);

        if ($rule === 'izhar') {
            return $duration >= (float) config('tajweed.izhar_strong_error_min_ghunnah_ms', 70)
                && $ratio >= (float) config('tajweed.izhar_strong_error_min_ratio', 0.04)
                && $strength >= (float) config('tajweed.izhar_strong_error_min_strength', 0.20)
                && $nasalScore >= (float) config('tajweed.izhar_strong_error_min_score', 0.22);
        }

        if ($rule === 'ikhfa') {
            return $duration <= (float) config('tajweed.ikhfa_strong_error_max_ghunnah_ms', 30)
                && $ratio <= (float) config('tajweed.ikhfa_strong_error_max_ratio', 0.008)
                && $strength <= (float) config('tajweed.ikhfa_strong_error_max_strength', 0.10)
                && $nasalScore < (float) config('tajweed.ikhfa_strong_error_max_score', 0.10);
        }

        return false;
    }

    /**
     * Apply the binary elongation policy requested by the learner. Prefer the
     * Quran reference model's target-aligned hidden/clear-noon contrast because
     * it follows the actual recitation timing. A millisecond measurement is a
     * fallback only when its target timing is verified; proportional text
     * position alone is diagnostic and cannot decide correctness.
     */
    public function targetElongationDecision(array $targetResult): ?array
    {
        $rule = strtolower((string) ($targetResult['rule'] ?? ''));

        if (! in_array($rule, ['ikhfa', 'izhar'], true)) {
            return null;
        }

        $expectedRule = strtolower((string) ($targetResult['expected_rule'] ?? $rule));

        if (in_array($expectedRule, ['ikhfa', 'izhar'], true) && $expectedRule !== $rule) {
            return null;
        }

        $phonemeEvidence = $targetResult['quran_muaalem_elongation'] ?? null;

        if (is_array($phonemeEvidence)
            && (bool) ($phonemeEvidence['trusted'] ?? false)
            && in_array($phonemeEvidence['status'] ?? null, ['correct', 'incorrect'], true)) {
            return [
                'status' => (string) $phonemeEvidence['status'],
                'reason' => (string) ($phonemeEvidence['reason']
                    ?? ($phonemeEvidence['status'] === 'incorrect'
                        ? 'The target phoneme elongation did not match the selected Tajweed rule.'
                        : 'The target phoneme elongation matched the selected Tajweed rule.')),
                'rule' => $rule,
                'source' => 'quran_muaalem_phoneme_alignment',
                'error_code' => $phonemeEvidence['error_code'] ?? null,
                'model_confidence' => $phonemeEvidence['model_confidence'] ?? null,
                'phoneme_error' => $phonemeEvidence['error'] ?? null,
            ];
        }

        $quality = (array) (
            $targetResult['elongation_quality']
            ?? $targetResult['heuristic_target_window_quality']
            ?? $targetResult['target_window_quality']
            ?? []
        );

        if ($quality === []
            || (bool) data_get($quality, 'window_is_silent', false)
            || (bool) data_get($quality, 'content_mismatch', false)
            || (array_key_exists('target_alignment_verified', $quality)
                && ! (bool) $quality['target_alignment_verified'])
            || ! array_key_exists('ghunnah_duration_ms', $quality)
            || ! is_numeric($quality['ghunnah_duration_ms'])
            || ! is_finite((float) $quality['ghunnah_duration_ms'])) {
            return null;
        }

        $duration = max(0.0, (float) $quality['ghunnah_duration_ms']);
        $configuredThreshold = (float) config('tajweed.elongation_threshold_ms', 50);
        $threshold = is_finite($configuredThreshold) && $configuredThreshold > 0
            ? $configuredThreshold
            : 50.0;
        $incorrect = $rule === 'ikhfa'
            ? $duration < $threshold
            : $duration > $threshold;

        if ($rule === 'ikhfa') {
            $reason = $incorrect
                ? "Ikhfa ghunnah was too short ({$this->formatMilliseconds($duration)} ms; minimum {$this->formatMilliseconds($threshold)} ms). Hold the nasal sound longer."
                : "Ikhfa ghunnah duration met the elongation rule ({$this->formatMilliseconds($duration)} ms; minimum {$this->formatMilliseconds($threshold)} ms).";
        } else {
            $reason = $incorrect
                ? "Izhar nasal hold was too long ({$this->formatMilliseconds($duration)} ms; maximum {$this->formatMilliseconds($threshold)} ms). Keep the pronunciation short and clear."
                : "Izhar stayed within the elongation limit ({$this->formatMilliseconds($duration)} ms; maximum {$this->formatMilliseconds($threshold)} ms).";
        }

        return [
            'status' => $incorrect ? 'incorrect' : 'correct',
            'reason' => $reason,
            'rule' => $rule,
            'source' => 'target_acoustic_duration',
            'ghunnah_duration_ms' => $duration,
            'threshold_ms' => $threshold,
        ];
    }

    private function formatMilliseconds(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function confidenceFloor(string $configKey, float $default): float
    {
        $configured = (float) config($configKey, $default);

        // Model thresholds are conventionally configured as probabilities,
        // while stored target confidence uses a 0..100 percentage.
        return $configured <= 1.0 ? $configured * 100.0 : $configured;
    }

    private function boundedConfidence(mixed $confidence): int
    {
        if (! is_numeric($confidence) || ! is_finite((float) $confidence)) {
            return 0;
        }

        return (int) round(min(100.0, max(0.0, (float) $confidence)));
    }

    private function percentageConfidence(mixed $confidence): ?float
    {
        if (! is_numeric($confidence) || ! is_finite((float) $confidence)) {
            return null;
        }

        $value = (float) $confidence;

        if ($value < 0) {
            return null;
        }

        return min(100.0, $value <= 1.0 ? $value * 100.0 : $value);
    }
}
