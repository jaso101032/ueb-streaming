<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Registro de la autorización de difusión de un evento. Sin una autorización
 * vigente, ni el directo, ni la grabación, ni el video del catálogo o de la
 * parrilla se muestran al público. La licencia de uso no la sustituye.
 */
class PublicationAuthorizationController extends Controller
{
    public function update(Request $request, Evento $evento)
    {
        $data = $request->validate([
            'autorizacion_responsable' => ['required', 'string', 'max:255'],
            'autorizacion_referencia' => ['required', 'string', 'max:255'],
            'autorizacion_alcance' => ['nullable', 'string', 'max:500'],
            'autorizacion_fecha' => ['required', 'date', 'before_or_equal:today'],
            'autorizacion_vigencia' => ['nullable', 'date', 'after_or_equal:autorizacion_fecha'],
        ], [
            'autorizacion_responsable.required' => 'Indica quién emitió la autorización de difusión.',
            'autorizacion_referencia.required' => 'Indica el documento que autoriza la difusión (oficio, resolución o acta).',
            'autorizacion_fecha.required' => 'Indica la fecha de la autorización.',
            'autorizacion_fecha.before_or_equal' => 'La fecha de la autorización no puede ser futura.',
            'autorizacion_vigencia.after_or_equal' => 'La vigencia debe ser posterior a la fecha de la autorización.',
        ]);
        $before = $evento->only(array_keys($data));
        $evento->update($data);
        Audit::record('evento.autorizacion', $evento, ['antes' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $before),
            'despues' => $data, 'estado' => $evento->fresh()->authorizationStatus()]);

        return back()->with('success', $evento->fresh()->hasValidAuthorization()
            ? 'Autorización de difusión registrada. El evento ya puede mostrarse según su visibilidad.'
            : 'Autorización registrada, pero no está vigente: el evento sigue visible solo para el personal.');
    }

    public function destroy(Request $request, Evento $evento)
    {
        $evento->update(['autorizacion_responsable' => null, 'autorizacion_referencia' => $evento->licencia === 'otra' ? $evento->autorizacion_referencia : null,
            'autorizacion_alcance' => null, 'autorizacion_fecha' => null, 'autorizacion_vigencia' => null]);
        Audit::record('evento.autorizacion_revocada', $evento);

        return back()->with('success', 'Autorización revocada. El evento ya no se muestra al público.');
    }
}
