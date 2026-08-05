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
        'url_transmision',
        'duracion_minutos',
        'responsable_streaming_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

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