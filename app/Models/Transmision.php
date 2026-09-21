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
    ];

    protected $casts = [
        'activo' => 'boolean',
        'last_signal_at' => 'datetime',
        'recording_ready_at' => 'datetime',
        'recording_attempt_at' => 'datetime',
        'iniciado_en' => 'datetime',
        'finalizado_en' => 'datetime',
    ];

    public function recordingPath(): ?string
    {
        if ($this->recording_status !== 'ready' || !preg_match('/^[a-f0-9-]{36}\.mp4$/D', $this->recording_file ?? '')) return null;
        $path = rtrim(config('streaming.recordings_root'), '/').'/vod/'.$this->recording_file;
        return is_file($path) && !is_link($path) && is_readable($path) ? $path : null;
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}