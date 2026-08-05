<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Académico',
            'Investigación',
            'Cultural',
            'Deportivo',
            'Institucional',
            'Conferencias',
            'Seminarios',
            'Graduaciones',
            'Vinculación',
            'Comunidad'
        ];

        foreach ($categorias as $cat) {
            Categoria::firstOrCreate(
                ['slug' => Str::slug($cat)],
                [
                    'nombre' => $cat,
                    'descripcion' => "Contenido relacionado con la categoría institucional de {$cat}."
                ]
            );
        }
    }
}