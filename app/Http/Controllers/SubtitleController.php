<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Support\Audit;
use App\Support\Subtitles;
use Illuminate\Http\Request;

/**
 * Subtítulos de videos almacenados en la plataforma (RNF-11). Se entregan con
 * los mismos permisos que el video al que acompañan.
 */
class SubtitleController extends Controller
{
    public function upload(Request $request, Evento $evento)
    {
        $media = $this->eventMedia($evento);
        abort_unless($media, 409, 'El evento todavía no tiene un video almacenado.');
        $request->validate(Subtitles::rules(), $this->messages());
        $path = Subtitles::store($request->file('subtitles'));
        $previous = $media->subtitles_path;
        $media->update(['subtitles_path' => $path]);
        Subtitles::delete($previous);
        Audit::record($previous ? 'subtitulos.reemplazados' : 'subtitulos.agregados', $evento,
            ['archivo' => mb_substr($request->file('subtitles')->getClientOriginalName(), 0, 120)]);

        return back()->with('success', 'Subtítulos guardados. El público puede activarlos con el botón CC.');
    }

    public function remove(Evento $evento)
    {
        $media = $this->eventMedia($evento);
        abort_unless($media?->subtitles_path, 404);
        Subtitles::delete($media->subtitles_path);
        $media->update(['subtitles_path' => null]);
        Audit::record('subtitulos.eliminados', $evento);

        return back()->with('success', 'Subtítulos eliminados.');
    }

    public function show(Request $request, Evento $evento)
    {
        abort_unless($evento->isVisibleTo($request->user()) && $evento->estado !== 'cancelado', 404);
        if ($scheduled = $evento->scheduledVideo) {
            abort_unless($scheduled->processing_status === 'ready', 404);

            return Subtitles::response($scheduled->subtitles_path);
        }
        $stream = $evento->transmision;
        $staff = in_array($request->user()?->role, ['admin', 'operador'], true);
        abort_unless($evento->estado === 'finalizado' && $stream?->recordingPath() && ($stream->isPublished() || $staff), 404);

        return Subtitles::response($stream->subtitles_path);
    }

    public function uploadAd(Request $request, InstitutionalAd $ad)
    {
        abort_unless($ad->processing_status === 'ready', 409, 'La publicidad todavía se está preparando.');
        $request->validate(Subtitles::rules(), $this->messages());
        $path = Subtitles::store($request->file('subtitles'));
        $previous = $ad->subtitles_path;
        $ad->update(['subtitles_path' => $path]);
        Subtitles::delete($previous);
        Audit::record($previous ? 'subtitulos.reemplazados' : 'subtitulos.agregados', $ad);

        return back()->with('success', 'Subtítulos de la publicidad guardados.');
    }

    public function removeAd(InstitutionalAd $ad)
    {
        abort_unless($ad->subtitles_path, 404);
        Subtitles::delete($ad->subtitles_path);
        $ad->update(['subtitles_path' => null]);
        Audit::record('subtitulos.eliminados', $ad);

        return back()->with('success', 'Subtítulos de la publicidad eliminados.');
    }

    public function showAd(InstitutionalAd $ad)
    {
        abort_unless($ad->is_active && $ad->processing_status === 'ready', 404);

        return Subtitles::response($ad->subtitles_path);
    }

    /** The stored video of the event: its scheduled program or its ready recording. */
    private function eventMedia(Evento $evento)
    {
        if ($evento->scheduledVideo) return $evento->scheduledVideo->processing_status === 'ready' ? $evento->scheduledVideo : null;

        return $evento->transmision?->recordingPath() ? $evento->transmision : null;
    }

    private function messages(): array
    {
        return ['subtitles.required' => 'Selecciona un archivo de subtítulos.',
            'subtitles.max' => 'El archivo de subtítulos no puede superar 1 MB.',
            'subtitles.extensions' => 'Usa un archivo .vtt o .srt.'];
    }
}
