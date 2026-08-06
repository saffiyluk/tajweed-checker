<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;
class CheckTajweedMl extends Command
{
    private const CLASSES = ['ikhfa', 'izhar', 'other'];
    private const ARTIFACTS = [
        'prediction script' => 'python/predict.py',
        'CNN model' => 'python/tajweed_model.keras',
        'Random Forest model' => 'python/feature_model.pkl',
        'label encoder' => 'python/label_encoder.pkl',
        'CNN metrics' => 'python/cnn_model_metrics.json',
        'Random Forest metrics' => 'python/feature_model_metrics.json',
    ];
    protected $signature = 'tajweed:ml-health
                            {--sample= : Optional audio path to run through python/predict.py}';
    protected $description = 'Check the Tajweed rule classifier and reference-aware pronunciation readiness.';
    private int $failures = 0;
    private int $warnings = 0;
    private array $probe = [];
    public function handle(): int
    {
        $python = trim((string) config('tajweed.python_binary', 'python'));
        $this->line('<fg=cyan;options=bold>Tajweed ML release/readiness check</>');
        $this->line('General rule classification is required; target-window correctness is a separate capability.');
        $this->heading('Required general classifier');
        $pythonReady = $this->checkPython($python);
        $artifactsReady = $this->checkArtifacts();
        $this->checkMetrics('CNN', 'python/cnn_model_metrics.json', 77.24);
        $this->checkMetrics('Random Forest', 'python/feature_model_metrics.json', 78.29);
        if ($pythonReady && $artifactsReady) {
            $this->probeArtifacts($python);
        }
        $this->heading('Quran-trained transcription');
        $this->checkTranscriptionModel($python, $pythonReady);
        $this->heading('Reference-aware pronunciation correctness');
        $this->checkTargetWindow($python, $pythonReady);
        $sample = trim((string) $this->option('sample'));
        if ($sample !== '') {
            $this->heading('Optional sample inference');
            $this->checkSample($python, $pythonReady, $sample);
        }
        $this->heading('Summary');
        if ($this->failures > 0) {
            $this->line("<error>NOT READY</error> {$this->failures} required general classifier/runtime check(s) failed.");
            return self::FAILURE;
        }
        $this->line('<info>READY</info> Required general Ikhfa / Izhar / Other classifier passed.');
        if ($this->warnings > 0) {
            $this->line("<comment>{$this->warnings} warning(s)</comment> do not change the general-classifier exit code.");
        }
        return self::SUCCESS;
    }
    private function checkPython(string $python): bool
    {
        if ($python === '') {
            $this->recordFailure('Configured Python executable is empty.');
            return false;
        }
        try {
            $process = $this->runProcess([$python, '--version'], 15);
        } catch (Throwable $exception) {
            $this->recordFailure("Cannot run configured Python '{$python}': {$exception->getMessage()}");
            return false;
        }
        $version = trim($process->getOutput().' '.$process->getErrorOutput());
        if (!$process->isSuccessful() || !preg_match('/Python\s+\d+\.\d+(?:\.\d+)?/i', $version, $match)) {
            $this->recordFailure("Configured executable '{$python}' did not report a usable Python version.");
            return false;
        }
        $this->pass("{$python} ({$match[0]})");
        return true;
    }
    private function checkArtifacts(): bool
    {
        $ready = true;
        foreach (self::ARTIFACTS as $label => $relative) {
            $path = base_path($relative);
            if (!is_file($path) || !is_readable($path) || filesize($path) < 1) {
                $this->recordFailure("Missing, unreadable, or empty {$label}: {$relative}");
                $ready = false;
            } else {
                $this->pass("{$label}: {$relative}");
            }
        }
        return $ready;
    }
    private function checkMetrics(string $label, string $relative, float $baseline): void
    {
        $path = base_path($relative);
        if (!is_file($path)) {
            return;
        }
        try {
            $metrics = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->recordFailure("{$label} metrics JSON is invalid: {$exception->getMessage()}");
            return;
        }
        $classes = $this->classes($metrics['classes'] ?? null);
        $accuracy = $metrics['accuracy'] ?? null;
        $testSize = $metrics['test_size'] ?? null;
        if ($classes !== self::CLASSES) {
            $this->recordFailure("{$label} classes must be ordered: ".implode(', ', self::CLASSES));
        }
        if (!is_numeric($accuracy) || round((float) $accuracy * 100, 2) < $baseline) {
            $actual = is_numeric($accuracy) ? number_format((float) $accuracy * 100, 2).'%' : 'invalid';
            $this->recordFailure("{$label} accuracy {$actual} is below report baseline {$baseline}%.");
        }
        if ($testSize !== 479) {
            $this->recordFailure("{$label} test_size must match the report's 479 samples.");
        }
        if ($classes === self::CLASSES && is_numeric($accuracy) && round((float) $accuracy * 100, 2) >= $baseline && $testSize === 479) {
            $this->pass(sprintf('%s metrics: %.2f%% accuracy, 479 samples, classes [%s].', $label, (float) $accuracy * 100, implode(', ', $classes)));
        }
    }
    private function probeArtifacts(string $python): void
    {
        $script = <<<'PYTHON'
import json, os, pickle, sys
root = os.path.abspath(sys.argv[1])
sys.path.insert(0, root)
import numpy, soundfile, sklearn, tensorflow
import audio_cleaning, hybrid_features
from tensorflow.keras.models import load_model
with open(os.path.join(root, "label_encoder.pkl"), "rb") as f:
    encoder = pickle.load(f)
with open(os.path.join(root, "feature_model.pkl"), "rb") as f:
    feature = pickle.load(f)
cnn = load_model(os.path.join(root, "tajweed_model.keras"), compile=False)
shape = cnn.output_shape[0] if isinstance(cnn.output_shape, list) else cnn.output_shape
target_path = os.path.join(root, "target_window_model.pkl")
target_classes = None
if os.path.isfile(target_path):
    with open(target_path, "rb") as f:
        target_classes = [str(x).lower() for x in pickle.load(f)["classes"]]
print(json.dumps({
    "versions": [sys.version.split()[0], tensorflow.__version__, sklearn.__version__],
    "encoder_classes": [str(x).lower() for x in encoder.classes_],
    "feature_classes": [str(x).lower() for x in feature["classes"]],
    "cnn_outputs": int(shape[-1]),
    "feature_inputs": int(getattr(feature["model"], "n_features_in_", -1)),
    "target_classes": target_classes,
}))
PYTHON;
        try {
            $process = $this->runProcess([$python, '-c', $script, base_path('python')], max(90, (int) config('tajweed.prediction_timeout', 60)));
        } catch (Throwable $exception) {
            $this->recordFailure('Python dependency/artifact probe failed to start: '.$exception->getMessage());
            return;
        }
        $payload = $this->json($process->getOutput());
        if (!$process->isSuccessful() || $payload === null) {
            $this->recordFailure('Python dependencies or model artifacts could not be loaded: '.$this->processError($process));
            return;
        }
        $this->probe = $payload;
        if ($this->classes($payload['encoder_classes'] ?? null) !== self::CLASSES
            || $this->classes($payload['feature_classes'] ?? null) !== self::CLASSES
            || ($payload['cnn_outputs'] ?? null) !== 3
            || ($payload['feature_inputs'] ?? null) !== 91) {
            $this->recordFailure('Loaded model shapes/classes do not match the three-class, 91-feature contract.');
            return;
        }
        $versions = implode(', ', array_map('strval', $payload['versions'] ?? []));
        $this->pass("Python dependencies and model artifacts loaded ({$versions}).");
    }

    private function checkTranscriptionModel(string $python, bool $pythonReady): void
    {
        if (!config('tajweed.enable_transcription', true)) {
            $this->line('<comment>SKIP</comment> Tajweed transcription is disabled.');

            return;
        }

        $configured = trim((string) config('tajweed.whisper_model', ''));

        if ($configured === '') {
            $this->recordWarning('WHISPER_MODEL is empty; blind transcription will fall back poorly.');

            return;
        }

        $model = str_starts_with($configured, 'hf:') ? substr($configured, 3) : $configured;
        $isRemoteModelId = str_starts_with($configured, 'hf:')
            || preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $model) === 1;
        $modelPath = $this->resolveLocalPath($model);
        $looksLikePath = !$isRemoteModelId && $this->looksLikePath($model);

        if ($looksLikePath && !is_dir($modelPath)) {
            $this->recordWarning("Configured WHISPER_MODEL path is missing: {$configured}");

            return;
        }

        if (!is_dir($modelPath)) {
            $this->recordWarning("Generic or remote Whisper model configured ({$configured}); the bundled Tarteel Quran model is recommended for recitation transcription.");

            return;
        }

        $requiredFiles = ['config.json', 'preprocessor_config.json', 'tokenizer_config.json', 'vocab.json'];
        $missing = collect($requiredFiles)
            ->reject(fn (string $name): bool => is_file(rtrim($modelPath, '\\/').DIRECTORY_SEPARATOR.$name))
            ->values()
            ->all();
        $hasWeights = collect(['model.safetensors', 'pytorch_model.bin'])
            ->contains(fn (string $name): bool => is_file(rtrim($modelPath, '\\/').DIRECTORY_SEPARATOR.$name)
                && filesize(rtrim($modelPath, '\\/').DIRECTORY_SEPARATOR.$name) > 1024);

        if ($missing !== [] || !$hasWeights) {
            $details = $missing !== [] ? 'missing '.implode(', ', $missing) : 'missing model weights';
            $this->recordWarning("Configured Quran transcription model is incomplete ({$details}).");

            return;
        }

        if (!$pythonReady) {
            $this->line('<comment>SKIP</comment> Python runtime is unavailable.');

            return;
        }

        $script = <<<'PYTHON'
import json, sys, time
started = time.time()
from transformers import WhisperForConditionalGeneration, WhisperProcessor
processor = WhisperProcessor.from_pretrained(sys.argv[1])
model = WhisperForConditionalGeneration.from_pretrained(sys.argv[1], low_cpu_mem_usage=True)
print(json.dumps({
    "ready": True,
    "model_type": getattr(model.config, "model_type", ""),
    "vocab_size": int(getattr(model.config, "vocab_size", 0)),
    "seconds": round(time.time() - started, 2),
}))
PYTHON;

        try {
            $process = $this->runProcess([$python, '-c', $script, $modelPath], 90);
            $payload = $this->json($process->getOutput());
        } catch (Throwable $exception) {
            $this->recordWarning('Quran transcription model probe failed to start: '.$exception->getMessage());

            return;
        }

        if (!$process->isSuccessful() || !($payload['ready'] ?? false)) {
            $this->recordWarning('Quran transcription model could not be loaded: '.$this->processError($process));

            return;
        }

        $this->line(sprintf(
            '<info>READY</info> Quran-trained Whisper transcription model loaded (%s, vocab %d, %.2fs).',
            (string) ($payload['model_type'] ?? 'unknown'),
            (int) ($payload['vocab_size'] ?? 0),
            (float) ($payload['seconds'] ?? 0)
        ));
    }

    private function checkTargetWindow(string $python, bool $pythonReady): void
    {
        if (config('tajweed.enable_quran_pronunciation_model', true)) {
            $script = base_path('python/predict_quran_pronunciation.py');
            $configuredModel = trim((string) config('tajweed.quran_pronunciation_model', ''));
            $modelDirectory = $configuredModel !== ''
                ? (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configuredModel) ? $configuredModel : base_path($configuredModel))
                : base_path('python/models/muaalem-model-v3_2');
            $requiredFiles = ['config.json', 'vocab.json', 'preprocessor_config.json'];
            $weights = collect(['model.safetensors', 'pytorch_model.bin'])
                ->map(fn (string $name): string => rtrim($modelDirectory, '\\/').DIRECTORY_SEPARATOR.$name)
                ->first(fn (string $path): bool => is_file($path) && filesize($path) > 1024);
            $artifactReady = is_file($script)
                && is_dir($modelDirectory)
                && collect($requiredFiles)->every(fn (string $name): bool => is_file(rtrim($modelDirectory, '\\/').DIRECTORY_SEPARATOR.$name))
                && is_string($weights);

            if ($artifactReady && $pythonReady) {
                $probe = <<<'PYTHON'
import json, sys
sys.path.insert(0, sys.argv[1])
import quran_muaalem
import quran_transcript
from predict_quran_pronunciation import canonicalize_uthmani_reference_text, _reference_phonetization
from quran_muaalem.modeling.multi_level_tokenizer import MultiLevelTokenizer

# Simplified Quran API text containing a waqf sign plus Izhar and Ikhfa
# tanween contexts.  This catches the exact compatibility failure that a
# package-import-only health check previously missed.
text = (
    "\u062d\u064e\u0642\u0651\u064b\u0627 \u06da \u0644\u0651\u064e\u0647\u064f\u0645\u0652 "
    "\u062f\u064e\u0631\u064e\u062c\u064e\u0627\u062a\u064c \u0639\u0650\u0646\u062f\u064e "
    "\u0648\u064e\u0631\u0650\u0632\u0652\u0642\u064c \u0643\u064e\u0631\u0650\u064a\u0645\u064c"
)
canonical, normalization = canonicalize_uthmani_reference_text(text)
reference = _reference_phonetization(text, canonical)
tokenizer = MultiLevelTokenizer(sys.argv[2])
encoded = tokenizer.tokenize(
    [reference.phonemes],
    [reference.sifat],
    to_dict=True,
    return_tensors="pt",
    padding="longest",
)["input_ids"]
print(json.dumps({
    "ready": True,
    "levels": sorted(encoded.keys()),
    "phoneme_tokens": int(encoded["phonemes"].shape[-1]),
    "removed_annotations": len(normalization["removed_annotations"]),
    "inserted_context_markers": len(normalization["inserted_context_markers"]),
}))
PYTHON;

                try {
                    $process = $this->runProcess([
                        $python,
                        '-c',
                        $probe,
                        base_path('python'),
                        $modelDirectory,
                    ], 45);
                    $payload = $this->json($process->getOutput());

                    if ($process->isSuccessful() && ($payload['ready'] ?? false) === true) {
                        $sizeGb = filesize($weights) / 1_000_000_000;
                        $this->line(sprintf(
                            '<info>READY</info> Quran Muaalem reference model installed (%.2f GB, offline local inference).',
                            $sizeGb
                        ));
                        $this->line(sprintf(
                            '  Representative mixed-rule Quran reference tokenized (%d phoneme tokens; %d annotation removed; %d context markers restored).',
                            (int) ($payload['phoneme_tokens'] ?? 0),
                            (int) ($payload['removed_annotations'] ?? 0),
                            (int) ($payload['inserted_context_markers'] ?? 0)
                        ));
                        $this->line('  Decisive Correct/Incorrect results still require the configured confidence, content, and target-alignment gates.');

                        return;
                    }

                    $this->recordWarning('Quran Muaalem packages could not be imported: '.$this->processError($process));
                } catch (Throwable $exception) {
                    $this->recordWarning('Quran Muaalem dependency probe failed: '.$exception->getMessage());
                }
            } else {
                $this->recordWarning('Quran Muaalem script or complete local model artifact is unavailable.');
            }
        }

        $script = base_path('python/predict_target_windows.py');
        $model = base_path('python/target_window_model.pkl');
        $metrics = base_path('python/target_window_model_metrics.json');

        if (!is_file($script) || !is_file($model)) {
            $this->recordWarning('Trained target-window model is unavailable; heuristics are not trusted correctness evidence.');
            $this->line('<comment>NOT READY</comment> Analysis must fail unless another trusted analyzer completes after the selected ayah is verified.');

            if (!is_file($metrics)) {
                $this->recordWarning('Target-window evaluation metrics are unavailable.');
            }

            return;
        }

        if (!is_file($metrics)) {
            $this->recordWarning('Target-window model exists but has no evaluation metrics.');
            $this->line('<comment>NOT READY</comment> Do not claim decisive pronunciation correctness.');

            return;
        }

        try {
            $data = json_decode((string) file_get_contents($metrics), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->recordWarning('Target-window metrics are invalid: '.$exception->getMessage());
            $this->line('<comment>NOT READY</comment> Do not claim decisive pronunciation correctness.');

            return;
        }

        $metricClasses = $this->classes($data['classes'] ?? null);
        $modelClasses = $this->classes($this->probe['target_classes'] ?? null);
        $accuracy = $data['accuracy'] ?? null;
        $testSize = $data['test_size'] ?? null;

        if ($modelClasses === [] || $metricClasses !== $modelClasses || !is_numeric($accuracy) || !is_int($testSize) || $testSize < 1) {
            $this->recordWarning('Target-window model and metrics do not provide matching, evaluated class evidence.');
            $this->line('<comment>NOT READY</comment> Do not claim decisive pronunciation correctness.');

            return;
        }

        $this->line(sprintf('<info>READY</info> Evaluated target-window model: %.2f%% on %d samples.', (float) $accuracy * 100, $testSize));
    }

    private function checkSample(string $python, bool $pythonReady, string $sample): void
    {
        if (!$pythonReady) {
            $this->line('<comment>SKIP</comment> Python runtime is unavailable.');

            return;
        }

        $path = is_file($sample) ? $sample : base_path($sample);

        if (!is_file($path) || !is_readable($path)) {
            $this->recordWarning("Optional sample is missing or unreadable: {$sample}");

            return;
        }

        try {
            $process = $this->runProcess([$python, base_path('python/predict.py'), realpath($path)], max(15, (int) config('tajweed.prediction_timeout', 60)));
        } catch (ProcessTimedOutException $exception) {
            $this->recordFailure("Sample inference timed out after {$exception->getExceededTimeout()} seconds.");

            return;
        } catch (Throwable $exception) {
            $this->recordFailure('Sample inference could not start: '.$exception->getMessage());

            return;
        }

        $payload = $this->json($process->getOutput());

        if (!$process->isSuccessful() || $payload === null) {
            $this->recordFailure('predict.py did not return valid JSON: '.$this->processError($process));

            return;
        }

        if (isset($payload['error'])) {
            $this->recordWarning('Supplied sample was rejected: '.$payload['error']);

            return;
        }

        $valid = in_array($payload['prediction'] ?? null, self::CLASSES, true)
            && is_numeric($payload['confidence'] ?? null)
            && (float) $payload['confidence'] >= 0
            && (float) $payload['confidence'] <= 1
            && is_numeric($payload['margin'] ?? null)
            && is_array($payload['probabilities'] ?? null)
            && in_array($payload['status'] ?? null, ['confident', 'uncertain', 'unrelated'], true)
            && is_string($payload['method'] ?? null)
            && is_array($payload['quality'] ?? null);

        if (!$valid) {
            $this->recordFailure('Prediction JSON does not match the required inference schema.');

            return;
        }

        $this->pass(sprintf('Schema valid: %s, %.2f%%, %s, %s.', $payload['prediction'], (float) $payload['confidence'] * 100, $payload['status'], $payload['method']));
        $this->line('  This validates general classification only, not pronunciation correctness.');
    }

    private function runProcess(array $command, int $timeout): Process
    {
        $process = new Process($command, base_path(), [
            'PYTHONDONTWRITEBYTECODE' => '1',
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
            'TF_CPP_MIN_LOG_LEVEL' => '3',
            'TF_ENABLE_ONEDNN_OPTS' => '0',
        ]);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }

    private function json(string $output): ?array
    {
        foreach (array_reverse(preg_split('/\R/u', trim($output)) ?: []) as $line) {
            try {
                $value = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);

                if (is_array($value)) {
                    return $value;
                }
            } catch (JsonException) {
                continue;
            }
        }

        return null;
    }

    private function classes(mixed $classes): array
    {
        return is_array($classes)
            ? array_values(array_map(static fn ($class) => strtolower((string) $class), $classes))
            : [];
    }

    private function looksLikePath(string $value): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/|\.)/', $value) === 1
            || str_contains($value, '\\')
            || str_contains($value, '/');
    }

    private function resolveLocalPath(string $value): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $value) === 1
            ? $value
            : base_path($value);
    }

    private function processError(Process $process): string
    {
        $output = trim($process->getErrorOutput()) ?: trim($process->getOutput());

        return mb_substr(preg_replace('/\s+/u', ' ', $output) ?: 'no process output', 0, 400);
    }

    private function heading(string $title): void
    {
        $this->newLine();
        $this->line("<fg=yellow;options=bold>{$title}</>");
    }

    private function pass(string $message): void
    {
        $this->line("  <info>PASS</info> {$message}");
    }

    private function recordWarning(string $message): void
    {
        $this->warnings++;
        $this->line("  <comment>WARN</comment> {$message}");
    }

    private function recordFailure(string $message): void
    {
        $this->failures++;
        $this->line("  <error>FAIL</error> {$message}");
    }
}
