<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoAnalyticsController extends Controller
{
    public function show(Request $request, Evento $evento)
    {
        $filters = $request->validate(['days' => ['nullable', 'integer', 'in:7,30,90']]);
        $days = (int) ($filters['days'] ?? 30);
        $today = now()->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $previousFrom = $from->copy()->subDays($days);
        $previousTo = $from->copy()->subDay();

        $dailyCounts = DB::table('event_video_views')
            ->where('evento_id', $evento->id)
            ->whereBetween('viewed_on', [$from->toDateString(), $today->toDateString()])
            ->select('viewed_on', DB::raw('COUNT(*) as total'))
            ->groupBy('viewed_on')
            ->pluck('total', 'viewed_on');

        $daily = collect(CarbonPeriod::create($from, $today))->map(fn ($date) => [
            'date' => $date->toDateString(),
            'label' => $date->format('d/m'),
            'views' => (int) ($dailyCounts[$date->toDateString()] ?? 0),
        ])->values();

        $currentViews = (int) $daily->sum('views');
        $previousViews = DB::table('event_video_views')->where('evento_id', $evento->id)
            ->whereBetween('viewed_on', [$previousFrom->toDateString(), $previousTo->toDateString()])->count();
        $growth = $previousViews > 0 ? round((($currentViews - $previousViews) / $previousViews) * 100, 1) : null;
        $peak = $daily->sortByDesc('views')->first();

        $ranked = Evento::query()
            ->where('estado', '!=', 'cancelado')
            ->where(fn ($query) => $query->whereNotNull('video_url')->orWhereHas('transmision')->orWhereHas('scheduledVideo'))
            ->leftJoin('event_video_views', function ($join) use ($from, $today) {
                $join->on('eventos.id', '=', 'event_video_views.evento_id')
                    ->whereBetween('event_video_views.viewed_on', [$from->toDateString(), $today->toDateString()]);
            })
            ->select('eventos.id', 'eventos.titulo')
            ->selectRaw('COUNT(event_video_views.id) as views')
            ->selectRaw("CASE WHEN eventos.estado = 'en_vivo' THEN 'En vivo' WHEN EXISTS (SELECT 1 FROM scheduled_videos WHERE scheduled_videos.evento_id = eventos.id) THEN 'Programado' WHEN eventos.video_url IS NOT NULL THEN 'Biblioteca' ELSE 'Grabación' END as type")
            ->groupBy('eventos.id', 'eventos.titulo', 'eventos.estado', 'eventos.video_url')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'title' => $row->titulo,
                    'url' => route('eventos.show', $row->id),
                    'views' => (int) $row->views,
                    'type' => $row->type,
                ];
            })
            ->values();

        return response()->json([
            'period' => $days,
            'current' => [
                'views' => $currentViews,
                'allTime' => DB::table('event_video_views')->where('evento_id', $evento->id)->count(),
                'peakDay' => $peak && $peak['views'] > 0 ? $peak : null,
                'growthPercent' => $growth,
                'previousViews' => $previousViews,
            ],
            'daily' => $daily,
            'mostViewed' => $ranked->sortByDesc('views')->take(5)->values(),
            'leastViewed' => $ranked->sortBy('views')->take(5)->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
