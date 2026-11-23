<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\ScheduledVideo;
use App\Models\Transmision;
use App\Models\User;
use App\Services\ChannelSchedule;
use App\Support\ProgramGuide;
use Illuminate\Support\Facades\DB;

/**
 * Resumen del panel: qué está al aire, qué viene, qué se está procesando o
 * falló, y cuatro números claros. Sin decoración.
 */
class DashboardController extends Controller
{
    public function index(ChannelSchedule $schedule)
    {
        return view('admin.dashboard', ['props' => [
            'name' => auth()->user()->name,
            'onAir' => $this->onAir($schedule),
            'upcoming' => collect(ProgramGuide::for(auth()->user(), 8))->where('when', 'next')->take(4)->values(),
            'tasks' => $this->tasks(),
            'storage' => \App\Support\StorageUsage::recordings(),
            'metrics' => [
                ['label' => 'Eventos', 'value' => Evento::where('estado', '!=', 'cancelado')->count(), 'detail' => 'en el catálogo', 'icon' => 'calendar'],
                ['label' => 'Directos este mes', 'value' => Transmision::where('iniciado_en', '>=', now()->startOfMonth())->count(), 'detail' => now()->locale('es')->isoFormat('MMMM YYYY'), 'icon' => 'signal'],
                ['label' => 'Visualizaciones', 'value' => DB::table('event_video_views')->where('viewed_on', '>=', now()->subDays(30)->toDateString())->count(), 'detail' => 'últimos 30 días', 'icon' => 'eye'],
                ['label' => 'Usuarios', 'value' => User::count(), 'detail' => 'registrados', 'icon' => 'users'],
            ],
            'events' => Evento::with('categoria')->latest()->take(5)->get()->map(fn ($event) => [
                'id' => $event->id, 'title' => $event->titulo, 'status' => $event->estado,
                'category' => $event->categoria?->nombre ?? 'Institucional',
                'date' => $event->fecha_inicio?->format('d/m/Y · H:i'),
                'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                'url' => route('admin.eventos.show', $event),
            ])->values(),
            'links' => [
                'create' => route('admin.eventos.create'), 'events' => route('admin.eventos.index'),
                'streaming' => route('streaming.control'), 'schedule' => route('admin.scheduled.index'),
                'ads' => route('admin.scheduled.index').'#schedule-ads', 'channel' => route('channel.index'),
                'media' => route('admin.media.index'),
            ],
        ]]);
    }

    /** Lo que se emite ahora en la señal 24/7. */
    private function onAir(ChannelSchedule $schedule): array
    {
        if ($live = $schedule->live()) {
            return ['mode' => 'live', 'title' => $live->evento->titulo, 'detail' => 'Directo en curso',
                'url' => route('admin.eventos.show', $live->evento), 'since' => $live->iniciado_en?->toIso8601String()];
        }
        $active = collect($schedule->timeline())->first(fn ($i) => in_array($i['state'], ['playing', 'paused'], true));
        if ($active) {
            return ['mode' => 'scheduled', 'title' => $active['event']->titulo, 'detail' => 'Programa del canal 24/7',
                'url' => route('admin.eventos.show', $active['event']), 'endsAt' => $active['ends_at'] ?? null];
        }
        if ($ad = $schedule->advertisement()) {
            return ['mode' => 'ad', 'title' => $ad['ad']->title ?? 'Publicidad institucional', 'detail' => 'Publicidad institucional', 'url' => route('admin.scheduled.index').'#schedule-ads'];
        }

        return ['mode' => 'idle', 'title' => 'Sin emisión', 'detail' => 'La señal muestra la pantalla de espera', 'url' => route('admin.scheduled.index')];
    }

    /** Trabajos en curso o con error: grabaciones, videos, calidades y subtítulos. */
    private function tasks(): array
    {
        $tasks = collect();
        $add = function (string $what, string $title, string $state, string $url) use ($tasks) {
            $tasks->push(['what' => $what, 'title' => $title, 'state' => $state, 'url' => $url]);
        };
        foreach (Transmision::with('evento')->where(fn ($q) => $q->whereIn('recording_status', ['processing', 'failed'])
            ->orWhereIn('renditions_status', ['processing', 'pending', 'failed'])->orWhereIn('subtitles_status', ['processing', 'pending', 'failed']))
            ->latest('updated_at')->take(12)->get() as $stream) {
            if (! $stream->evento) continue;
            $url = route('admin.eventos.show', $stream->evento);
            if (in_array($stream->recording_status, ['processing', 'failed'], true)) $add('Grabación', $stream->evento->titulo, $stream->recording_status === 'failed' ? 'bad' : 'work', $url);
            if (in_array($stream->renditions_status, ['processing', 'pending', 'failed'], true)) $add('Calidades', $stream->evento->titulo, $stream->renditions_status === 'failed' ? 'bad' : 'work', $url);
            if (in_array($stream->subtitles_status, ['processing', 'pending', 'failed'], true)) $add('Subtítulos', $stream->evento->titulo, $stream->subtitles_status === 'failed' ? 'bad' : 'work', $url);
        }
        foreach (ScheduledVideo::with('evento')->where(fn ($q) => $q->whereIn('processing_status', ['pending', 'processing', 'failed'])
            ->orWhereIn('renditions_status', ['processing', 'pending', 'failed'])->orWhereIn('subtitles_status', ['processing', 'pending', 'failed']))
            ->latest('updated_at')->take(12)->get() as $video) {
            if (! $video->evento) continue;
            $url = $video->isCatalogOnly() ? route('admin.eventos.show', $video->evento) : route('admin.scheduled.index');
            if (in_array($video->processing_status, ['pending', 'processing', 'failed'], true)) $add($video->isCatalogOnly() ? 'Video del catálogo' : 'Video programado', $video->evento->titulo, $video->processing_status === 'failed' ? 'bad' : 'work', $url);
            elseif (in_array($video->renditions_status, ['processing', 'pending', 'failed'], true)) $add('Calidades', $video->evento->titulo, $video->renditions_status === 'failed' ? 'bad' : 'work', $url);
            if (in_array($video->subtitles_status, ['processing', 'pending', 'failed'], true)) $add('Subtítulos', $video->evento->titulo, $video->subtitles_status === 'failed' ? 'bad' : 'work', $url);
        }
        foreach (InstitutionalAd::whereIn('processing_status', ['pending', 'processing', 'failed'])->latest('updated_at')->take(6)->get() as $ad) {
            $add('Publicidad', $ad->title, $ad->processing_status === 'failed' ? 'bad' : 'work', route('admin.scheduled.index').'#schedule-ads');
        }

        // Primero lo que falló.
        return $tasks->sortBy(fn ($t) => $t['state'] === 'bad' ? 0 : 1)->values()->take(8)->all();
    }
}
