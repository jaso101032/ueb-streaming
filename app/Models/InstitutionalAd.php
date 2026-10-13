<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalAd extends Model
{
    protected $fillable = ['title', 'path', 'source_path', 'duration', 'source_bytes', 'optimized_bytes',
        'processing_status', 'processing_started_at', 'ready_at', 'processing_error', 'is_active'];

    protected $casts = ['duration' => 'integer', 'is_active' => 'boolean',
        'processing_started_at' => 'datetime', 'ready_at' => 'datetime'];
}
