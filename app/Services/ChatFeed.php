<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Evento;
use Illuminate\Support\Facades\Cache;

class ChatFeed
{
    public function messages(Evento $evento): array
    {
        return Cache::remember($this->key($evento), now()->addSeconds(3), fn () => ChatMessage::with('user:id,name')
            ->where('evento_id', $evento->id)
            ->orderByDesc('id')->limit(80)->get()->reverse()->values()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'user' => $message->user?->name ?? 'Usuario',
                'message' => $message->message,
                'created_at' => $message->created_at->toIso8601String(),
                'playback_seconds' => $message->playback_seconds,
            ])->all()
        );
    }

    public function forget(Evento $evento): void
    {
        Cache::forget($this->key($evento));
    }

    private function key(Evento $evento): string
    {
        return 'chat-feed:v2:'.$evento->id;
    }
}
