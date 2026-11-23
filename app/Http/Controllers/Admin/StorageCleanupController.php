<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StorageCleanup;
use Illuminate\Http\Request;

class StorageCleanupController extends Controller
{
    /** Lista de lo que se puede eliminar (se pide al abrir la ventana). */
    public function index(StorageCleanup $cleanup)
    {
        return response()->json(['items' => $cleanup->candidates(), 'viewsDays' => StorageCleanup::VIEWS_DAYS,
            'storage' => \App\Support\StorageUsage::recordings()], 200, ['Cache-Control' => 'no-store']);
    }

    /** Borra lo que el administrador eligió en "Liberar espacio". */
    public function store(Request $request, StorageCleanup $cleanup)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['string', 'max:60', 'regex:/^(event|ad):\d+$/'],
        ], ['items.required' => 'Elige al menos un elemento para eliminar.']);

        $result = $cleanup->apply($data['items']);
        if ($result['done'] === 0) {
            return back(303)->withErrors(['items' => 'No se eliminó nada: lo elegido ya no está disponible o está en uso. Actualiza la página e inténtalo de nuevo.']);
        }
        $message = ($result['done'] === 1 ? 'Se eliminó 1 elemento' : 'Se eliminaron '.$result['done'].' elementos').' y se liberaron '.StorageCleanup::human($result['freed']).'.';
        if ($result['skipped'] > 0) $message .= ' '.$result['skipped'].' elemento(s) se omitieron porque ahora están en uso.';

        return to_route('admin.media.index', [], 303)->with('success', $message);
    }
}
