<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\AudioRecitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TajweedDisplayStateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_home_and_history_use_policy_safe_outcome_states(): void
    {
        $user = User::create([
            'name' => 'Display State User',
            'email' => 'display-state-'.uniqid().'@example.test',
            'password' => 'a-secure-test-password',
            'role' => 'user',
        ]);

        $failed = $this->createAnalysis($user, 'failed.wav', [
            'processing_status' => 'failed',
            'correctness' => 'uncertain',
            'classification_status' => 'no_recitation',
        ]);
        $unavailable = $this->createAnalysis($user, 'legacy-uncertain.wav', [
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'classification_status' => 'confident',
            'classification_method' => 'cnn_rule_priority',
        ]);
        $this->createAnalysis($user, 'no-recitation.wav', [
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'classification_status' => 'no_recitation',
            'classification_method' => 'audio_activity_gate',
        ]);

        $this->actingAs($user)
            ->get(route('tajweed.history'))
            ->assertOk()
            ->assertSee('data-status="analysis_failed"', false)
            ->assertSee('data-status="unavailable"', false)
            ->assertSee('data-status="uncertain"', false)
            ->assertSee(route('tajweed.result', $failed->audio_id), false)
            ->assertSee(route('tajweed.result', $unavailable->audio_id), false);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<span class="status-pill failed">Analysis Failed</span>', false)
            ->assertSee('<span class="status-pill unavailable">Unavailable</span>', false)
            ->assertSee('<span class="status-pill uncertain">Not Enough Evidence</span>', false)
            ->assertSee(route('tajweed.result', $failed->audio_id), false)
            ->assertSee(route('tajweed.result', $unavailable->audio_id), false);
    }

    private function createAnalysis(User $user, string $filename, array $attributes): AnalysisResult
    {
        $audio = AudioRecitation::create([
            'user_id' => $user->id,
            'audio_file_path' => 'tajweed/test/'.$filename,
            'tajweed_rule' => 'izhar',
            'original_filename' => $filename,
            'duration_seconds' => 5,
        ]);

        return AnalysisResult::create(array_merge([
            'audio_id' => $audio->id,
            'confidence_score' => 0,
            'feedback_message' => 'Display state fixture.',
        ], $attributes));
    }
}
