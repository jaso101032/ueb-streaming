<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Facultad;
use App\Models\Carrera;
use Illuminate\Support\Str;

class FacultadCarreraSeeder extends Seeder
{
    public function run(): void
    {
        $facultadesData = [
            [
                'nombre' => 'Facultad de Jurisprudencia, Ciencias Sociales y Políticas',
                'carreras' => ['Derecho', 'Comunicación Social']
            ],
            [
                'nombre' => 'Facultad de Ciencias Agropecuarias, Recursos Naturales y del Ambiente',
                'carreras' => ['Ingeniería Agropecuaria', 'Medicina Veterinaria Forestry']
            ],
            [
                'nombre' => 'Facultad de Ciencias de la Educación, Sociales, Filosofuales y Humanísticas',
                'carreras' => ['Educación Básica', 'Pedagogía de la Actividad Física y Deporte']
            ],
            [
                'nombre' => 'Facultad de Ciencias Administrativas, Gestión Empresarial e Informática',
                'carreras' => ['Ingeniería en Software', 'Administración de Empresas', 'Contabilidad y Auditoría']
            ],
        ];

        foreach ($facultadesData as $facData) {
            $facultad = Facultad::firstOrCreate(
                ['slug' => Str::slug($facData['nombre'])],
                [
                    'nombre' => $facData['nombre'],
                    'descripcion' => 'Facultad perteneciente a la Universidad Estatal de Bolívar.'
                ]
            );

            foreach ($facData['carreras'] as $carName) {
                Carrera::firstOrCreate(
                    [
                        'facultad_id' => $facultad->id,
                        'slug' => Str::slug($carName)
                    ],
                    [
                        'nombre' => $carName
                    ]
                );
            }
        }
    }
}