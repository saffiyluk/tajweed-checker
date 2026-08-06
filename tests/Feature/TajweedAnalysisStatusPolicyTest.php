<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\AudioRecitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TajweedAnalysisStatusPolicyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_broad_uncertain_row_is_exposed_as_failed_not_uncertain(): void
    {
        [$user, $audio] = $this->recording();
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'uncertain',
            'confidence_score' => 41,
            'processing_status' => 'completed',
            'classification_status' => 'uncertain',
            'classification_method' => 'cnn_rule_priority',
            'feedback_message' => 'The old broad model was inconclusive.',
        ]);

        $this->actingAs($user)
            ->getJson(route('tajweed.analysis-status', $audio))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('correctness', null)
            ->assertJsonPath('classification_status', 'failed');
    }

    public function test_explicit_no_recitation_row_remains_uncertain(): void
    {
        [$user, $audio] = $this->recording();
        AnalysisResult::create([
            'audio_id' => $audio->id,
            'correctness' => 'uncertain',
            'confidence_score' => 0,
            'processing_status' => 'completed',
            'classification_status' => 'no_recitation',
            'classification_method' => 'audio_activity_gate',
            'feedback_message' => 'No recitation was detected.',
        ]);

        $this->actingAs($user)
            ->getJson(route('tajweed.analysis-status', $audio))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('correctness', 'uncertain')
            ->assertJsonPath('classification_status', 'no_recitation');
    }

    private function recording(): array
    {
        $user = User::create([
            'name' => 'Status Policy User',
            'email' => 'status-policy-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/status-policy.wav',
            'tajweed_rule' => 'izhar',
            'original_filename' => 'status-policy.wav',
            'duration_seconds' => 5,
        ]);

        return [$user, $audio];
    }
}
