<?php

namespace App\Models;

use App\Services\ChannelSchedule;
use Illuminate\Database\Eloquent\Model;

class ScheduledVideo extends Model
{
    protected $fillable = ['evento_id', 'path', 'duration', 'processing_status', 'source_path', 'source_bytes', 'optimized_bytes', 'processing_started_at', 'processing_heartbeat_at', 'processing_progress', 'ready_at', 'processing_error', 'source_sha256', 'output_sha256', 'subtitles_path',
        'subtitles_source', 'subtitles_status', 'subtitles_progress', 'subtitles_error', 'subtitles_attempts', 'subtitles_attempt_at'];

    protected $casts = ['duration' => 'integer', 'processing_progress' => 'integer', 'processing_started_at' => 'datetime', 'processing_heartbeat_at' => 'datetime', 'ready_at' => 'datetime',
        'subtitles_progress' => 'integer', 'subtitles_attempts' => 'integer', 'subtitles_attempt_at' => 'datetime'];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function playback(): array
    {
        return app(ChannelSchedule::class)->playback($this);
    }
}
