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
    ];

    protected $casts = [
        'activo' => 'boolean',
        'iniciado_en' => 'datetime',
        'finalizado_en' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}