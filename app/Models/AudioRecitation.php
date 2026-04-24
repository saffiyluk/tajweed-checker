<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioRecitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'audio_file_path',
        'tajweed_rule',
        'original_filename',
        'duration_seconds',
        'firebase_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Audio belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Audio has one analysis result
     */
    public function analysisResult()
    {
        return $this->hasOne(AnalysisResult::class, 'audio_id');
    }

    /**
     * Get the rule label
     */
    public function getRuleLabel()
    {
        return match($this->tajweed_rule) {
            'ikhfa' => 'Ikhfa\' Haqiqi',
            'izhar' => 'Izhar Halqi',
            default => 'Unknown',
        };
    }
}
