<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\AudioRecitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TajweedResultViewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_trusted_target_result_displays_pronunciation_confidence(): void
    {
        $user = User::create([
            'name' => 'Pronunciation Result User',
            'email' => 'pronunciation-result-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/recitation.wav',
            'tajweed_rule' => 'izhar',
            'original_filename' => 'recitation.wav',
            'duration_seconds' => 10,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'correct',
            'confidence_score' => 99,
            'predicted_rule' => 'other',
            'classification_status' => 'reference_verified',
            'classification_method' => 'quran_muaalem_reference_alignment',
            'processing_status' => 'completed',
            'feedback_message' => 'Correct target pronunciation.',
            'transcribed_text' => 'مِنْ عِلْمٍ',
            'detected_errors' => [
                [
                    'type' => 'target_analysis',
                    'targets' => [
                        [
                            'rule' => 'izhar',
                            'status' => 'correct',
                            'target_window_model_status' => 'loaded',
                            'target_window_confidence' => 99.2,
                            'target_window_decision_source' => 'trusted_ml',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.result', $audio))
            ->assertOk()
            ->assertSee('Pronunciation confidence')
            ->assertSee('Other (broad classifier only; reference verified)')
            ->assertSee('Correct target pronunciation.');
    }

    public function test_mixed_rule_binary_result_displays_both_evaluated_rules_and_each_target_outcome(): void
    {
        $user = User::create([
            'name' => 'Combined Result User',
            'email' => 'combined-result-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/combined-recitation.wav',
            // This remains the legacy entry/focus value stored by the checker.
            'tajweed_rule' => 'ikhfa',
            'original_filename' => 'combined-recitation.wav',
            'duration_seconds' => 12,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'correct',
            'confidence_score' => 87,
            'predicted_rule' => 'ikhfa',
            'classification_status' => 'confident',
            'classification_method' => 'cnn_rule_priority',
            'processing_status' => 'completed',
            'feedback_message' => 'No specific target error was detected.',
            'transcribed_text' => 'مِنْ شَرِّ غَاسِقٍ إِذَا وَقَبَ',
            'detected_errors' => [
                [
                    'type' => 'target_analysis',
                    'targets' => [
                        [
                            'rule' => 'ikhfa',
                            'status' => 'correct',
                            'snippet' => 'مِنْ شَ',
                            'position' => 0,
                            'end_position' => 5,
                            'target_window_model_status' => 'loaded',
                            'target_window_confidence' => 98.4,
                            'target_window_decision_source' => 'trusted_ml',
                        ],
                        [
                            'rule' => 'izhar',
                            'status' => 'correct',
                            'snippet' => 'قٍ إِ',
                            'position' => 17,
                            'end_position' => 21,
                            'target_window_model_status' => 'loaded',
                            'target_window_confidence' => null,
                            'target_window_decision_source' => 'conservative_no_error_fallback',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.result', $audio))
            ->assertOk()
            ->assertSeeText('Rules evaluated')
            ->assertSeeText('Rules Evaluated')
            ->assertSeeText('Ikhfa & Izhar')
            ->assertSeeText('Ikhfa target 1')
            ->assertSeeText('Izhar target 2')
            ->assertSeeText('(1 Ikhfa, 1 Izhar)')
            ->assertDontSeeText('Not enough evidence');
    }

    public function test_result_displays_user_speech_transcript_separately_from_reference_text(): void
    {
        $user = User::create([
            'name' => 'Speech Transcript User',
            'email' => 'speech-transcript-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/speech-transcript.wav',
            'tajweed_rule' => 'ikhfa',
            'original_filename' => 'speech-transcript.wav',
            'duration_seconds' => 5,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'correct',
            'confidence_score' => 99,
            'predicted_rule' => 'other',
            'classification_status' => 'reference_verified',
            'classification_method' => 'quran_muaalem_reference_alignment',
            'processing_status' => 'completed',
            'feedback_message' => 'Correct target pronunciation.',
            'transcribed_text' => 'وَلَآ أَنتُمْ عَـٰبِدُونَ مَآ أَعْبُدُ',
            'model_predictions' => [
                'transcription' => [
                    'reference_text' => 'وَلَآ أَنتُمْ عَـٰبِدُونَ مَآ أَعْبُدُ',
                    'reference_source' => 'selected_ayah',
                    'speech_text' => 'ولا أنتم عابدون ما أعبد',
                    'speech_source' => 'browser',
                ],
            ],
            'detected_errors' => [
                [
                    'type' => 'target_analysis',
                    'targets' => [
                        [
                            'rule' => 'ikhfa',
                            'status' => 'correct',
                            'snippet' => 'أَنتُم',
                            'position' => 9,
                            'end_position' => 11,
                            'target_window_model_status' => 'loaded',
                            'target_window_confidence' => 99.4,
                            'target_window_decision_source' => 'trusted_ml',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.result', $audio))
            ->assertOk()
            ->assertSeeText('Quran reference used for target detection')
            ->assertSeeText('User speech transcript')
            ->assertSeeText('ولا أنتم عابدون ما أعبد');
    }

    public function test_history_uses_evaluated_rules_instead_of_legacy_single_rule(): void
    {
        $user = User::create([
            'name' => 'Combined History User',
            'email' => 'combined-history-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/history-combined.wav',
            'tajweed_rule' => 'ikhfa',
            'original_filename' => 'history-combined.wav',
            'duration_seconds' => 12,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'correct',
            'confidence_score' => 97,
            'predicted_rule' => 'ikhfa',
            'classification_status' => 'reference_verified',
            'classification_method' => 'quran_muaalem_reference_alignment',
            'processing_status' => 'completed',
            'feedback_message' => 'All targets aligned.',
            'detected_errors' => [[
                'type' => 'target_analysis',
                'targets' => [
                    ['rule' => 'ikhfa', 'status' => 'correct'],
                    ['rule' => 'izhar', 'status' => 'correct'],
                ],
            ]],
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.history'))
            ->assertOk()
            ->assertSeeText('Ikhfa & Izhar')
            ->assertSeeText('Correct · pronunciation 97%');
    }

    public function test_legacy_broad_uncertain_result_is_not_displayed_as_not_enough_evidence(): void
    {
        $user = User::create([
            'name' => 'Legacy Result User',
            'email' => 'legacy-result-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/legacy-uncertain.wav',
            'tajweed_rule' => 'izhar',
            'original_filename' => 'legacy-uncertain.wav',
            'duration_seconds' => 5,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'uncertain',
            'confidence_score' => 44,
            'classification_status' => 'uncertain',
            'classification_method' => 'cnn_rule_priority',
            'processing_status' => 'completed',
            'feedback_message' => 'Legacy broad-model uncertainty.',
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.result', $audio))
            ->assertOk()
            ->assertSeeText('Failed')
            ->assertSeeText('Unavailable')
            ->assertDontSeeText('Not enough evidence');
    }

    public function test_confirmed_selected_ayah_mismatch_still_displays_not_enough_evidence(): void
    {
        $user = User::create([
            'name' => 'Mismatch Result User',
            'email' => 'mismatch-result-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/mismatch.wav',
            'tajweed_rule' => 'izhar',
            'original_filename' => 'mismatch.wav',
            'duration_seconds' => 5,
        ]);
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'uncertain',
            'confidence_score' => 92,
            'classification_status' => 'selected_ayah_mismatch',
            'classification_method' => 'selected_ayah_validation',
            'processing_status' => 'completed',
            'feedback_message' => 'The recitation does not match the selected ayah.',
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.result', $audio))
            ->assertOk()
            ->assertSeeText('Not enough evidence')
            ->assertSeeText('Completed');
    }
}
