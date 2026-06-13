<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioMetadata extends Model
{
    use HasFactory;

    // This model reads/writes the same table as AnalysisResult.
    // It appears to be a simpler view of the analysis_results table for basic audio feedback data.
    protected $table = 'analysis_results';

    // Fields that can be filled in bulk, for example AudioMetadata::create([...]).
    protected $fillable = [
        'user_id',
        'file_name',
        'feedback_message',
        'correctness',
    ];
}
