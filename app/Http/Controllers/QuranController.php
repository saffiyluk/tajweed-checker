<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class QuranController extends Controller
{
    private function getSurahData(int $surah): array
    {
        $data = null;
        $allSurahs = [];

        try {
            $response = Http::timeout(15)
                ->retry(2, 500)
                ->get("https://quranapi.pages.dev/api/{$surah}.json");

            if ($response->successful()) {
                $apiData = $response->json();

                $arabicAyahs = $apiData['arabic1']
                    ?? $apiData['arabic']
                    ?? [];

                $translationAyahs = $apiData['english']
                    ?? $apiData['translation']
                    ?? $apiData['translationEnglish']
                    ?? $apiData['englishTranslation']
                    ?? [];

                if (!is_array($arabicAyahs)) {
                    $arabicAyahs = [];
                }

                if (!is_array($translationAyahs)) {
                    $translationAyahs = [];
                }

                $getValue = function ($array, $index) {
                    return $array[$index]
                        ?? $array[$index + 1]
                        ?? $array[(string) $index]
                        ?? $array[(string) ($index + 1)]
                        ?? null;
                };

                $data = [
                    'number' => $apiData['surahNo'] ?? $surah,
                    'name' => $apiData['surahNameArabic'] ?? '',
                    'englishName' => $apiData['surahName'] ?? '',
                    'englishNameTranslation' => $apiData['surahNameTranslation'] ?? '',
                    'revelationType' => $apiData['revelationPlace'] ?? '',
                    'numberOfAyahs' => $apiData['totalAyah'] ?? count($arabicAyahs),

                    'ayahs' => collect($arabicAyahs)->values()->map(function ($text, $index) use ($translationAyahs, $getValue) {
                        return [
                            'numberInSurah' => $index + 1,
                            'text' => $text,
                            'translation' => strip_tags($getValue($translationAyahs, $index) ?? ''),
                        ];
                    })->toArray(),
                ];
            }

            $allSurahsResponse = Http::timeout(15)
                ->retry(2, 500)
                ->get("https://quranapi.pages.dev/api/surah.json");

            if ($allSurahsResponse->successful()) {
                $allSurahsData = $allSurahsResponse->json();

                $allSurahs = collect($allSurahsData)->values()->map(function ($item, $index) {
                    return [
                        'number' => $item['surahNo'] ?? ($index + 1),
                        'name' => $item['surahNameArabic'] ?? '',
                        'englishName' => $item['surahName'] ?? '',
                        'englishNameTranslation' => $item['surahNameTranslation'] ?? '',
                        'revelationType' => $item['revelationPlace'] ?? '',
                        'numberOfAyahs' => $item['totalAyah'] ?? 0,
                    ];
                })->toArray();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Quran API', [
                'surah' => $surah,
                'message' => $e->getMessage(),
            ]);
        }

        return [$data, $allSurahs];
    }

    public function showSurah(Request $request, $surah = 1)
    {
        $surah = max(1, min(114, (int) $surah));

        [$data, $allSurahs] = $this->getSurahData($surah);

        $ayahs = $data['ayahs'] ?? [];
        $ayahsPerPage = 8;
        $totalAyahs = count($ayahs);
        $totalPages = max(1, (int) ceil($totalAyahs / $ayahsPerPage));

        $selectedAyah = max(1, min($totalAyahs ?: 1, (int) $request->query('ayah', 1)));
        $requestedPage = $request->query('page');

        $currentPage = $requestedPage
            ? max(1, min($totalPages, (int) $requestedPage))
            : (int) ceil($selectedAyah / $ayahsPerPage);

        $pageStart = $totalAyahs > 0
            ? (($currentPage - 1) * $ayahsPerPage) + 1
            : 0;

        $pageEnd = min($totalAyahs, $currentPage * $ayahsPerPage);

        $pagedAyahs = array_slice(
            $ayahs,
            ($currentPage - 1) * $ayahsPerPage,
            $ayahsPerPage
        );

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
            'apiError' => $data === null,
        ]);
    }

    public function memorizeSurah(Request $request, $surah = 1)
    {
        $surah = max(1, min(114, (int) $surah));
        [$data, $allSurahs] = $this->getSurahData($surah);

        $ayahs = $data['ayahs'] ?? [];
        $selectedAyah = max(1, min(count($ayahs) ?: 1, (int) $request->query('ayah', 1)));

        return view('memorizeQuran', [
            'surah' => $data,
            'currentSurah' => $surah,
            'allSurahs' => $allSurahs,
            'ayahs' => $ayahs,
            'selectedAyah' => $selectedAyah,
            'totalAyahs' => count($ayahs),
            'apiError' => $data === null,
        ]);
    }

    public function transcribeMemorization(Request $request)
    {
        $validated = $request->validate([
            'audio' => 'required|file|max:51200',
        ]);

        $audioPath = $validated['audio']->getPathname();
        $pythonBinary = config('tajweed.python_binary', 'python');
        $scriptPath = base_path('python/transcribe.py');

        if (!is_file($scriptPath)) {
            return response()->json([
                'status' => 'failed',
                'error' => 'Transcription script not found.',
            ], 500);
        }

        $process = new Process([$pythonBinary, $scriptPath, $audioPath]);
        $process->setTimeout((int) config('tajweed.transcription_timeout', 180));
        $process->setEnv($this->pythonProcessEnvironment());

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return response()->json([
                'status' => 'timeout',
                'error' => 'Transcription took too long. Please record a shorter chunk.',
            ], 504);
        }

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        $json = $this->extractJsonObject($output);
        $result = $json ? json_decode($json, true) : null;

        if (!is_array($result)) {
            Log::warning('Memorization transcription returned invalid output', [
                'output' => $output,
            ]);

            return response()->json([
                'status' => 'failed',
                'error' => 'Unable to read transcription output.',
            ], 500);
        }

        $result['raw_text'] = $result['raw_text'] ?? ($result['text'] ?? '');
        $result['text'] = $this->keepArabicText((string) ($result['text'] ?? ''));
        $result['status'] = $result['text'] !== '' ? 'success' : 'empty';

        return response()->json($result);
    }

    private function keepArabicText(string $text): string
    {
        preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\s]+/u', $text, $matches);

        $text = collect($matches[0] ?? [])
            ->map(fn ($chunk) => trim($chunk))
            ->filter()
            ->implode(' ');

        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        if (!preg_match('/[\x{0621}-\x{064A}]/u', $text)) {
            return '';
        }

        return trim($text);
    }

    private function extractJsonObject(string $output): ?string
    {
        $start = strpos($output, '{');
        $end = strrpos($output, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return substr($output, $start, $end - $start + 1);
    }

    private function pythonProcessEnvironment(): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
        $path = getenv('PATH') ?: getenv('Path') ?: '';
        $temp = getenv('TEMP') ?: sys_get_temp_dir();
        $tmp = getenv('TMP') ?: $temp;
        $pythonHome = storage_path('app/python-home');
        $appData = $pythonHome . DIRECTORY_SEPARATOR . 'AppData';
        $localAppData = $appData . DIRECTORY_SEPARATOR . 'Local';
        $roamingAppData = $appData . DIRECTORY_SEPARATOR . 'Roaming';

        foreach ([$pythonHome, $localAppData, $roamingAppData] as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }

        $windowsPythonHome = str_replace('/', '\\', $pythonHome);
        $drive = preg_match('/^[A-Za-z]:/', $windowsPythonHome) ? substr($windowsPythonHome, 0, 2) : 'C:';
        $homePath = preg_match('/^[A-Za-z]:(.*)$/', $windowsPythonHome, $matches) ? $matches[1] : $windowsPythonHome;

        return [
            'PATH' => $path,
            'Path' => $path,
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => getenv('WINDIR') ?: $systemRoot,
            'HOME' => $pythonHome,
            'USERPROFILE' => $pythonHome,
            'HOMEDRIVE' => $drive,
            'HOMEPATH' => $homePath,
            'APPDATA' => $roamingAppData,
            'LOCALAPPDATA' => $localAppData,
            'KERAS_HOME' => $pythonHome . DIRECTORY_SEPARATOR . '.keras',
            'TEMP' => $temp,
            'TMP' => $tmp,
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
            'TF_CPP_MIN_LOG_LEVEL' => '3',
            'TF_ENABLE_ONEDNN_OPTS' => '0',
            'WHISPER_MODEL' => config('tajweed.memorization_whisper_model', config('tajweed.whisper_model', 'tiny')),
        ];
    }
}
