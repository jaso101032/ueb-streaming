<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalAd;
use App\Services\ScheduledVideoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InstitutionalAdController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'ad_title' => 'required|string|max:255',
            'ad_video' => 'required|file|mimes:mp4,mov,avi,mkv,webm,wmv,asf,mpeg,mpg,m4v|max:512000',
        ]);
        try {
            $duration = app(ScheduledVideoProcessor::class)->probe($request->file('ad_video')->getRealPath())['duration'];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['ad_video' => 'No se pudo leer el video. Usa un archivo de hasta 12 horas en un formato admitido.']);
        }
        $path = $request->file('ad_video')->store('institutional-ad-sources', 'local');
        abort_unless($path, 500, 'No se pudo guardar el anuncio.');
        try {
            InstitutionalAd::create(['title' => $data['ad_title'], 'source_path' => $path,
                'duration' => $duration, 'source_bytes' => $request->file('ad_video')->getSize(),
                'processing_status' => 'pending', 'is_active' => true]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return to_route('admin.scheduled.index')->with('success', 'Publicidad recibida. Se emitirá en los huecos cuando termine su conversión.');
    }

    public function toggle(InstitutionalAd $ad)
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        return back()->with('success', $ad->is_active ? 'Publicidad activada.' : 'Publicidad desactivada.');
    }

    public function retry(InstitutionalAd $ad)
    {
        abort_unless($ad->processing_status === 'failed' && $ad->source_path, 409);
        $ad->update(['processing_status' => 'pending', 'processing_error' => null]);

        return back()->with('success', 'Se reintentará la conversión de la publicidad.');
    }

    public function media(InstitutionalAd $ad)
    {
        abort_unless($ad->is_active && $ad->processing_status === 'ready' && $ad->path
            && Storage::disk('local')->exists($ad->path), 404);

        return response()->file(Storage::disk('local')->path($ad->path), [
            'Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
