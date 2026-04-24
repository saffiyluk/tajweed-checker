<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'audio_id',
        'correctness',
        'confidence_score',
        'feedback_message',
        'detected_errors',
        'suggestions',
        'processing_status',
    ];

    protected $casts = [
        'detected_errors' => 'array',
        'suggestions' => 'array',
        'confidence_score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Result belongs to an audio recitation
     */
    public function audioRecitation()
    {
        return $this->belongsTo(AudioRecitation::class, 'audio_id');
    }

    /**
     * Check if analysis is complete
     */
    public function isComplete()
    {
        return $this->processing_status === 'completed';
    }

    /**
     * Check if analysis is correct
     */
    public function isCorrect()
    {
        return $this->correctness === 'correct';
    }

    public function audio()
    {
        return $this->belongsTo(AudioRecitation::class, 'audio_id');
    }
}
