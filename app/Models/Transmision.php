<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transmision extends Model
{
    use HasFactory;

    protected $table = 'transmisiones';

    protected $fillable = [
        'evento_id',
        'stream_key',
        'rtmp_server',
        'hls_url',
        'espectadores_activos',
        'activo',
        'iniciado_en',
        'finalizado_en',
        'recording_status', 'publisher_id', 'last_signal_at', 'recording_file',
        'recording_ready_at', 'recording_attempt_at', 'recording_attempts', 'recording_error',
        'recording_fps_limit', 'recording_metadata',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'last_signal_at' => 'datetime',
        'recording_ready_at' => 'datetime',
        'recording_attempt_at' => 'datetime',
        'iniciado_en' => 'datetime',
        'finalizado_en' => 'datetime',
        'recording_fps_limit' => 'integer',
        'recording_metadata' => 'array',
    ];

    public function recordingPath(): ?string
    {
        if ($this->recording_status !== 'ready' || !preg_match('/^[a-f0-9-]{36}\.mp4$/D', $this->recording_file ?? '')) return null;
        $path = rtrim(config('streaming.recordings_root'), '/').'/vod/'.$this->recording_file;
        return is_file($path) && !is_link($path) && is_readable($path) ? $path : null;
    }

    public function displayRecordingMetadata(): ?array
    {
        $metadata = $this->recording_metadata ?? [];
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $this->stream_key ?? '')) {
            $raw = rtrim(config('streaming.recordings_root'), '/').'/raw/'.$this->stream_key.'.flv';
            if (is_file($raw) && ! is_link($raw)) {
                clearstatcache(true, $raw);
                $bytes = @filesize($raw);
                if (is_int($bytes)) $metadata['rawBytes'] = $bytes;
                $metadata['rawPresent'] = true;
            }
        }
        $mp4 = $this->recordingPath();
        if ($mp4) {
            clearstatcache(true, $mp4);
            $bytes = @filesize($mp4);
            if (is_int($bytes)) $metadata['mp4Bytes'] = $bytes;
        }

        if ($metadata) $metadata['rawPresent'] = $metadata['rawPresent'] ?? false;

        return $metadata ?: null;
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}
