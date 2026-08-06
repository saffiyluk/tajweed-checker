<?php

namespace Tests\Unit;

use App\Services\TajweedCorrectnessService;
use Tests\TestCase;

class TajweedCorrectnessServiceTest extends TestCase
{
    public function test_silence_is_uncertain_even_if_a_model_also_failed(): void
    {
        $result = $this->service()->evaluate($this->context([
            'silent_or_no_recitation' => true,
            'analysis_failed' => true,
        ]));

        $this->assertSame('uncertain', $result['correctness']);
        $this->assertSame('completed', $result['processing_status']);
        $this->assertSame('no_recitation', $result['classification_status']);
        $this->assertSame(0, $result['confidence']);
    }

    public function test_confirmed_selected_ayah_mismatch_is_uncertain(): void
    {
        $result = $this->service()->evaluate($this->context([
            'selected_ayah_mismatch' => true,
            'recitation_verified' => false,
        ]));

        $this->assertSame('uncertain', $result['correctness']);
        $this->assertSame('selected_ayah_mismatch', $result['classification_status']);
        $this->assertStringContainsString('does not match', $result['reason']);
    }

    public function test_confirmed_selected_ayah_mismatch_wins_over_target_analysis_failure(): void
    {
        $result = $this->service()->evaluate($this->context([
            'selected_ayah_mismatch' => true,
            'recitation_verified' => false,
            'analysis_failed' => true,
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'analysis_failed',
                'analysis_failure' => true,
                'failure_code' => 'target_alignment_failed',
                'target_window_model_status' => 'loaded',
            ]],
        ]));

        $this->assertSame('uncertain', $result['correctness']);
        $this->assertSame('completed', $result['processing_status']);
        $this->assertSame('selected_ayah_mismatch', $result['classification_status']);
    }

    public function test_valid_matching_recitation_without_a_specific_error_gets_conservative_pass(): void
    {
        $result = $this->service()->evaluate($this->context([
            'model_status' => 'unrelated',
            'prediction' => 'other',
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'uncertain',
                'heuristic_status' => 'uncertain',
                'target_window_model_status' => 'loaded',
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
                'target_window_quality' => [
                    'ghunnah_frame_ratio' => 0.025,
                    'ghunnah_strength' => 0.18,
                    'nasal_excess_score' => 0.15,
                ],
            ]],
        ]));

        $this->assertSame('correct', $result['correctness']);
        $this->assertSame('conservative_no_error', $result['classification_status']);
        $this->assertStringContainsString('conservative pass', $result['reason']);
    }

    public function test_matching_recitation_with_no_evaluated_target_is_failed(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [],
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
        $this->assertStringContainsString('No Tajweed target was evaluated', $result['reason']);
    }

    public function test_borderline_target_no_longer_makes_valid_recitation_uncertain(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'uncertain',
                'heuristic_status' => 'uncertain',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 71.2,
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
                'target_window_quality' => [
                    'ghunnah_frame_ratio' => 0.025,
                    'ghunnah_strength' => 0.18,
                    'nasal_excess_score' => 0.15,
                ],
            ]],
        ]));

        $this->assertSame('correct', $result['correctness']);
        $this->assertSame(88, $result['confidence']);
    }

    public function test_low_confidence_loaded_result_no_longer_makes_matching_recitation_uncertain(): void
    {
        config(['tajweed.quran_pronunciation_high_target_confidence' => 0.82]);

        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'correct',
                'target_window_model_status' => 'loaded',
                'target_window_label' => 'quran_muaalem_correct',
                'target_window_confidence' => 81.99,
                'target_window_decision_source' => 'trusted_ml',
            ]],
        ]));

        $this->assertSame('correct', $result['correctness']);
        $this->assertSame('conservative_no_error', $result['classification_status']);
    }

    public function test_trusted_target_model_can_mark_all_targets_correct(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'correct',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 91.2,
                'target_window_decision_source' => 'ml_and_heuristic_agree',
            ]],
        ]));

        $this->assertSame('correct', $result['correctness']);
        $this->assertSame('target_verified', $result['classification_status']);
        $this->assertSame(91, $result['confidence']);
    }

    public function test_trusted_specific_target_error_is_incorrect(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'incorrect',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 92.0,
                'target_window_decision_source' => 'trusted_ml',
            ]],
        ]));

        $this->assertSame('incorrect', $result['correctness']);
        $this->assertSame('target_verified', $result['classification_status']);
    }

    public function test_corroborated_local_izhar_error_is_incorrect(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'uncertain',
                'heuristic_status' => 'incorrect',
                'target_window_model_status' => 'loaded',
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
                'target_window_quality' => [
                    'ghunnah_duration_ms' => 80,
                    'ghunnah_frame_ratio' => 0.05,
                    'ghunnah_strength' => 0.25,
                    'nasal_excess_score' => 0.28,
                ],
            ]],
        ]));

        $this->assertSame('incorrect', $result['correctness']);
        $this->assertSame('elongation_rule', $result['classification_status']);
    }

    public function test_long_izhar_duration_fails_even_when_other_cues_are_low(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'correct',
                'heuristic_status' => 'correct',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 96,
                'target_window_decision_source' => 'trusted_ml',
                'elongation_quality' => [
                    'window_is_silent' => false,
                    'ghunnah_duration_ms' => 80,
                    'ghunnah_frame_ratio' => 0.01,
                    'ghunnah_strength' => 0.12,
                    'nasal_excess_score' => 0.08,
                ],
            ]],
        ]));

        $this->assertSame('incorrect', $result['correctness']);
        $this->assertSame('elongation_rule', $result['classification_status']);
        $this->assertStringContainsString('too long', $result['reason']);
    }

    public function test_short_ikhfa_duration_fails_even_when_model_and_other_cues_say_correct(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        $result = $this->service()->evaluate($this->context([
            'selected_rule' => 'ikhfa',
            'target_results' => [[
                'rule' => 'ikhfa',
                'status' => 'correct',
                'heuristic_status' => 'correct',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 98,
                'target_window_decision_source' => 'trusted_ml',
                'elongation_quality' => [
                    'window_is_silent' => false,
                    'ghunnah_duration_ms' => 49,
                    'ghunnah_frame_ratio' => 0.50,
                    'ghunnah_strength' => 0.90,
                    'nasal_excess_score' => 0.90,
                ],
            ]],
        ]));

        $this->assertSame('incorrect', $result['correctness']);
        $this->assertSame('elongation_rule', $result['classification_status']);
        $this->assertStringContainsString('too short', $result['reason']);
    }

    public function test_aligned_phoneme_elongation_takes_priority_over_misaligned_acoustic_crop(): void
    {
        $result = $this->service()->evaluate($this->context([
            'selected_rule' => 'ikhfa',
            'target_results' => [[
                'rule' => 'ikhfa',
                'status' => 'correct',
                'quran_muaalem_elongation' => [
                    'status' => 'correct',
                    'trusted' => true,
                    'reason' => 'Ikhfa retained the expected nasal elongation.',
                    'model_confidence' => 0.97,
                ],
                // A linear text-position crop can land after the spoken target.
                'elongation_quality' => [
                    'window_is_silent' => false,
                    'ghunnah_duration_ms' => 0,
                ],
            ]],
        ]));

        $this->assertSame('correct', $result['correctness']);
        $this->assertSame('elongation_rule', $result['classification_status']);
        $this->assertSame(97, $result['confidence']);
    }

    public function test_clear_noon_phoneme_makes_short_ikhfa_incorrect_despite_acoustic_false_positive(): void
    {
        $result = $this->service()->evaluate($this->context([
            'selected_rule' => 'ikhfa',
            'target_results' => [[
                'rule' => 'ikhfa',
                'status' => 'correct',
                'quran_muaalem_elongation' => [
                    'status' => 'incorrect',
                    'trusted' => true,
                    'error_code' => 'ikhfa_too_short',
                    'reason' => 'Ikhfa was read too short, like clear Izhar.',
                    'model_confidence' => 0.96,
                    'error' => [
                        'expected_phonemes' => 'ںںں',
                        'predicted_phonemes' => 'ن',
                    ],
                ],
                // Nearby nasal sounds must not rescue this explicit target error.
                'elongation_quality' => [
                    'window_is_silent' => false,
                    'ghunnah_duration_ms' => 500,
                ],
            ]],
        ]));

        $this->assertSame('incorrect', $result['correctness']);
        $this->assertSame('elongation_rule', $result['classification_status']);
        $this->assertSame(96, $result['confidence']);
        $this->assertStringContainsString('too short', $result['reason']);
    }

    public function test_trusted_phoneme_elongation_is_complete_target_evidence_without_coverage_confidence(): void
    {
        $reliable = $this->service()->hasReliableTargetEvidence([
            'rule' => 'ikhfa',
            'status' => 'incorrect',
            'target_window_decision_source' => 'target_elongation_rule',
            'target_window_confidence' => null,
            'quran_muaalem_elongation' => [
                'status' => 'incorrect',
                'trusted' => true,
                'error_code' => 'ikhfa_too_short',
                'model_confidence' => 0.96,
            ],
        ]);

        $this->assertTrue($reliable);
    }

    public function test_elongation_boundary_passes_for_both_rules(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        foreach (['ikhfa', 'izhar'] as $rule) {
            $result = $this->service()->evaluate($this->context([
                'selected_rule' => $rule,
                'target_results' => [[
                    'rule' => $rule,
                    // A contradictory trusted model error must not override the
                    // requested duration-only rule.
                    'status' => 'incorrect',
                    'target_window_model_status' => 'loaded',
                    'target_window_confidence' => 99,
                    'target_window_decision_source' => 'trusted_ml',
                    'elongation_quality' => [
                        'window_is_silent' => false,
                        'ghunnah_duration_ms' => 50,
                    ],
                ]],
            ]));

            $this->assertSame('correct', $result['correctness'], "{$rule} should pass at the boundary");
            $this->assertSame('elongation_rule', $result['classification_status']);
        }
    }

    public function test_silent_local_window_does_not_become_an_elongation_verdict(): void
    {
        $decision = $this->service()->targetElongationDecision([
            'rule' => 'ikhfa',
            'elongation_quality' => [
                'window_is_silent' => true,
                'ghunnah_duration_ms' => 0,
            ],
        ]);

        $this->assertNull($decision);
    }

    public function test_model_runtime_failure_is_failed_with_no_correctness_value(): void
    {
        $result = $this->service()->evaluate($this->context([
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'uncertain',
                'target_window_model_status' => 'failed',
                'target_window_model_error' => 'Model import failed.',
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
            ]],
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
        $this->assertSame('failed', $result['classification_status']);
    }

    public function test_loaded_model_target_alignment_failure_is_never_a_conservative_pass(): void
    {
        $target = [
            'rule' => 'izhar',
            'status' => 'failed',
            'analysis_failure' => true,
            'failure_code' => 'target_alignment_failed',
            'target_window_model_status' => 'loaded',
            'target_window_decision_source' => 'quran_muaalem_inconclusive',
        ];

        $this->assertTrue($this->service()->targetHasAnalysisFailure($target));

        $result = $this->service()->evaluate($this->context([
            'target_results' => [$target],
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
        $this->assertSame('failed', $result['classification_status']);
    }

    public function test_invalid_selected_rule_context_is_failed_not_uncertain(): void
    {
        $result = $this->service()->evaluate($this->context([
            'selected_rule_mismatch' => true,
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
    }

    public function test_unverified_non_silent_recitation_is_failed_not_uncertain(): void
    {
        $result = $this->service()->evaluate($this->context([
            'recitation_verified' => false,
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
    }

    public function test_trusted_target_decision_cannot_replace_selected_ayah_content_verification(): void
    {
        $result = $this->service()->evaluate($this->context([
            'recitation_verified' => false,
            'target_results' => [[
                'rule' => 'izhar',
                'status' => 'correct',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 96,
                'target_window_decision_source' => 'ml_and_heuristic_agree',
            ]],
        ]));

        $this->assertNull($result['correctness']);
        $this->assertSame('failed', $result['processing_status']);
    }

    public function test_out_of_range_and_non_finite_confidence_are_rejected(): void
    {
        $base = [
            'status' => 'correct',
            'target_window_model_status' => 'loaded',
            'target_window_decision_source' => 'trusted_ml',
        ];

        foreach ([-1, 100.01, INF, -INF] as $confidence) {
            $this->assertFalse($this->service()->hasReliableTargetEvidence(array_merge($base, [
                'target_window_confidence' => $confidence,
            ])));
        }
    }

    public function test_later_trusted_reference_result_recovers_an_earlier_optional_model_error(): void
    {
        $target = [
            'rule' => 'izhar',
            'status' => 'correct',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 94,
            'target_window_decision_source' => 'trusted_ml',
            'target_window_model_error' => 'Earlier optional target model failed.',
        ];

        $this->assertFalse($this->service()->targetHasAnalysisFailure($target));
    }

    private function service(): TajweedCorrectnessService
    {
        return new TajweedCorrectnessService;
    }

    private function context(array $overrides = []): array
    {
        return array_merge([
            'model_status' => 'confident',
            'selected_rule' => 'izhar',
            'prediction' => 'izhar',
            'confidence' => 88,
            'silent_or_no_recitation' => false,
            'selected_ayah_mismatch' => false,
            'selected_rule_mismatch' => false,
            'rule_context_valid' => true,
            'recitation_verified' => true,
            'analysis_failed' => false,
            'target_results' => [],
        ], $overrides);
    }
}
