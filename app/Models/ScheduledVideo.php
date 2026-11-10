<?php

namespace App\Models;

use App\Services\ChannelSchedule;
use Illuminate\Database\Eloquent\Model;

class ScheduledVideo extends Model
{
    protected $fillable = ['evento_id', 'path', 'duration', 'processing_status', 'source_path', 'source_bytes', 'optimized_bytes', 'processing_started_at', 'processing_heartbeat_at', 'processing_progress', 'ready_at', 'processing_error', 'source_sha256', 'output_sha256'];

    protected $casts = ['duration' => 'integer', 'processing_progress' => 'integer', 'processing_started_at' => 'datetime', 'processing_heartbeat_at' => 'datetime', 'ready_at' => 'datetime'];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    public function playback(): array
    {
        return app(ChannelSchedule::class)->playback($this);
    }
}
