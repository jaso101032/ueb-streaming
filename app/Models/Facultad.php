<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    use HasFactory;

    protected $table = 'facultades';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
    ];

    public function carreras()
    {
        return $this->hasMany(Carrera::class);
    }

    public function departamentos()
    {
        return $this->hasMany(Departamento::class);
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class);
    }
}