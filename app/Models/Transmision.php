<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'playback_id', 'publicacion', 'publicacion_revisada_por', 'publicacion_revisada_en', 'subtitles_path',
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
        'publicacion_revisada_en' => 'datetime',
    ];

    protected $attributes = ['publicacion' => 'pendiente'];

    protected static function booted(): void
    {
        // El identificador público nunca deriva de la clave privada de OBS.
        static::creating(function (self $stream) {
            $stream->playback_id ??= self::newPlaybackId();
        });
    }

    public static function newPlaybackId(): string
    {
        return Str::lower(Str::random(32));
    }

    /** Dirección HLS pública, en el mismo origen del sitio y sin la clave de emisión. */
    public function playbackUrl(): ?string
    {
        return $this->playback_id ? '/directo/'.$this->playback_id.'.m3u8' : null;
    }

    public function isPublished(): bool
    {
        return $this->publicacion === 'aprobada';
    }

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

    public function revisor()
    {
        return $this->belongsTo(User::class, 'publicacion_revisada_por');
    }
}
