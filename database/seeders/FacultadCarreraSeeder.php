<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Facultad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacultadCarreraSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo contrastado con https://ueb.edu.ec/pregrado el 19/09/2026.
        // Las modalidades de una misma carrera se agrupan porque el modelo no distingue modalidad.
        $facultadesData = [
            [
                'nombre' => 'Facultad de Ciencias Administrativas, Gestión Empresarial e Informática',
                'carreras' => ['Administración de Empresas', 'Comunicación', 'Contabilidad y Auditoría', 'Emprendimiento e Innovación Social', 'Marketing Digital', 'Mercadotecnia', 'Software', 'Tecnologías de la Información', 'Turismo y Hotelería'],
            ],
            [
                'nombre' => 'Facultad de Jurisprudencia, Ciencias Sociales y Políticas',
                'carreras' => ['Derecho', 'Sociología'],
            ],
            [
                'nombre' => 'Facultad de Ciencias de la Educación, Sociales, Filosóficas y Humanísticas',
                'carreras' => ['Educación Básica', 'Educación Inicial', 'Educación Intercultural Bilingüe', 'Pedagogía de los Idiomas Nacionales y Extranjeros', 'Pedagogía de la Matemática y la Física', 'Pedagogía de las Ciencias Experimentales – Informática'],
            ],
            [
                'nombre' => 'Facultad de Ciencias Agropecuarias, Recursos Naturales y del Ambiente',
                'carreras' => ['Agroindustrias', 'Agronomía', 'Medicina Veterinaria'],
            ],
            [
                'nombre' => 'Facultad de Ciencias de la Salud y del Ser Humano',
                'carreras' => ['Enfermería', 'Ingeniería en Riesgos de Desastres', 'Psicología', 'Terapia Física'],
            ],
        ];

        foreach ($facultadesData as $facData) {
            $facultad = Facultad::firstOrCreate(
                ['slug' => Str::slug($facData['nombre'])],
                [
                    'nombre' => $facData['nombre'],
                    'descripcion' => 'Facultad perteneciente a la Universidad Estatal de Bolívar.',
                ]
            );

            foreach ($facData['carreras'] as $carName) {
                Carrera::firstOrCreate(
                    [
                        'facultad_id' => $facultad->id,
                        'slug' => Str::slug($carName),
                    ],
                    [
                        'nombre' => $carName,
                    ]
                );
            }
        }
    }
}
