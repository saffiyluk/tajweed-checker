<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuranController extends Controller
{
    public function showSurah(Request $request, $surah = 1)
    {
        $surah = max(1, min(114, (int) $surah));

        // Fetch the current surah
        $response = Http::get("https://api.alquran.cloud/v1/surah/{$surah}/ar.alafasy");
        $data = $response->successful() ? $response->json()['data'] : null;

        // Fetch all surahs for the dropdown
        $allSurahsResponse = Http::get("https://api.alquran.cloud/v1/surah");
        $allSurahs = $allSurahsResponse->successful() ? $allSurahsResponse->json()['data'] : [];

        $ayahs = $data['ayahs'] ?? [];
        $ayahsPerPage = 8;
        $totalAyahs = count($ayahs);
        $totalPages = max(1, (int) ceil($totalAyahs / $ayahsPerPage));
        $selectedAyah = max(1, min($totalAyahs ?: 1, (int) $request->query('ayah', 1)));
        $requestedPage = $request->query('page');
        $currentPage = $requestedPage
            ? max(1, min($totalPages, (int) $requestedPage))
            : (int) ceil($selectedAyah / $ayahsPerPage);
        $pageStart = (($currentPage - 1) * $ayahsPerPage) + 1;
        $pageEnd = min($totalAyahs, $currentPage * $ayahsPerPage);
        $pagedAyahs = array_slice($ayahs, ($currentPage - 1) * $ayahsPerPage, $ayahsPerPage);

        return view('reciteQuran', [
            'surah' => $data,
            'currentSurah' => $surah,
            'allSurahs' => $allSurahs,
            'pagedAyahs' => $pagedAyahs,
            'ayahsPerPage' => $ayahsPerPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'pageStart' => $pageStart,
            'pageEnd' => $pageEnd,
            'selectedAyah' => $selectedAyah,
            'totalAyahs' => $totalAyahs,
        ]);
    }
}
