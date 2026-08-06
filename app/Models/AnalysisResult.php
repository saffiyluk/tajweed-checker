<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisResult extends Model
{
    use HasFactory;

    // Columns that Laravel is allowed to fill using AnalysisResult::create() or update().
    // This protects other columns from being changed accidentally through request data.
    protected $fillable = [
        'audio_id',
        'correctness',
        'predicted_rule',
        'classification_status',
        'classification_method',
        'class_probabilities',
        'model_predictions',
        'confidence_score',
        'feedback_message',
        'transcribed_text',
        'detected_errors',
        'suggestions',
        'processing_status',
        'prediction_feedback',
        'transcription_feedback',
        'corrected_rule',
        'corrected_transcription',
        'correction_note',
        'correction_review_status',
        'correction_admin_note',
        'expert_target_label',
        'correction_submitted_by',
        'correction_reviewed_by',
        'correction_submitted_at',
        'correction_reviewed_at',
    ];

    // Convert database values into useful PHP types when this model is read.
    // Example: detected_errors is stored as JSON, but used as an array in PHP.
    protected $casts = [
        'detected_errors' => 'array',
        'suggestions' => 'array',
        'class_probabilities' => 'array',
        'model_predictions' => 'array',
        'confidence_score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'correction_submitted_at' => 'datetime',
        'correction_reviewed_at' => 'datetime',
    ];

    /**
     * The audio recording that produced this analysis result.
     */
    public function audioRecitation()
    {
        return $this->belongsTo(AudioRecitation::class, 'audio_id');
    }

    /**
     * Convenience check for views/controllers that only care whether processing finished.
     */
    public function isComplete()
    {
        return $this->processing_status === 'completed';
    }

    /**
     * Convenience check for displaying correct/incorrect result states.
     */
    public function isCorrect()
    {
        return $this->correctness === 'correct';
    }

    /**
     * Return the outcome state that is safe to show in reports and status badges.
     *
     * Historical rows may still contain correctness="uncertain" after a model
     * failure or an old low-confidence decision. Those rows must not be shown as
     * an input-validation result under the current policy.
     */
    public function displayOutcomeKey(): string
    {
        $processingStatus = strtolower(trim((string) $this->processing_status));

        if ($processingStatus === 'failed') {
            return 'analysis_failed';
        }

        if ($processingStatus === 'processing') {
            return 'processing';
        }

        if ($processingStatus === 'pending') {
            return 'pending';
        }

        if ($processingStatus !== 'completed') {
            return 'unavailable';
        }

        if ($this->correctness === 'correct' || $this->correctness === 'incorrect') {
            return $this->correctness;
        }

        if ($this->isInputValidationUncertain()) {
            return 'uncertain';
        }

        return 'unavailable';
    }

    public function displayOutcomeLabel(): string
    {
        return match ($this->displayOutcomeKey()) {
            'correct' => 'Correct',
            'incorrect' => 'Needs Practice',
            'uncertain' => 'Not Enough Evidence',
            'analysis_failed' => 'Analysis Failed',
            'processing' => 'Processing',
            'pending' => 'Pending',
            default => 'Unavailable',
        };
    }

    /**
     * Uncertainty is valid only for the two input conditions supported by the
     * product policy: no recitation/silence, or a confirmed selected-ayah
     * mismatch. Classification fields are authoritative for new rows, while
     * structured evidence keeps compatible historical rows recognizable.
     */
    public function isInputValidationUncertain(): bool
    {
        if (strtolower(trim((string) $this->processing_status)) !== 'completed'
            || $this->correctness !== 'uncertain') {
            return false;
        }

        $classificationStatus = strtolower(trim((string) $this->classification_status));
        if (in_array($classificationStatus, ['no_recitation', 'selected_ayah_mismatch'], true)) {
            return true;
        }

        $classificationMethod = strtolower(trim((string) $this->classification_method));
        if (in_array($classificationMethod, ['audio_activity_gate', 'selected_ayah_validation'], true)) {
            return true;
        }

        $modelPredictions = is_array($this->model_predictions) ? $this->model_predictions : [];
        if (data_get($modelPredictions, 'pronunciation.content_mismatch') === true
            || data_get($modelPredictions, 'transcription.selected_ayah_match.status') === 'mismatch') {
            return true;
        }

        foreach ((array) $this->detected_errors as $evidence) {
            if (! is_array($evidence)) {
                continue;
            }

            $type = strtolower((string) ($evidence['type'] ?? ''));
            if ($type === 'selected_ayah_mismatch') {
                return true;
            }

            if ($type === 'audio_input_issue'
                && $this->containsNoRecitationEvidence($evidence)) {
                return true;
            }

            if ($this->containsInputValidationTargetEvidence($evidence)) {
                return true;
            }
        }

        return false;
    }

    private function containsNoRecitationEvidence(array $evidence): bool
    {
        $issueType = strtolower((string) data_get($evidence, 'audio_input_issue.type', ''));
        $activityStatus = strtolower((string) data_get($evidence, 'audio_input_issue.audio_activity_status', ''));

        return $issueType === 'silent_audio'
            || data_get($evidence, 'audio_input_issue.is_silent') === true
            || in_array($activityStatus, ['silent', 'no_speech', 'no_recitation'], true);
    }

    private function containsInputValidationTargetEvidence(array $evidence): bool
    {
        $targets = ($evidence['type'] ?? null) === 'target_analysis'
            ? ($evidence['targets'] ?? [])
            : [$evidence['target'] ?? $evidence];

        foreach ((array) $targets as $target) {
            if (! is_array($target)) {
                continue;
            }

            if (in_array(
                (string) ($target['target_window_decision_source'] ?? ''),
                ['no_recitation_input_gate', 'selected_ayah_mismatch_input_gate'],
                true
            ) || data_get($target, 'target_window_quality.content_mismatch') === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Short alias used by places that call AnalysisResult::with('audio').
     */
    public function audio()
    {
        return $this->belongsTo(AudioRecitation::class, 'audio_id');
    }

    /**
     * User who submitted the correction.
     */
    public function correctionSubmitter()
    {
        return $this->belongsTo(User::class, 'correction_submitted_by');
    }

    /**
     * Admin who reviewed the correction.
     */
    public function correctionReviewer()
    {
        return $this->belongsTo(User::class, 'correction_reviewed_by');
    }
}
