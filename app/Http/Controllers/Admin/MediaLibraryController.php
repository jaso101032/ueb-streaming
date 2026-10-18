<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\ScheduledVideo;
use App\Support\ReactPage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class MediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::in(['recording', 'scheduled', 'ad'])],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $q = trim($filters['q'] ?? '');
        $kind = $filters['kind'] ?? '';
        $category = (int) ($filters['categoria_id'] ?? 0);
        $items = collect();

        if ($kind === '' || $kind === 'recording') {
            $events = Evento::with(['transmision', 'categoria'])
                ->whereHas('transmision', fn ($query) => $query->where('recording_status', '!=', 'idle'))
                ->when($category, fn ($query) => $query->where('categoria_id', $category))
                ->when($q !== '', fn ($query) => $query->where('titulo', 'like', '%'.$q.'%'))
                ->get();
            foreach ($events as $event) {
                $metadata = $event->transmision?->displayRecordingMetadata();
                if (! $metadata) continue;
                $date = $event->transmision->recording_ready_at ?? $event->transmision->iniciado_en ?? $event->created_at;
                $items->push([
                    'key' => 'recording-'.$event->id, 'kind' => 'recording', 'title' => $event->titulo,
                    'category' => $event->categoria?->nombre ?? 'Institucional', 'date' => $date->toDateString(),
                    'sortAt' => $date->getTimestamp(), 'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                    'sourceBytes' => $metadata['rawBytes'] ?? null, 'readyBytes' => $metadata['mp4Bytes'] ?? null,
                    'sourcePresent' => $metadata['rawPresent'] ?? false,
                    'sourceFormat' => 'FLV', 'readyFormat' => 'MP4',
                    'status' => $event->transmision->recording_status,
                    'detailsUrl' => route('admin.eventos.show', $event),
                    'previewUrl' => $event->transmision->recordingPath() && $event->estado === 'finalizado'
                        ? route('eventos.recording', $event) : null,
                ]);
            }
        }

        if ($kind === '' || $kind === 'scheduled') {
            $videos = ScheduledVideo::with(['evento.categoria'])
                ->whereHas('evento', fn ($query) => $query
                    ->when($category, fn ($query) => $query->where('categoria_id', $category))
                    ->when($q !== '', fn ($query) => $query->where('titulo', 'like', '%'.$q.'%')))
                ->get();
            foreach ($videos as $video) {
                $event = $video->evento;
                $date = $video->created_at;
                $items->push([
                    'key' => 'scheduled-'.$video->id, 'kind' => 'scheduled', 'title' => $event->titulo,
                    'category' => $event->categoria?->nombre ?? 'Institucional', 'date' => $date->toDateString(),
                    'sortAt' => $date->getTimestamp(), 'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                    'sourceBytes' => $video->source_bytes, 'readyBytes' => $video->optimized_bytes,
                    'sourceFormat' => 'Original', 'readyFormat' => 'MP4', 'status' => $video->processing_status,
                    'detailsUrl' => route('admin.scheduled.index'),
                    'previewUrl' => $video->processing_status === 'ready' ? route('admin.media.scheduled-preview', $event) : null,
                ]);
            }
        }

        if (! $category && ($kind === '' || $kind === 'ad')) {
            $ads = InstitutionalAd::query()
                ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'))->get();
            foreach ($ads as $ad) {
                $date = $ad->created_at;
                $items->push([
                    'key' => 'ad-'.$ad->id, 'kind' => 'ad', 'title' => $ad->title,
                    'category' => 'Publicidad institucional', 'date' => $date->toDateString(),
                    'sortAt' => $date->getTimestamp(), 'image' => null,
                    'sourceBytes' => $ad->source_bytes, 'readyBytes' => $ad->optimized_bytes,
                    'sourceFormat' => 'Original', 'readyFormat' => 'MP4', 'status' => $ad->processing_status,
                    'detailsUrl' => route('admin.scheduled.index').'#schedule-ads',
                    'previewUrl' => $ad->processing_status === 'ready' ? route('admin.media.ad-preview', $ad) : null,
                ]);
            }
        }

        $items = $items->filter(fn ($item) =>
            (! isset($filters['from']) || $item['date'] >= $filters['from']) &&
            (! isset($filters['to']) || $item['date'] <= $filters['to']))
            ->sortByDesc('sortAt')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($items->forPage($page, 12)->values(), $items->count(), 12, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.media.index', [
            'props' => [
                'items' => $paginator->items(), 'pagination' => ReactPage::pagination($paginator),
                'filters' => $filters, 'categories' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }
}
