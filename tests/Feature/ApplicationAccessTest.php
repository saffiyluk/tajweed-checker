<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuranController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationAccessTest extends TestCase
{
    public function test_progress_report_route_requires_authentication(): void
    {
        $route = Route::getRoutes()->getByName('report.generate');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->get('/pdf/report/999999')->assertRedirect(route('login'));
    }

    public function test_quran_routes_use_the_existing_surah_action(): void
    {
        $this->assertSame(
            QuranController::class.'@showSurah',
            Route::getRoutes()->getByName('quran.list')->getActionName()
        );
        $this->assertSame(
            QuranController::class.'@showSurah',
            Route::getRoutes()->getByName('quran.surah')->getActionName()
        );
    }

    public function test_quran_route_renders_with_api_data(): void
    {
        Http::fake([
            'quranapi.pages.dev/api/1.json' => Http::response([
                'surahNo' => 1,
                'surahNameArabic' => 'الفاتحة',
                'surahName' => 'Al-Fatiha',
                'surahNameTranslation' => 'The Opening',
                'revelationPlace' => 'Mecca',
                'totalAyah' => 1,
                'arabic1' => ['بسم الله الرحمن الرحيم'],
                'english' => ['In the name of Allah'],
            ]),
            'quranapi.pages.dev/api/surah.json' => Http::response([
                [
                    'surahNo' => 1,
                    'surahNameArabic' => 'الفاتحة',
                    'surahName' => 'Al-Fatiha',
                    'surahNameTranslation' => 'The Opening',
                    'revelationPlace' => 'Mecca',
                    'totalAyah' => 7,
                ],
            ]),
        ]);

        $this->get('/quran/1')->assertOk()->assertSee('Al-Fatiha');
    }

    public function test_profile_uses_mysql_data_when_firebase_is_not_configured(): void
    {
        config()->set('firebase.credentials', null);
        config()->set('firebase.storage_bucket', null);

        $user = $this->user(10);
        $this->actingAs($user);

        $view = app(ProfileController::class)->show($user);

        $this->assertSame($user, $view->getData()['user']);
        $this->assertSame('Owner', $view->getData()['userData']['name']);
        $this->assertSame('owner@example.test', $view->getData()['userData']['email']);
    }

    public function test_profile_controller_rejects_a_different_route_user(): void
    {
        config()->set('firebase.credentials', null);
        config()->set('firebase.storage_bucket', null);

        $this->actingAs($this->user(10));
        $this->expectException(AuthorizationException::class);

        app(ProfileController::class)->show($this->user(11));
    }

    public function test_memorization_transcription_is_authenticated_and_rate_limited(): void
    {
        $middleware = Route::getRoutes()->getByName('memorize.transcribe')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('throttle:10,1', $middleware);
    }

    private function user(int $id): User
    {
        $user = new User([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'role' => 'user',
        ]);
        $user->id = $id;

        return $user;
    }
}
