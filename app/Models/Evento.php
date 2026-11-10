<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'eventos';

    protected $fillable = [
        'titulo',
        'slug',
        'descripcion',
        'tipo_evento',
        'fecha_inicio',
        'fecha_fin',
        'lugar',
        'organizador_id',
        'facultad_id',
        'carrera_id',
        'categoria_id',
        'imagen_principal',
        'banner',
        'miniatura',
        'estado',
        'visibilidad',
        'url_transmision',
        'video_url',
        'duracion_minutos',
        'responsable_streaming_id',
        'chat_enabled',
    ];

    protected $attributes = ['visibilidad' => 'publico'];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'chat_enabled' => 'boolean',
    ];

    public const VISIBILITIES = ['publico' => 'Público', 'interno' => 'Interno (con sesión)', 'restringido' => 'Restringido (personal UEBTV)'];

    /** Indica si la persona puede ver este evento según su clasificación. */
    public function visibleTo(?User $user): bool
    {
        return match ($this->visibilidad ?? 'publico') {
            'publico' => true,
            'interno' => $user !== null,
            default => in_array($user?->role, ['admin', 'operador'], true),
        };
    }

    public function scopeVisibleTo($query, ?User $user)
    {
        if (in_array($user?->role, ['admin', 'operador'], true)) return $query;

        return $query->whereIn($query->qualifyColumn('visibilidad'), $user ? ['publico', 'interno'] : ['publico']);
    }

    public function scheduledVideo()
    {
        return $this->hasOne(ScheduledVideo::class);
    }

    public function organizador()
    {
        return $this->belongsTo(User::class, 'organizador_id');
    }

    public function responsableStreaming()
    {
        return $this->belongsTo(User::class, 'responsable_streaming_id');
    }

    public function facultad()
    {
        return $this->belongsTo(Facultad::class);
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function transmision()
    {
        return $this->hasOne(Transmision::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    // Scopes útiles para filtros rápidos
    public function scopeEnVivo($query)
    {
        return $query->where('estado', 'en_vivo');
    }

    public function scopeProgramados($query)
    {
        return $query->where('estado', 'programado');
    }
}
