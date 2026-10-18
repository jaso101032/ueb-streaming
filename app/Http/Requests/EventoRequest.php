<?php

namespace App\Http\Requests;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('evento') instanceof Evento && $this->route('evento')->scheduledVideo) return false;
        return (bool) $this->user()?->allowsOperatorAction('events');
    }

    protected function prepareForValidation(): void
    {
        $evento = $this->route('evento');

        if ($evento instanceof Evento) {
            // Una petición parcial no debe borrar datos que no fueron enviados.
            foreach (['descripcion', 'fecha_fin', 'lugar', 'facultad_id', 'carrera_id', 'video_url', 'estado'] as $field) {
                if (! $this->exists($field)) {
                    $this->merge([$field => $evento->getAttribute($field)]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:20000'],
            'tipo_evento' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'facultad_id' => ['nullable', 'integer', 'exists:facultades,id'],
            'carrera_id' => ['nullable', 'integer', Rule::exists('carreras', 'id')->where('facultad_id', $this->input('facultad_id'))],
            'estado' => ['sometimes', 'required', Rule::in(['programado', 'en_vivo', 'finalizado', 'cancelado'])],
            'video_url' => ['nullable', 'url:http,https', 'max:255'],
            'recording_fps_limit' => ['sometimes', 'nullable', 'integer', Rule::in([30, 60])],
            'chat_enabled' => ['sometimes', 'boolean'],
            'miniatura' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'Selecciona una opción válida para :attribute.',
            'exists' => 'Selecciona una opción válida para :attribute.',
            'date' => 'Ingresa una fecha válida para :attribute.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'fecha_fin.after' => 'La fecha de cierre debe ser posterior a la fecha de inicio.',
            'carrera_id.exists' => 'La carrera debe pertenecer a la facultad seleccionada.',
            'estado.in' => 'Selecciona un estado válido.',
            'video_url.url' => 'El video debe tener un enlace HTTP o HTTPS válido.',
            'recording_fps_limit.in' => 'Selecciona conservar los FPS originales, 30 o 60.',
            'miniatura.image' => 'La miniatura debe ser una imagen válida.',
            'miniatura.mimes' => 'La miniatura debe ser JPG, PNG o WebP.',
            'miniatura.max' => 'La miniatura no puede superar 2 MB.',
            'miniatura.dimensions' => 'La miniatura no puede superar 4096 × 4096 píxeles.',
            'miniatura.uploaded' => 'No se pudo subir la miniatura. Comprueba su tamaño e inténtalo de nuevo.',
        ];
    }

    public function attributes(): array
    {
        return ['titulo' => 'título', 'tipo_evento' => 'tipo de evento', 'fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de cierre', 'categoria_id' => 'categoría', 'facultad_id' => 'facultad', 'carrera_id' => 'carrera', 'video_url' => 'video grabado'];
    }
}
