<?php

namespace Tests\Unit;

use App\Http\Controllers\TajweedController;
use App\Models\AudioRecitation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class TajweedControllerStoredAudioTest extends TestCase
{
    public function test_reanalysis_prefers_local_audio_without_contacting_firebase(): void
    {
        Storage::fake('public');
        Http::fake();

        $path = 'tajweed/3/izhar/local-recording.webm';
        $audio = 'local audio bytes';
        Storage::disk('public')->put($path, $audio);

        $recitation = new AudioRecitation([
            'audio_file_path' => $path,
            'firebase_url' => 'https://example.invalid/slow-firebase-audio',
        ]);

        $reflection = new ReflectionClass(TajweedController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('loadStoredAudio');
        $method->setAccessible(true);

        $this->assertSame($audio, $method->invoke($controller, $recitation));
        Http::assertNothingSent();
    }

    public function test_reanalysis_resolves_a_local_storage_url_without_an_http_self_request(): void
    {
        Storage::fake('public');
        Http::fake();

        $path = 'tajweed/3/izhar/url-recording.webm';
        $audio = 'audio bytes resolved from storage URL';
        Storage::disk('public')->put($path, $audio);

        $recitation = new AudioRecitation([
            'audio_file_path' => 'legacy/missing-recording.webm',
            'firebase_url' => "http://127.0.0.1:8000/storage/{$path}",
        ]);

        $reflection = new ReflectionClass(TajweedController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('loadStoredAudio');
        $method->setAccessible(true);

        $this->assertSame($audio, $method->invoke($controller, $recitation));
        Http::assertNothingSent();
    }
}
