<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuranTranscriptionMatcher
{
    private const CACHE_PATH = 'app/quran/ayahs.json';

    public function match(?string $rawText): ?array
    {
        $normalizedInput = $this->normalizeArabic((string) $rawText);

        if (mb_strlen($normalizedInput) < 8) {
            return null;
        }

        $ayahs = $this->loadAyahCorpus();

        if (empty($ayahs)) {
            return null;
        }

        $best = null;
        $secondBestScore = 0.0;

        foreach ($ayahs as $ayah) {
            $score = $this->score($normalizedInput, $ayah['normalized']);

            if (!$best || $score > $best['score']) {
                $secondBestScore = $best['score'] ?? 0.0;
                $best = $ayah + ['score' => $score];
            } elseif ($score > $secondBestScore) {
                $secondBestScore = $score;
            }
        }

        if (!$best) {
            return null;
        }

        $threshold = (float) config('tajweed.quran_match_threshold', 72);
        $margin = $best['score'] - $secondBestScore;

        if ($best['score'] < $threshold || $margin < 2.5) {
            return null;
        }

        return $best + [
            'margin' => round($margin, 2),
        ];
    }

    public function warmCache(): int
    {
        $path = storage_path(self::CACHE_PATH);
        $ayahs = $this->buildAyahCorpus($path);

        return count($ayahs);
    }

    private function loadAyahCorpus(): array
    {
        $path = storage_path(self::CACHE_PATH);

        if (File::exists($path)) {
            $cached = json_decode(File::get($path), true);

            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        return $this->buildAyahCorpus($path);
    }

    private function buildAyahCorpus(string $path): array
    {
        $ayahs = [];

        try {
            for ($surah = 1; $surah <= 114; $surah++) {
                $response = Http::timeout(12)
                    ->retry(2, 250)
                    ->get("https://api.alquran.cloud/v1/surah/{$surah}/ar.alafasy");

                if (!$response->successful()) {
                    Log::warning('Quran ayah corpus fetch failed', [
                        'surah' => $surah,
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $data = $response->json('data');

                foreach (($data['ayahs'] ?? []) as $ayah) {
                    $text = trim((string) ($ayah['text'] ?? ''));

                    if ($text === '') {
                        continue;
                    }

                    $ayahs[] = [
                        'surah' => (int) ($data['number'] ?? $surah),
                        'surah_name' => (string) ($data['englishName'] ?? ''),
                        'ayah' => (int) ($ayah['numberInSurah'] ?? 0),
                        'text' => $text,
                        'normalized' => $this->normalizeArabic($text),
                    ];
                }
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($ayahs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Quran ayah corpus build failed: ' . $e->getMessage());

            return [];
        }

        return $ayahs;
    }

    private function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
        $text = str_replace(['ـ', 'ٰ'], '', $text);
        $text = strtr($text, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ؤ' => 'ء',
            'ئ' => 'ء',
            'ى' => 'ي',
            'ة' => 'ه',
        ]);
        $text = preg_replace('/[^\x{0621}-\x{064A}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function score(string $input, string $ayah): float
    {
        if ($input === '' || $ayah === '') {
            return 0.0;
        }

        similar_text($input, $ayah, $similarity);

        $inputWords = collect(explode(' ', $input))->filter()->unique();
        $ayahWords = collect(explode(' ', $ayah))->filter()->unique();
        $overlap = $inputWords->intersect($ayahWords)->count();
        $wordScore = $inputWords->count() > 0
            ? ($overlap / max(1, $inputWords->count())) * 100
            : 0;

        $containsBonus = Str::contains($ayah, $input) || Str::contains($input, $ayah) ? 8 : 0;

        return min(100, round(($similarity * 0.72) + ($wordScore * 0.28) + $containsBonus, 2));
    }
}
