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
