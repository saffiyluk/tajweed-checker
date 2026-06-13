<?php

namespace App\Console\Commands;

use App\Services\QuranTranscriptionMatcher;
use Illuminate\Console\Command;

class CacheQuranAyahs extends Command
{
    protected $signature = 'quran:cache';

    protected $description = 'Cache Quran ayah text for blind transcription matching.';

    public function handle(QuranTranscriptionMatcher $matcher): int
    {
        $this->info('Fetching Quran ayahs and building local cache...');

        $count = $matcher->warmCache();

        if ($count === 0) {
            $this->error('Unable to build Quran cache. Check internet connection or Quran API availability.');

            return self::FAILURE;
        }

        $this->info("Cached {$count} ayahs successfully.");

        return self::SUCCESS;
    }
}
