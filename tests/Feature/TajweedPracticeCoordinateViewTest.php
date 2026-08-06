<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TajweedPracticeCoordinateViewTest extends TestCase
{
    public function test_dedicated_recording_pages_preserve_selected_quran_coordinates(): void
    {
        $user = new User([
            'name' => 'Reciter',
            'email' => 'reciter@example.test',
            'role' => 'user',
        ]);
        $user->id = 42;

        $this->actingAs($user);

        foreach (['tajweed.ikhfa-haqiqi', 'tajweed.izhar-halqi'] as $routeName) {
            $response = $this->get(route($routeName, [
                'ayah' => 'مِنْ قَبْلِ',
                'surah' => 2,
                'ayah_number' => 255,
            ]));

            $response
                ->assertOk()
                ->assertSee("sourceSurahInput.name = 'source_surah';", false)
                ->assertSee('sourceSurahInput.value = 2;', false)
                ->assertSee("sourceAyahInput.name = 'source_ayah';", false)
                ->assertSee('sourceAyahInput.value = 255;', false);
        }
    }
}
