<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    use HasFactory;

    protected $table = 'carreras';

    protected $fillable = [
        'facultad_id',
        'nombre',
        'slug',
    ];

    public function facultad()
    {
        return $this->belongsTo(Facultad::class);
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class);
    }
}