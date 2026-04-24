<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioMetadata extends Model
{
    use HasFactory;
    protected $table = 'analysis_results';

    protected $fillable = [
        'user_id',
        'file_name',
        'feedback_message',
        'correctness',
    ];
}
