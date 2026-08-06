<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\AudioRecitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TajweedMlPipelineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_real_python_pipeline_persists_a_terminal_honest_result(): void
    {
        if (!filter_var(env('RUN_ML_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Set RUN_ML_INTEGRATION_TESTS=true to run the TensorFlow integration test.');
        }

        $python = base_path('.venv/Scripts/python.exe');

        if (!is_file($python)) {
            $python = (string) config('tajweed.python_binary', 'python');
        }

        config([
            'tajweed.python_binary' => $python,
            'tajweed.use_firebase_storage' => false,
            'tajweed.enable_firestore_sync' => false,
            'tajweed.enable_ai_feedback' => false,
            'tajweed.enable_diacritization' => false,
            'tajweed.enable_quran_matching' => false,
            'tajweed.enable_rule_based_analysis' => true,
        ]);

        Storage::fake('public');
        $wavPath = $this->makeToneWav();

        try {
            $user = User::create([
                'name' => 'ML Integration User',
                'email' => 'ml-integration-'.uniqid().'@example.test',
                'password' => 'a-secure-test-password',
                'role' => 'user',
            ]);

            $response = $this->actingAs($user)->post(route('tajweed.upload'), [
                'audio' => new UploadedFile($wavPath, 'tone.wav', 'audio/wav', null, true),
                'tajweed_rule' => 'ikhfa',
                'selected_ayah' => "مِنْ قَبْلِ",
            ]);

            $audio = AudioRecitation::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
            $analysis = AnalysisResult::query()->where('audio_id', $audio->id)->firstOrFail();

            $response->assertRedirect(route('tajweed.result', $audio));
            $this->assertContains($analysis->processing_status, ['completed', 'failed']);
            $this->assertNotSame('pending', $analysis->processing_status);

            if ($analysis->processing_status === 'completed') {
                $this->assertContains($analysis->predicted_rule, ['ikhfa', 'izhar', 'other']);
                $this->assertContains($analysis->correctness, ['correct', 'incorrect', 'uncertain']);
                if ($analysis->correctness === 'uncertain') {
                    $this->assertTrue($analysis->isInputValidationUncertain());
                }
                $this->assertIsArray($analysis->class_probabilities);
            } else {
                $this->assertNull($analysis->correctness);
                $this->assertSame('failed', $analysis->classification_status);
            }

            $reanalyzeResponse = $this->actingAs($user)
                ->post(route('tajweed.reanalyze', $audio));

            $reanalyzeResponse->assertRedirect(route('tajweed.result', $audio));
            $analysis->refresh();
            $this->assertContains($analysis->processing_status, ['completed', 'failed']);
            $this->assertNotSame('pending', $analysis->processing_status);
        } finally {
            @unlink($wavPath);
        }
    }

    private function makeToneWav(): string
    {
        $sampleRate = 16000;
        $sampleCount = (int) ($sampleRate * 1.2);
        $pcm = '';

        for ($index = 0; $index < $sampleCount; $index++) {
            $sample = (int) round(sin(2 * M_PI * 440 * $index / $sampleRate) * 10000);
            $pcm .= pack('v', $sample & 0xffff);
        }

        $dataLength = strlen($pcm);
        $header = 'RIFF'.pack('V', 36 + $dataLength).'WAVEfmt ';
        $header .= pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
        $header .= 'data'.pack('V', $dataLength);
        $path = tempnam(storage_path('app'), 'tajweed_ml_');
        file_put_contents($path, $header.$pcm);

        return $path;
    }
}
