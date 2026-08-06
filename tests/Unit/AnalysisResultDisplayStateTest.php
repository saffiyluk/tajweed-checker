<?php

namespace Tests\Unit;

use App\Models\AnalysisResult;
use Tests\TestCase;

class AnalysisResultDisplayStateTest extends TestCase
{
    public function test_silence_is_a_displayable_uncertain_outcome(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'classification_status' => 'no_recitation',
        ]);

        $this->assertTrue($analysis->isInputValidationUncertain());
        $this->assertSame('uncertain', $analysis->displayOutcomeKey());
        $this->assertSame('Not Enough Evidence', $analysis->displayOutcomeLabel());
    }

    public function test_structured_selected_ayah_mismatch_is_a_displayable_uncertain_outcome(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'classification_status' => 'confident',
            'model_predictions' => [
                'transcription' => [
                    'selected_ayah_match' => ['status' => 'mismatch'],
                ],
            ],
        ]);

        $this->assertSame('uncertain', $analysis->displayOutcomeKey());
    }

    public function test_pronunciation_content_mismatch_is_a_displayable_uncertain_outcome(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'model_predictions' => [
                'pronunciation' => ['content_mismatch' => true],
            ],
        ]);

        $this->assertSame('uncertain', $analysis->displayOutcomeKey());
    }

    public function test_legacy_low_evidence_uncertain_outcome_is_unavailable(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'completed',
            'correctness' => 'uncertain',
            'classification_status' => 'confident',
            'classification_method' => 'cnn_rule_priority',
        ]);

        $this->assertFalse($analysis->isInputValidationUncertain());
        $this->assertSame('unavailable', $analysis->displayOutcomeKey());
        $this->assertSame('Unavailable', $analysis->displayOutcomeLabel());
    }

    public function test_failed_row_wins_over_stale_uncertain_correctness(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'failed',
            'correctness' => 'uncertain',
            'classification_status' => 'no_recitation',
        ]);

        $this->assertFalse($analysis->isInputValidationUncertain());
        $this->assertSame('analysis_failed', $analysis->displayOutcomeKey());
        $this->assertSame('Analysis Failed', $analysis->displayOutcomeLabel());
    }

    public function test_completed_null_correctness_is_unavailable(): void
    {
        $analysis = $this->analysis([
            'processing_status' => 'completed',
            'correctness' => null,
        ]);

        $this->assertSame('unavailable', $analysis->displayOutcomeKey());
    }

    private function analysis(array $attributes): AnalysisResult
    {
        return new AnalysisResult($attributes);
    }
}
