<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\ScheduledVideo;
use App\Models\Transmision;
use Carbon\Carbon;

class ChannelSchedule
{
    public function timeline(): array
    {
        $intervals = app(ChannelClock::class)->intervals();
        $cursor = 0;
        $items = [];
        $now = now()->timestamp;
        $events = Evento::withTrashed()->whereHas('scheduledVideo')->with('scheduledVideo')
            ->orderBy('fecha_inicio')->orderBy('id')->get();
        foreach ($events as $event) {
            if ($event->scheduledVideo->processing_status !== 'ready') continue;
            $unavailable = $event->trashed() || $event->estado === 'cancelado';
            // Historical slots retain their place: deleting archives must not rewind today's channel.
            $start = max($cursor, $event->fecha_inicio->timestamp, $event->scheduledVideo->ready_at?->timestamp ?? 0);
            $withdrawnAt = $event->deleted_at ?? $event->updated_at;
            if ($unavailable && $withdrawnAt->timestamp <= $start) {
                continue;
            }
            $duration = $event->scheduledVideo->duration;
            $end = $start + $duration;
            $paused = 0;
            $isPaused = false;
            foreach ($intervals as [$a, $b]) {
                if ($b <= $start) {
                    continue;
                }
                if ($a >= $end) {
                    break;
                }
                $overlapStart = max($a, $start);
                $end += $b - $overlapStart;
                $paused += max(0, min($now, $b) - $overlapStart);
                if ($a <= $now && $now < $b) {
                    $isPaused = true;
                }
            }
            $position = max(0, min($duration, $now - $start - $paused));
            $state = $unavailable ? 'cancelled' : ($now < $start ? 'upcoming' : ($now >= $end ? 'ended' : ($isPaused ? 'paused' : 'playing')));
            $items[$event->id] = ['event' => $event, 'state' => $state, 'position' => $position,
                'duration' => $duration, 'starts_at' => Carbon::createFromTimestamp($start)->toIso8601String(),
                'ends_at' => Carbon::createFromTimestamp($end)->toIso8601String(), 'server_time' => now()->toIso8601String()];
            $cursor = $end;
        }

        return $items;
    }

    public function playback(ScheduledVideo $video): array
    {
        if ($video->processing_status !== 'ready') return ['state' => $video->processing_status === 'failed' ? 'failed' : 'processing', 'position' => 0, 'duration' => $video->duration, 'server_time' => now()->toIso8601String()];
        $item = $this->timeline()[$video->evento_id] ?? ['state' => 'cancelled', 'position' => 0, 'duration' => $video->duration, 'server_time' => now()->toIso8601String()];
        unset($item['event']);

        return $item;
    }

    public function live(): ?Transmision
    {
        return Transmision::with('evento')->where('activo', true)->whereNull('finalizado_en')
            ->where('last_signal_at', '>', now()->subSeconds(ChannelClock::SIGNAL_TTL))
            ->whereHas('evento', fn ($q) => $q->where('estado', 'en_vivo')->whereDoesntHave('scheduledVideo'))
            ->orderBy('iniciado_en')->orderBy('id')->first();
    }

    public function summary(?array $items = null): array
    {
        $items ??= $this->timeline();
        $available = array_filter($items, fn ($i) => $i['state'] !== 'cancelled');
        $endsAt = collect($available)->max('ends_at');

        return ['count' => count($available), 'totalSeconds' => array_sum(array_column($available, 'duration')),
            'remainingSeconds' => array_sum(array_map(fn ($i) => $i['duration'] - $i['position'], $available)),
            'endsAt' => $endsAt];
    }
}
