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
        'requiere_aprobacion',
        'licencia',
        'autorizacion_referencia',
        'url_transmision',
        'video_url',
        'duracion_minutos',
        'responsable_streaming_id',
        'chat_enabled',
    ];

    protected $attributes = ['visibilidad' => 'publico', 'requiere_aprobacion' => false, 'licencia' => 'institucional'];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'chat_enabled' => 'boolean',
        'requiere_aprobacion' => 'boolean',
    ];

    /**
     * Licencias de uso. "help" se muestra a quien crea el evento para que elija
     * la opción correcta; "short" es lo que ve el público en la ficha.
     */
    public const LICENSES = [
        'institucional' => ['name' => 'Uso institucional (todos los derechos reservados)', 'short' => 'Uso institucional · Todos los derechos reservados',
            'help' => 'El video solo se ve en UEBTV. Nadie puede descargarlo, copiarlo ni volver a publicarlo sin permiso de la UEB. Elige esta opción si no estás seguro.'],
        'cc-by' => ['name' => 'Creative Commons BY 4.0 (compartir citando a la UEB)', 'short' => 'Creative Commons BY 4.0',
            'help' => 'Cualquier persona puede compartir y reutilizar el video, incluso con fines comerciales, siempre que cite a la Universidad Estatal de Bolívar. Úsala solo si la UEB y los participantes lo autorizaron.'],
        'cc-by-nc' => ['name' => 'Creative Commons BY-NC 4.0 (compartir sin fines comerciales)', 'short' => 'Creative Commons BY-NC 4.0',
            'help' => 'Cualquier persona puede compartir y reutilizar el video citando a la UEB, pero no puede usarlo con fines comerciales. Adecuada para clases y conferencias abiertas, si los participantes lo autorizaron.'],
        'otra' => ['name' => 'Otra licencia o condición especial', 'short' => 'Condición especial de uso',
            'help' => 'Existe un acuerdo particular, por ejemplo con un invitado externo o un medio de comunicación. Describe la condición o el documento en «Referencia de autorización».'],
    ];

    public const VISIBILITIES = ['publico' => 'Público', 'interno' => 'Interno (con sesión)', 'restringido' => 'Restringido (personal UEBTV)'];

    /** Indica si la persona puede ver este evento según su clasificación. */
    public function isVisibleTo(?User $user): bool
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
