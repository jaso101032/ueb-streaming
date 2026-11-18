<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\User;
use App\Services\ChannelSchedule;
use Illuminate\Support\Carbon;

/**
 * Guía de programación para la página de un video: lo que está al aire ahora y
 * lo que viene (programas del canal 24/7 y directos anunciados). Solo incluye
 * eventos que la persona puede ver.
 */
class ProgramGuide
{
    public static function for(?User $user, int $limit = 8): array
    {
        $now = now();
        $items = [];
        foreach (app(ChannelSchedule::class)->timeline() as $slot) {
            $event = $slot['event'];
            if ($event->trashed() || ! in_array($slot['state'], ['playing', 'paused', 'upcoming'], true)
                || ! $event->isVisibleTo($user)) continue;
            $items[] = self::item($event, 'channel', $slot['state'] === 'upcoming' ? 'next' : 'now', $slot['starts_at'], $slot['ends_at']);
        }
        $live = Evento::query()->with('categoria')->visibleTo($user)->whereDoesntHave('scheduledVideo')
            ->where(fn ($q) => $q->where('estado', 'en_vivo')
                ->orWhere(fn ($q) => $q->where('estado', 'programado')->whereBetween('fecha_inicio', [$now, $now->copy()->addDays(14)])))
            ->orderBy('fecha_inicio')->take($limit)->get();
        foreach ($live as $event) {
            $items[] = self::item($event, 'live', $event->estado === 'en_vivo' ? 'now' : 'next', $event->fecha_inicio?->toIso8601String());
        }
        // Lo que está al aire primero; después, por hora de inicio.
        usort($items, fn ($a, $b) => [$a['when'] !== 'now', $a['starts_at']] <=> [$b['when'] !== 'now', $b['starts_at']]);

        return array_slice($items, 0, $limit);
    }

    private static function item(Evento $event, string $kind, string $when, ?string $startsAt, ?string $endsAt = null): array
    {
        return ['id' => $event->id, 'title' => $event->titulo, 'url' => route('eventos.show', $event, false),
            'kind' => $kind, 'when' => $when, 'starts_at' => $startsAt ?? Carbon::now()->toIso8601String(), 'ends_at' => $endsAt,
            'category' => $event->categoria?->nombre,
            'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null];
    }
}
