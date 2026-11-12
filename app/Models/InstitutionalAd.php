<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalAd extends Model
{
    protected $fillable = ['title', 'path', 'source_path', 'duration', 'source_bytes', 'optimized_bytes',
        'processing_status', 'processing_started_at', 'processing_heartbeat_at', 'processing_progress', 'ready_at', 'processing_error', 'is_active', 'source_sha256', 'output_sha256', 'subtitles_path',
        'subtitles_source', 'subtitles_status', 'subtitles_progress', 'subtitles_error', 'subtitles_attempts', 'subtitles_attempt_at'];

    protected $casts = ['duration' => 'integer', 'processing_progress' => 'integer', 'is_active' => 'boolean',
        'processing_started_at' => 'datetime', 'processing_heartbeat_at' => 'datetime', 'ready_at' => 'datetime',
        'subtitles_progress' => 'integer', 'subtitles_attempts' => 'integer', 'subtitles_attempt_at' => 'datetime'];
}
