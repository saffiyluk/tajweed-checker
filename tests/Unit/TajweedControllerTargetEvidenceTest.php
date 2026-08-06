<?php

namespace Tests\Unit;

use App\Http\Controllers\TajweedController;
use App\Services\TajweedCorrectnessService;
use ReflectionClass;
use Tests\TestCase;

class TajweedControllerTargetEvidenceTest extends TestCase
{
    public function test_presence_fallback_does_not_overwrite_loaded_target_model_evidence(): void
    {
        $target = [
            'rule' => 'ikhfa',
            'status' => 'incorrect',
            'reason' => 'Target-window ML detected weak ghunnah.',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 94.0,
            'target_window_decision_source' => 'ml_and_heuristic_agree',
        ];

        $result = $this->invokePrivate('enforcePresenceBasedGhunnahRules', [
            [$target],
            [
                'ghunnah_duration_ms' => 600,
                'ghunnah_frame_ratio' => 0.5,
                'ghunnah_strength' => 0.8,
            ],
            'ikhfa',
            80,
            50,
            50,
        ]);

        $this->assertSame([$target], $result);
    }

    public function test_izhar_safety_fallback_does_not_overwrite_loaded_target_model_evidence(): void
    {
        $target = [
            'rule' => 'izhar',
            'status' => 'correct',
            'reason' => 'Target-window ML found clear Izhar.',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 92.0,
            'target_window_decision_source' => 'trusted_ml',
        ];

        $result = $this->invokePrivate('enforceIzharNasalSafety', [
            [$target],
        ]);

        $this->assertSame([$target], $result);
    }

    public function test_final_elongation_rule_overrides_trusted_model_for_short_ikhfa(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        $results = $this->invokePrivate('applyElongationDurationRules', [[[
            'rule' => 'ikhfa',
            'status' => 'correct',
            'reason' => 'Reference alignment found no phoneme error.',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 98.4,
            'target_window_decision_source' => 'trusted_ml',
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 49,
                // These deliberately contradict the duration so the test proves
                // that duration alone decides the elongation rule.
                'ghunnah_frame_ratio' => 0.50,
                'ghunnah_strength' => 0.90,
                'nasal_excess_score' => 0.90,
            ],
        ]]]);

        $this->assertSame('incorrect', $results[0]['status']);
        $this->assertSame('target_elongation_rule', $results[0]['target_window_decision_source']);
        $this->assertSame(49.0, $results[0]['elongation_rule']['ghunnah_duration_ms']);
        $this->assertNull($results[0]['target_window_confidence']);
        $this->assertSame('correct', $results[0]['raw_target_status']);
        $this->assertStringContainsString('too short', $results[0]['reason']);
    }

    public function test_aligned_phoneme_elongation_overrides_a_misaligned_zero_duration_crop(): void
    {
        $results = $this->invokePrivate('applyElongationDurationRules', [[[
            'rule' => 'ikhfa',
            'status' => 'incorrect',
            'quran_muaalem_elongation' => [
                'status' => 'correct',
                'trusted' => true,
                'reason' => 'Ikhfa retained the expected nasal elongation.',
                'model_confidence' => 0.97,
            ],
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 0,
            ],
        ]]]);

        $this->assertSame('correct', $results[0]['status']);
        $this->assertSame('quran_muaalem_phoneme_alignment', $results[0]['elongation_rule']['source']);
        $this->assertArrayNotHasKey('ghunnah_duration_ms', $results[0]['elongation_rule']);
    }

    public function test_short_ikhfa_phoneme_contrast_overrides_nearby_nasal_audio(): void
    {
        $results = $this->invokePrivate('applyElongationDurationRules', [[[
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
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 500,
            ],
        ]]]);

        $this->assertSame('incorrect', $results[0]['status']);
        $this->assertSame('ikhfa_too_short', $results[0]['elongation_rule']['error_code']);
        $this->assertSame('ن', $results[0]['elongation_rule']['phoneme_error']['predicted_phonemes']);
    }

    public function test_final_elongation_rule_overrides_trusted_model_for_long_izhar(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        $results = $this->invokePrivate('applyElongationDurationRules', [[[
            'rule' => 'izhar',
            'status' => 'correct',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 97,
            'target_window_decision_source' => 'trusted_ml',
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 51,
                'ghunnah_frame_ratio' => 0,
                'ghunnah_strength' => 0,
                'nasal_excess_score' => 0,
            ],
        ]]]);

        $this->assertSame('incorrect', $results[0]['status']);
        $this->assertSame('target_elongation_rule', $results[0]['target_window_decision_source']);
        $this->assertStringContainsString('too long', $results[0]['reason']);
    }

    public function test_exact_elongation_boundary_is_correct_for_ikhfa_and_izhar(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        foreach (['ikhfa', 'izhar'] as $rule) {
            $results = $this->invokePrivate('applyElongationDurationRules', [[[
                'rule' => $rule,
                'status' => 'incorrect',
                'target_window_model_status' => 'loaded',
                'target_window_confidence' => 99,
                'target_window_decision_source' => 'trusted_ml',
                'elongation_quality' => [
                    'window_is_silent' => false,
                    'ghunnah_duration_ms' => 50,
                ],
            ]]]);

            $this->assertSame('correct', $results[0]['status']);
            $this->assertSame('target_elongation_rule', $results[0]['target_window_decision_source']);
        }
    }

    public function test_whole_recording_ghunnah_does_not_rescue_a_short_ikhfa_target(): void
    {
        config(['tajweed.elongation_threshold_ms' => 50]);

        $results = $this->invokePrivate('applyElongationDurationRules', [[[
            'rule' => 'ikhfa',
            'status' => 'correct',
            'nearby_ghunnah_ms' => 0,
            'global_ghunnah_ms' => 900,
            'target_window_model_status' => 'unavailable_using_heuristic',
            'target_window_decision_source' => 'conservative_no_error_fallback',
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 20,
            ],
        ]]]);

        $this->assertSame('incorrect', $results[0]['status']);
        $this->assertSame(20.0, $results[0]['elongation_rule']['ghunnah_duration_ms']);
    }

    public function test_silent_target_window_is_not_forced_into_an_elongation_result(): void
    {
        $target = [
            'rule' => 'ikhfa',
            'status' => 'correct',
            'target_window_model_status' => 'loaded',
            'target_window_decision_source' => 'trusted_ml',
            'target_window_confidence' => 90,
            'elongation_quality' => [
                'window_is_silent' => true,
                'ghunnah_duration_ms' => 0,
            ],
        ];

        $results = $this->invokePrivate('applyElongationDurationRules', [[$target]]);

        $this->assertSame([$target], $results);
    }

    public function test_linear_text_ratio_crop_is_diagnostic_not_authoritative(): void
    {
        $target = [
            'rule' => 'ikhfa',
            'status' => 'correct',
            'reason' => 'No aligned elongation error was found.',
            'elongation_quality' => [
                'window_is_silent' => false,
                'ghunnah_duration_ms' => 0,
                'target_alignment_verified' => false,
                'target_alignment_method' => 'linear_text_ratio',
            ],
        ];

        $results = $this->invokePrivate('applyElongationDurationRules', [[$target]]);

        $this->assertSame([$target], $results);
    }

    public function test_short_audio_is_rejected_before_silence_with_actionable_duration_feedback(): void
    {
        $result = $this->invokePrivate('detectAudioInputIssue', [[
            'audio_activity_status' => 'too_short',
            'is_too_short' => true,
            'is_silent' => true,
            'raw_duration_ms' => 250,
            'minimum_duration_ms' => 750,
            'raw_active_frame_ratio' => 0.4,
        ]]);

        $this->assertSame('audio_too_short', $result['type']);
        $this->assertSame(250.0, $result['duration_ms']);
        $this->assertSame(750.0, $result['minimum_duration_ms']);
        $this->assertStringContainsString('0.25 seconds recorded', $result['message']);
    }

    public function test_fathatan_carrier_alif_is_not_mistaken_for_an_izhar_letter(): void
    {
        $targets = $this->invokePrivate('detectTajweedTargets', ["\u{062D}\u{064E}\u{0642}\u{0651}\u{064B}\u{0627} \u{0644}\u{0651}\u{064E}\u{0647}\u{064F}\u{0645}\u{0652}"]);

        $this->assertSame([], $targets);
    }

    public function test_fathatan_carrier_is_skipped_before_a_real_izhar_letter(): void
    {
        $targets = $this->invokePrivate('detectTajweedTargets', ["\u{0639}\u{064E}\u{0644}\u{0650}\u{064A}\u{0645}\u{064B}\u{0627} \u{062D}\u{064E}\u{0643}\u{0650}\u{064A}\u{0645}\u{064B}\u{0627}"]);

        $this->assertCount(1, $targets);
        $this->assertSame('izhar', $targets[0]['rule']);
        $this->assertSame("\u{062D}", $targets[0]['next_letter']);
    }

    public function test_tanween_followed_by_ikhfa_letter_is_detected_as_ikhfa(): void
    {
        $targets = $this->invokePrivate('detectTajweedTargets', ["\u{0639}\u{064E}\u{0644}\u{0650}\u{064A}\u{0645}\u{064C} \u{0642}\u{064E}\u{062F}\u{0650}\u{064A}\u{0631}\u{064C}"]);

        $this->assertCount(1, $targets);
        $this->assertSame('ikhfa', $targets[0]['rule']);
        $this->assertSame('tanwin', $targets[0]['source']);
        $this->assertSame("\u{0642}", $targets[0]['next_letter']);
    }

    public function test_reference_failure_is_explicit_and_does_not_promote_izhar_heuristic(): void
    {
        config([
            'tajweed.enable_hybrid_rule_audio_fallback' => true,
            'tajweed.hybrid_rule_audio_min_confidence' => 68,
            'tajweed.hybrid_rule_audio_fallback_rules' => ['ikhfa'],
        ]);

        $fallbackResults = $this->invokePrivate('applyHybridRuleAudioFallback', [
            [[
                'rule' => 'izhar',
                'expected_rule' => 'izhar',
                'source' => 'tanwin',
                'trigger' => "tanwin + \u{062D}",
                'status' => 'uncertain',
                'reason' => 'The reference pronunciation model failed.',
                'heuristic_status' => 'correct',
                'heuristic_reason' => 'No local nasal hold was detected.',
                'target_window_model_status' => 'failed',
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
                'target_window_confidence' => null,
                'target_window_quality' => [
                    'ghunnah_duration_ms' => 0,
                    'ghunnah_frame_ratio' => 0,
                    'ghunnah_strength' => 0,
                    'nasal_excess_score' => 0,
                ],
            ]],
            [
                'audio_activity_status' => 'usable',
                'raw_rms' => 0.08,
                'raw_peak_amplitude' => 0.4,
                'raw_active_frame_ratio' => 0.7,
                'ghunnah_duration_ms' => 0,
            ],
        ]);
        $results = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [$fallbackResults]);

        $this->assertSame('analysis_failed', $results[0]['status']);
        $this->assertSame('failed', $results[0]['target_window_model_status']);
        $this->assertSame('quran_muaalem_inconclusive', $results[0]['target_window_decision_source']);
        $this->assertNull($results[0]['target_window_confidence']);
        $this->assertArrayNotHasKey('hybrid_evidence', $results[0]);
    }

    public function test_whole_recording_ghunnah_does_not_veto_an_izhar_target(): void
    {
        config([
            'tajweed.enable_hybrid_rule_audio_fallback' => true,
            'tajweed.hybrid_rule_audio_fallback_rules' => ['ikhfa'],
        ]);

        $quality = [
            'duration_ms' => 4000,
            'audio_activity_status' => 'usable',
            'raw_rms' => 0.08,
            'raw_peak_amplitude' => 0.4,
            'raw_active_frame_ratio' => 0.7,
            // This may come from another natural nun/mim elsewhere in the ayah.
            'ghunnah_duration_ms' => 900,
            'ghunnah_frame_ratio' => 0.7,
            'ghunnah_strength' => 0.9,
            'ghunnah_segments' => [],
        ];
        $targets = [[
            'rule' => 'izhar',
            'expected_rule' => 'izhar',
            'source' => 'tanwin',
            'trigger' => "tanwin + \u{062D}",
            'letter_position' => 2,
            'total_letters' => 10,
        ]];

        $heuristicResults = $this->invokePrivate('analyzeTajweedTargetResults', [
            $targets,
            $quality,
            80,
            50,
            50,
        ]);
        $presenceResults = $this->invokePrivate('enforcePresenceBasedGhunnahRules', [
            $heuristicResults,
            $quality,
            'izhar',
            80,
            50,
            50,
        ]);
        $safetyResults = $this->invokePrivate('enforceIzharNasalSafety', [$presenceResults]);
        $fallbackResults = $this->invokePrivate('applyHybridRuleAudioFallback', [$safetyResults, $quality]);
        $finalResults = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [$fallbackResults]);

        $this->assertSame('correct', $heuristicResults[0]['status']);
        $this->assertSame('correct', $presenceResults[0]['status']);
        $this->assertSame($presenceResults, $safetyResults);
        $this->assertSame($safetyResults, $fallbackResults);
        $this->assertSame('correct', $finalResults[0]['status']);
        $this->assertSame('correct', $finalResults[0]['heuristic_status']);
        $this->assertSame('conservative_no_error_fallback', $finalResults[0]['target_window_decision_source']);
    }

    public function test_failed_reference_model_without_trusted_alternate_is_analysis_failed(): void
    {
        config([
            'tajweed.enable_hybrid_rule_audio_fallback' => true,
            'tajweed.hybrid_rule_audio_min_confidence' => 68,
            'tajweed.hybrid_rule_audio_fallback_rules' => ['ikhfa'],
            'tajweed.ikhfa_min_ghunnah_ms' => 80,
            'tajweed.ikhfa_min_local_ghunnah_ms' => 50,
        ]);

        $quality = [
            'audio_activity_status' => 'usable',
            'raw_rms' => 0.08,
            'raw_peak_amplitude' => 0.4,
            'raw_active_frame_ratio' => 0.7,
            'ghunnah_duration_ms' => 260,
            'ghunnah_frame_ratio' => 0.025,
            'ghunnah_strength' => 0.21,
        ];
        $failedReferenceTarget = [[
            'rule' => 'ikhfa',
            'expected_rule' => 'ikhfa',
            'source' => 'noon_sakinah',
            'trigger' => "noon sakinah + \u{0634}",
            'status' => 'uncertain',
            'reason' => 'The reference pronunciation model failed.',
            'nearby_ghunnah_ms' => 50,
            'global_ghunnah_ms' => 260,
            'target_window_model_status' => 'failed',
            'target_window_model_error' => "Could not import module 'AutoFeatureExtractor'.",
            'target_window_decision_source' => 'quran_muaalem_inconclusive',
            'target_window_confidence' => null,
            'target_window_quality' => [
                'ghunnah_duration_ms' => 50,
                'ghunnah_frame_ratio' => 0.025,
                'ghunnah_strength' => 0.21,
                'nasal_excess_score' => 0.18,
            ],
        ]];

        $ruleBasedResults = $this->invokePrivate('enforcePresenceBasedGhunnahRules', [
            $failedReferenceTarget,
            $quality,
            'ikhfa',
            80,
            50,
            50,
        ]);
        $hybridResults = $this->invokePrivate('applyHybridRuleAudioFallback', [
            $ruleBasedResults,
            $quality,
        ]);
        $finalTargets = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [$hybridResults]);

        $decision = (new TajweedCorrectnessService)->evaluate([
            'ml_only' => false,
            'model_status' => 'confident',
            'selected_rule' => 'ikhfa',
            'prediction' => 'ikhfa',
            'confidence' => 72,
            'is_unrelated_audio' => false,
            'transcription_unclear' => false,
            'selected_rule_mismatch' => false,
            'rule_context_valid' => true,
            'target_results' => $finalTargets,
        ]);

        $this->assertSame('analysis_failed', $finalTargets[0]['status']);
        $this->assertSame('failed', $finalTargets[0]['target_window_model_status']);
        $this->assertSame('quran_muaalem_inconclusive', $finalTargets[0]['target_window_decision_source']);
        $this->assertSame("Could not import module 'AutoFeatureExtractor'.", $finalTargets[0]['target_window_model_error']);
        $this->assertNull($decision['correctness']);
        $this->assertSame('failed', $decision['processing_status']);
    }

    public function test_combined_ayah_has_one_izhar_and_two_ikhfa_targets(): void
    {
        $ayah = "\u{0623}\u{064F}\u{0648}\u{06DF}\u{0644}\u{064E}\u{0640}\u{0670}\u{0653}\u{0626}\u{0650}\u{0643}\u{064E} \u{0647}\u{064F}\u{0645}\u{064F} \u{0671}\u{0644}\u{0652}\u{0645}\u{064F}\u{0624}\u{0652}\u{0645}\u{0650}\u{0646}\u{064F}\u{0648}\u{0646}\u{064E} \u{062D}\u{064E}\u{0642}\u{0651}\u{064B}\u{0627} \u{06DA} \u{0644}\u{0651}\u{064E}\u{0647}\u{064F}\u{0645}\u{0652} \u{062F}\u{064E}\u{0631}\u{064E}\u{062C}\u{064E}\u{0640}\u{0670}\u{062A}\u{064C} \u{0639}\u{0650}\u{0646}\u{062F}\u{064E} \u{0631}\u{064E}\u{0628}\u{0651}\u{0650}\u{0647}\u{0650}\u{0645}\u{0652} \u{0648}\u{064E}\u{0645}\u{064E}\u{063A}\u{0652}\u{0641}\u{0650}\u{0631}\u{064E}\u{0629}\u{064C} \u{0648}\u{064E}\u{0631}\u{0650}\u{0632}\u{0652}\u{0642}\u{064C} \u{0643}\u{064E}\u{0631}\u{0650}\u{064A}\u{0645}\u{064C}";
        $targets = $this->invokePrivate('detectTajweedTargets', [$ayah]);

        $this->assertCount(3, $targets);
        $this->assertSame(['izhar' => 1, 'ikhfa' => 2], collect($targets)->countBy('rule')->all());
        $this->assertFalse(collect($targets)->contains(fn (array $target): bool => ($target['next_letter'] ?? null) === "\u{0627}"));

        $noonSakinah = collect($targets)->firstWhere('source', 'noon_sakinah');
        $characters = preg_split('//u', $ayah, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->assertSame("\u{0646}", $characters[$noonSakinah['position']]);
    }

    public function test_runtime_failed_target_is_labeled_analysis_failed_for_display(): void
    {
        $results = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [[
            [
                'rule' => 'izhar',
                'status' => 'incorrect',
                'reason' => 'Acoustic heuristic detected nasal energy.',
                'target_window_model_status' => 'failed',
                'target_window_decision_source' => 'quran_muaalem_inconclusive',
                'target_window_confidence' => null,
            ],
        ]]);

        $this->assertSame('analysis_failed', $results[0]['status']);
        $this->assertSame('incorrect', $results[0]['heuristic_status']);
        $this->assertStringContainsString('analysis failed', strtolower($results[0]['reason']));
    }

    public function test_trusted_reference_target_keeps_its_decision(): void
    {
        $target = [
            'rule' => 'ikhfa',
            'status' => 'correct',
            'reason' => 'Reference alignment passed.',
            'target_window_model_status' => 'loaded',
            'target_window_decision_source' => 'trusted_ml',
            'target_window_confidence' => 97.2,
        ];

        $results = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [[$target]]);

        $this->assertSame([$target], $results);
    }

    public function test_near_zero_untrusted_target_gets_conservative_pass_without_fake_confidence(): void
    {
        config(['tajweed.quran_pronunciation_high_target_confidence' => 0.82]);
        $target = [
            'rule' => 'izhar',
            'status' => 'correct',
            'reason' => 'Untrusted low-confidence result.',
            'target_window_model_status' => 'loaded',
            'target_window_label' => 'quran_muaalem_correct',
            'target_window_decision_source' => 'trusted_ml',
            'target_window_confidence' => 0.01,
        ];

        $results = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [[$target]]);

        $this->assertSame('correct', $results[0]['status']);
        $this->assertSame('correct', $results[0]['heuristic_status']);
        $this->assertNull($results[0]['target_window_confidence']);
        $this->assertSame(0.01, $results[0]['raw_target_confidence']);
    }

    public function test_transient_quran_model_import_failure_is_retryable(): void
    {
        $shouldRetry = $this->invokePrivate('shouldRetryQuranPronunciationAnalysis', [
            [
                'status' => 'failed',
                'error' => "Could not import module 'AutoFeatureExtractor'. Are this object's requirements defined correctly?",
            ],
            '',
        ]);

        $this->assertTrue($shouldRetry);
    }

    public function test_low_direct_similarity_alone_never_claims_a_different_ayah(): void
    {
        $assessment = $this->invokePrivate('assessSelectedAyahMatch', [
            'مِنْ شَرِّ مَا خَلَقَ',
            'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ',
            null,
            113,
            2,
        ]);

        $this->assertSame('unknown', $assessment['status']);
        $this->assertSame('ambiguous_transcript_similarity', $assessment['method']);
    }

    public function test_high_score_high_margin_different_quran_coordinate_is_mismatch(): void
    {
        $assessment = $this->invokePrivate('assessSelectedAyahMatch', [
            'مِنْ شَرِّ مَا خَلَقَ',
            'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ',
            ['surah' => 1, 'ayah' => 2, 'score' => 96, 'margin' => 12],
            113,
            2,
        ]);

        $this->assertSame('mismatch', $assessment['status']);
        $this->assertSame('quran_coordinate_mismatch', $assessment['method']);
        $this->assertSame(1, $assessment['matched_surah']);
    }

    public function test_weak_coordinate_conflict_does_not_create_a_mismatch(): void
    {
        $assessment = $this->invokePrivate('assessSelectedAyahMatch', [
            'مِنْ شَرِّ مَا خَلَقَ',
            'مِنْ شَرِّ الَّذِي خَلَقَ',
            ['surah' => 1, 'ayah' => 2, 'score' => 80, 'margin' => 3],
            113,
            2,
        ]);

        $this->assertNotSame('mismatch', $assessment['status']);
    }

    public function test_decisive_coordinate_conflict_precedes_only_moderate_direct_similarity(): void
    {
        config(['tajweed.selected_ayah_near_exact_match_threshold' => 95]);
        $assessment = $this->invokePrivate('assessSelectedAyahMatch', [
            'مِنْ شَرِّ مَا خَلَقَ',
            'مِنْ شَرِّ الَّذِي خَلَقَ',
            ['surah' => 1, 'ayah' => 2, 'score' => 96, 'margin' => 8],
            113,
            2,
        ]);

        $this->assertSame('mismatch', $assessment['status']);
    }

    public function test_near_exact_words_win_over_a_repeated_phrase_coordinate_conflict(): void
    {
        $assessment = $this->invokePrivate('assessSelectedAyahMatch', [
            'مِنْ شَرِّ مَا خَلَقَ',
            'من شر ما خلق',
            ['surah' => 1, 'ayah' => 2, 'score' => 96, 'margin' => 8],
            113,
            2,
        ]);

        $this->assertSame('match', $assessment['status']);
        $this->assertSame('direct_transcript_similarity', $assessment['method']);
    }

    public function test_authoritative_reference_content_check_overrides_conflicting_asr_mismatch(): void
    {
        $this->assertFalse($this->invokePrivate('hasSelectedAyahMismatch', [
            true,
            false,
            ['status' => 'mismatch'],
        ]));

        $this->assertTrue($this->invokePrivate('hasSelectedAyahMismatch', [
            true,
            true,
            ['status' => 'match'],
        ]));
    }

    public function test_low_confidence_reference_content_pass_does_not_erase_decisive_transcript_mismatch(): void
    {
        // false represents loaded Muaalem evidence below the configured model
        // confidence floor, so it is not authoritative over Quran coordinates.
        $this->assertTrue($this->invokePrivate('hasSelectedAyahMismatch', [
            false,
            false,
            ['status' => 'mismatch', 'method' => 'quran_coordinate_mismatch'],
        ]));

        $this->assertFalse($this->invokePrivate('isRecitationContentVerified', [
            false,
            false,
            false,
            ['status' => 'mismatch'],
        ]));
    }

    public function test_target_model_evidence_cannot_verify_selected_ayah_content(): void
    {
        $this->assertFalse($this->invokePrivate('isRecitationContentVerified', [
            false,
            false,
            false,
            ['status' => 'unknown'],
        ]));

        $this->assertTrue($this->invokePrivate('isRecitationContentVerified', [
            false,
            false,
            false,
            ['status' => 'match'],
        ]));
    }

    public function test_error_explanation_failure_is_a_real_pipeline_failure(): void
    {
        $failed = $this->invokePrivate('pronunciationAnalysisFailed', [[
            'status' => 'uncertain',
            'model_status' => 'loaded',
            'error_explanation_error' => 'Explanation decoder crashed.',
        ], false]);

        $this->assertTrue($failed);
    }

    public function test_global_content_mismatch_survives_target_or_explainer_failure(): void
    {
        $evidence = [
            'status' => 'failed',
            'model_status' => 'loaded',
            'model_confidence' => 0.91,
            'global_per' => 0.64,
            'content_mismatch' => true,
            'error_explanation_error' => 'Target error explanation failed.',
        ];

        $contentChecked = $this->invokePrivate('hasUsableReferenceContentEvidence', [$evidence]);
        $pipelineFailed = $this->invokePrivate('pronunciationAnalysisFailed', [$evidence, false]);
        $selectedAyahMismatch = $this->invokePrivate('hasSelectedAyahMismatch', [
            true,
            $contentChecked && $evidence['content_mismatch'],
            ['status' => 'unknown'],
        ]);

        $this->assertTrue($contentChecked);
        $this->assertTrue($pipelineFailed);
        $this->assertTrue($selectedAyahMismatch);
    }

    public function test_not_run_unusable_audio_is_only_non_failure_for_actual_silence(): void
    {
        $evidence = [
            'status' => 'uncertain',
            'model_status' => 'not_run_unusable_audio',
        ];

        $this->assertFalse($this->invokePrivate('pronunciationAnalysisFailed', [$evidence, true]));
        $this->assertTrue($this->invokePrivate('pronunciationAnalysisFailed', [$evidence, false]));
    }

    public function test_non_silent_short_or_quiet_capture_is_failed_when_models_were_skipped(): void
    {
        foreach (['audio_too_short', 'unclear_audio'] as $issueType) {
            $this->assertTrue($this->invokePrivate('shouldFailAnalysisPipeline', [
                ['status' => 'not_run', 'reference_verified' => false],
                false,
                ['type' => $issueType],
                false,
                false,
            ]));
        }
    }

    public function test_silence_is_not_converted_to_pipeline_failure_by_audio_quality_gate(): void
    {
        $this->assertFalse($this->invokePrivate('shouldFailAnalysisPipeline', [
            ['status' => 'uncertain', 'model_status' => 'not_run_unusable_audio'],
            true,
            ['type' => 'silent_audio'],
            false,
            false,
        ]));
    }

    public function test_complete_trusted_target_evidence_recovers_optional_reference_failure(): void
    {
        $this->assertFalse($this->invokePrivate('shouldFailAnalysisPipeline', [
            ['status' => 'failed', 'model_status' => 'script_missing'],
            false,
            null,
            false,
            true,
        ]));
    }

    public function test_trusted_target_model_survives_optional_reference_model_failure(): void
    {
        $trusted = [
            'rule' => 'izhar',
            'status' => 'correct',
            'target_window_model_status' => 'loaded',
            'target_window_confidence' => 94.0,
            'target_window_decision_source' => 'ml_and_heuristic_agree',
        ];

        $result = $this->invokePrivate('mergeReferenceModelFailure', [
            $trusted,
            'script_missing',
            'Optional reference script missing.',
        ]);

        $this->assertSame('correct', $result['status']);
        $this->assertSame('loaded', $result['target_window_model_status']);
        $this->assertSame('ml_and_heuristic_agree', $result['target_window_decision_source']);
        $this->assertSame('script_missing', $result['reference_model_status']);
    }

    public function test_reference_failure_without_trusted_alternate_is_analysis_failed(): void
    {
        $result = $this->invokePrivate('mergeReferenceModelFailure', [[
            'rule' => 'izhar',
            'status' => 'uncertain',
            'target_window_model_status' => 'unavailable_using_heuristic',
        ], 'script_missing', 'Reference script missing.']);

        $this->assertSame('analysis_failed', $result['status']);
        $this->assertSame('script_missing', $result['target_window_model_status']);
    }

    public function test_loaded_quran_target_alignment_failure_preserves_failure_metadata(): void
    {
        $failed = $this->invokePrivate('mergeReferenceTargetFailure', [[
            'rule' => 'izhar',
            'status' => 'uncertain',
            'reason' => 'Original heuristic was borderline.',
        ], [
            'status' => 'failed',
            'analysis_failure' => true,
            'failure_code' => 'target_alignment_failed',
            'reason' => 'Expected target phonemes could not be aligned.',
            'errors' => [['type' => 'alignment']],
        ], [
            'status' => 'success',
            'model_status' => 'loaded',
            'content_mismatch' => false,
            'global_per' => 0.08,
        ]]);

        $normalized = $this->invokePrivate('neutralizeUntrustedTargetDecisions', [[$failed]]);
        $decision = (new TajweedCorrectnessService)->evaluate([
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
            'target_results' => $normalized,
        ]);

        $this->assertSame('analysis_failed', $normalized[0]['status']);
        $this->assertTrue($normalized[0]['analysis_failure']);
        $this->assertSame('target_alignment_failed', $normalized[0]['failure_code']);
        $this->assertSame('loaded', $normalized[0]['target_window_model_status']);
        $this->assertNull($decision['correctness']);
        $this->assertSame('failed', $decision['processing_status']);
    }

    public function test_missing_selected_coordinates_are_resolved_from_reference_text(): void
    {
        [$surah, $ayah, $match] = $this->invokePrivate('resolveSelectedAyahCoordinates', [
            'قُلْ هُوَ اللَّهُ أَحَدٌ',
            null,
            null,
        ]);

        $this->assertSame(112, $surah);
        $this->assertSame(1, $ayah);
        $this->assertIsArray($match);
    }

    private function invokePrivate(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass(TajweedController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $correctnessService = $reflection->getProperty('tajweedCorrectnessService');
        $correctnessService->setAccessible(true);
        $correctnessService->setValue($controller, app(TajweedCorrectnessService::class));
        $quranMatcher = $reflection->getProperty('quranTranscriptionMatcher');
        $quranMatcher->setAccessible(true);
        $quranMatcher->setValue($controller, app(\App\Services\QuranTranscriptionMatcher::class));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $arguments);
    }
}
