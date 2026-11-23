<?php

namespace App\Models;

use App\Services\ChannelSchedule;
use Illuminate\Database\Eloquent\Model;

class ScheduledVideo extends Model
{
    protected $fillable = ['evento_id', 'on_channel', 'path', 'duration', 'processing_status', 'source_path', 'source_bytes', 'optimized_bytes', 'processing_started_at', 'processing_heartbeat_at', 'processing_progress', 'ready_at', 'processing_error', 'source_sha256', 'output_sha256', 'subtitles_path',
        'subtitles_source', 'subtitles_status', 'subtitles_progress', 'subtitles_error', 'subtitles_attempts', 'subtitles_attempt_at', 'renditions_path', 'renditions_status', 'renditions_progress', 'renditions_error', 'renditions_attempts', 'renditions_attempt_at', 'renditions_levels'];

    protected $casts = ['on_channel' => 'boolean', 'duration' => 'integer', 'processing_progress' => 'integer', 'processing_started_at' => 'datetime', 'processing_heartbeat_at' => 'datetime', 'ready_at' => 'datetime',
        'subtitles_progress' => 'integer', 'subtitles_attempts' => 'integer', 'subtitles_attempt_at' => 'datetime',
        'renditions_progress' => 'integer', 'renditions_attempts' => 'integer', 'renditions_attempt_at' => 'datetime', 'renditions_levels' => 'array'];

    /** Video subido solo para el catálogo (no se emite en el canal 24/7). */
    public function isCatalogOnly(): bool
    {
        return $this->on_channel === false;
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function playback(): array
    {
        return app(ChannelSchedule::class)->playback($this);
    }
}
