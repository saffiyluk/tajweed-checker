<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /**
     * Each profile record belongs to one user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
