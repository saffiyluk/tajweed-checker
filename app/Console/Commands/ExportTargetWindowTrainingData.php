<?php

namespace App\Console\Commands;

use App\Models\AnalysisResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;

class ExportTargetWindowTrainingData extends Command
{
    private const SUPPORTED_TARGET_LABELS = [
        'ikhfa_correct',
        'ikhfa_weak_ghunnah',
        'izhar_correct',
        'izhar_with_ghunnah',
        'other',
    ];

    protected $signature = 'tajweed:export-target-windows {--output=storage/app/target-window/manifest.json}';

    protected $description = 'Export corrected recitations as a manifest for target-window Tajweed training.';

    public function handle(): int
    {
        $outputPath = base_path($this->option('output'));
        $audioDirectory = dirname($outputPath) . DIRECTORY_SEPARATOR . 'audio';

        if (!is_dir($audioDirectory)) {
            mkdir($audioDirectory, 0775, true);
        }

        $rows = AnalysisResult::query()
            ->with('audio')
            ->whereNotNull('correction_submitted_at')
            ->where('correction_review_status', 'used')
            ->whereNotNull('expert_target_label')
            ->orderBy('id')
            ->get();

        $manifest = [];

        foreach ($rows as $row) {
            $audio = $row->audio;

            if (!$audio) {
                continue;
            }

            $label = $this->labelForCorrection($row);

            if ($label === null) {
                continue;
            }

            try {
                $audioBytes = $this->loadAudioBytes((string) $audio->audio_file_path);
            } catch (\Throwable $exception) {
                $this->warn("Skipping audio {$audio->id}: {$exception->getMessage()}");
                continue;
            }

            $extension = strtolower(pathinfo((string) $audio->original_filename, PATHINFO_EXTENSION)) ?: 'webm';
            $audioPath = $audioDirectory . DIRECTORY_SEPARATOR . 'audio_' . $audio->id . '.' . $extension;
            file_put_contents($audioPath, $audioBytes);

            $manifest[] = [
                'analysis_id' => $row->id,
                'audio_id' => $audio->id,
                'audio_path' => $this->relativePath($audioPath),
                'duration_seconds' => $audio->duration_seconds,
                'selected_rule' => $audio->tajweed_rule,
                'target_label' => $label,
                'corrected_rule' => $row->corrected_rule,
                'prediction_feedback' => $row->prediction_feedback,
                'transcription_feedback' => $row->transcription_feedback,
                'transcript' => trim((string) ($row->corrected_transcription ?: $row->transcribed_text)),
                'note' => $row->correction_note,
            ];
        }

        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Exported ' . count($manifest) . ' corrected recitations to ' . $this->relativePath($outputPath));

        return self::SUCCESS;
    }

    private function labelForCorrection(AnalysisResult $row): ?string
    {
        $label = trim((string) $row->expert_target_label);

        return in_array($label, self::SUPPORTED_TARGET_LABELS, true)
            ? $label
            : null;
    }

    private function loadAudioBytes(string $path): string
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        if (str_starts_with($path, 'public/')) {
            $trimmed = substr($path, 7);

            if (Storage::disk('public')->exists($trimmed)) {
                return Storage::disk('public')->get($trimmed);
            }
        }

        if (config('tajweed.use_firebase_storage', false) && str_starts_with($path, 'users/')) {
            $credentialsPath = base_path(config('firebase.credentials'));
            $bucketName = config('firebase.storage_bucket');

            if (!is_file($credentialsPath) || !$bucketName) {
                throw new \RuntimeException('Firebase storage is not configured for this audio.');
            }

            $storage = (new Factory())
                ->withServiceAccount($credentialsPath)
                ->withDefaultStorageBucket($bucketName)
                ->createStorage();

            $object = $storage->getBucket()->object($path);

            if (!$object->exists()) {
                throw new \RuntimeException('Firebase audio object not found.');
            }

            return $object->downloadAsString();
        }

        throw new \RuntimeException('Audio file is not available locally.');
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
    }
}
