<?php

namespace Tests\Unit;

use App\Console\Commands\ExportTargetWindowTrainingData;
use App\Models\AnalysisResult;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExportTargetWindowTrainingDataTest extends TestCase
{
    public function test_export_label_requires_explicit_supported_expert_label(): void
    {
        $row = new AnalysisResult([
            'expert_target_label' => 'ikhfa_weak_ghunnah',
            'prediction_feedback' => 'correct',
            'corrected_rule' => 'ikhfa',
        ]);

        $this->assertSame('ikhfa_weak_ghunnah', $this->labelFor($row));
    }

    public function test_user_prediction_feedback_is_not_inferred_as_pronunciation_ground_truth(): void
    {
        $row = new AnalysisResult([
            'prediction_feedback' => 'correct',
            'corrected_rule' => 'ikhfa',
            'correction_note' => 'prediction was correct',
        ]);

        $this->assertNull($this->labelFor($row));
    }

    public function test_unsupported_expert_label_is_rejected(): void
    {
        $row = new AnalysisResult([
            'expert_target_label' => 'ikhfa_probably_ok',
        ]);

        $this->assertNull($this->labelFor($row));
    }

    private function labelFor(AnalysisResult $row): ?string
    {
        $method = new ReflectionMethod(ExportTargetWindowTrainingData::class, 'labelForCorrection');
        $method->setAccessible(true);

        return $method->invoke(new ExportTargetWindowTrainingData, $row);
    }
}
