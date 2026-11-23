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
            'kind' => ['nullable', Rule::in(['recording', 'catalog', 'scheduled', 'ad'])],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'liberar' => ['nullable', 'boolean'],
            'subir' => ['nullable', 'boolean'],
        ]);
        unset($filters['liberar'], $filters['subir']);
        $q = trim($filters['q'] ?? '');
        $kind = $filters['kind'] ?? '';
        $category = (int) ($filters['categoria_id'] ?? 0);
        $items = collect();

        // Se reúnen todos los tipos para mostrar los conteos por pestaña; el
        // filtro de tipo se aplica después, solo a la lista.
        if (true) {
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
                    'publicUrl' => route('eventos.show', $event, false),
                    'previewUrl' => $event->transmision->recordingPath() && $event->estado === 'finalizado'
                        ? route('eventos.recording', $event) : null,
                    'downloadUrl' => $event->transmision->recordingPath() && $event->estado === 'finalizado'
                        ? route('admin.media.download', $event->id) : null,
                ]);
            }
        }

        if (true) {
            $videos = ScheduledVideo::with(['evento.categoria'])
                ->whereHas('evento', fn ($query) => $query
                    ->when($category, fn ($query) => $query->where('categoria_id', $category))
                    ->when($q !== '', fn ($query) => $query->where('titulo', 'like', '%'.$q.'%')))
                ->get();
            foreach ($videos as $video) {
                $event = $video->evento;
                $date = $video->created_at;
                $items->push([
                    'key' => 'scheduled-'.$video->id, 'kind' => $video->isCatalogOnly() ? 'catalog' : 'scheduled', 'title' => $event->titulo,
                    'category' => $event->categoria?->nombre ?? 'Institucional', 'date' => $date->toDateString(),
                    'sortAt' => $date->getTimestamp(), 'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                    'sourceBytes' => $video->source_bytes, 'readyBytes' => $video->optimized_bytes,
                    'sourceFormat' => 'Original', 'readyFormat' => 'MP4', 'status' => $video->processing_status,
                    'detailsUrl' => $video->isCatalogOnly() ? route('admin.eventos.show', $event) : route('admin.scheduled.index'),
                    'publicUrl' => route('eventos.show', $event, false),
                    'progress' => $video->processing_progress,
                    'previewUrl' => $video->processing_status === 'ready' ? route('admin.media.scheduled-preview', $event) : null,
                    'downloadUrl' => $video->processing_status === 'ready' ? route('admin.media.download', $event->id) : null,
                ]);
            }
        }

        if (! $category) {
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
                    'publicUrl' => null,
                    'previewUrl' => $ad->processing_status === 'ready' ? route('admin.media.ad-preview', $ad) : null,
                    'downloadUrl' => $ad->processing_status === 'ready' ? route('admin.media.ad-download', $ad) : null,
                ]);
            }
        }

        $items = $items->filter(fn ($item) =>
            (! isset($filters['from']) || $item['date'] >= $filters['from']) &&
            (! isset($filters['to']) || $item['date'] <= $filters['to']))
            ->sortByDesc('sortAt')->values();
        $counts = ['all' => $items->count()] + collect(['recording', 'catalog', 'scheduled', 'ad'])
            ->mapWithKeys(fn ($k) => [$k => $items->where('kind', $k)->count()])->all();
        $totalBytes = $items->sum(fn ($item) => (int) ($item['readyBytes'] ?? 0) + (($item['sourcePresent'] ?? ($item['kind'] !== 'recording')) ? (int) ($item['sourceBytes'] ?? 0) : 0));
        if ($kind !== '') $items = $items->where('kind', $kind)->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($items->forPage($page, 12)->values(), $items->count(), 12, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.media.index', [
            'props' => [
                'items' => $paginator->items(), 'pagination' => ReactPage::pagination($paginator),
                'filters' => $filters, 'categories' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
                'counts' => $counts, 'totalBytes' => $totalBytes, 'catalogUrl' => route('eventos.index'),
                'storage' => \App\Support\StorageUsage::recordings(),
                'cleanup' => ['url' => route('admin.media.cleanup'), 'listUrl' => route('admin.media.cleanup.list'),
                    'open' => $request->boolean('liberar')],
                'upload' => ['action' => route('admin.media.videos.store'), 'limits' => \App\Support\UploadLimits::scheduled(),
                    'faculties' => \App\Models\Facultad::orderBy('nombre')->get(['id', 'nombre']),
                    'careers' => \App\Models\Carrera::orderBy('nombre')->get(['id', 'nombre', 'facultad_id']),
                    'open' => $request->boolean('subir') || ($request->session()->hasOldInput('titulo') && $request->session()->has('errors'))],
            ],
        ]);
    }
}
