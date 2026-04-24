<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuranController extends Controller
{
    public function showSurah($surah = 1)
    {
        // Fetch the current surah
        $response = Http::get("https://api.alquran.cloud/v1/surah/{$surah}/ar.alafasy");
        $data = $response->successful() ? $response->json()['data'] : null;

        // Fetch all surahs for the dropdown
        $allSurahsResponse = Http::get("https://api.alquran.cloud/v1/surah");
        $allSurahs = $allSurahsResponse->successful() ? $allSurahsResponse->json()['data'] : [];

        return view('reciteQuran', [
            'surah' => $data,
            'currentSurah' => $surah,
            'allSurahs' => $allSurahs
        ]);
    }
}
