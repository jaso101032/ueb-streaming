<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\Transmision;
use App\Services\ChatFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatMessageController extends Controller
{
    public function index(Request $request, Evento $evento, ChatFeed $feed): JsonResponse
    {
        $messages = $feed->messages($evento);
        $response = response()->json($messages);
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->headers->set('X-Chat-Enabled', $evento->chat_enabled ? '1' : '0');
        $response->headers->set('X-Chat-Restricted', $request->user()?->chat_restricted ? '1' : '0');
        $response->isNotModified($request);

        return $response;
    }

    public function replay(Request $request, Evento $evento): JsonResponse
    {
        abort_unless($evento->estado === 'finalizado', 404);
        $data = $request->validate(['after_id' => ['sometimes', 'integer', 'min:0']]);
        $afterId = (int) ($data['after_id'] ?? 0);

        // El feed del directo conserva solo los últimos 80 mensajes. La grabación
        // necesita el historial completo, paginado para no generar respuestas enormes.
        $page = ChatMessage::with('user:id,name')
            ->where('evento_id', $evento->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(201)
            ->get();
        $hasMore = $page->count() > 200;
        $messages = $page->take(200)->map(fn (ChatMessage $message) => [
            'id' => $message->id,
            'user' => $message->user?->name ?? 'Usuario',
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
            'playback_seconds' => $message->playback_seconds,
        ])->values();

        return response()->json([
            'messages' => $messages,
            'next_after_id' => $hasMore ? $messages->last()['id'] : null,
        ])->header('Cache-Control', 'private, no-cache');
    }

    public function store(Request $request, Evento $evento, ChatFeed $feed): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:180'],
        ], [
            'message.required' => 'Escribe un mensaje antes de enviarlo.',
            'message.string' => 'El mensaje debe ser texto.',
            'message.max' => 'El mensaje no puede superar 180 caracteres.',
        ]);

        // Match the lock order used by stream start/finalization so comments
        // are consistently assigned to the live timeline.
        $message = DB::transaction(function () use ($evento, $request, $validated) {
            $lockedEvent = Evento::whereKey($evento->id)->lockForUpdate()->firstOrFail();
            $stream = Transmision::where('evento_id', $lockedEvent->id)->lockForUpdate()->first();
            $playbackSeconds = null;

            if ($lockedEvent->estado === 'finalizado' || $stream?->finalizado_en) {
                throw ValidationException::withMessages(['message' => 'El chat está cerrado porque la transmisión ya terminó.']);
            }
            if (! $lockedEvent->chat_enabled) {
                throw ValidationException::withMessages(['message' => 'Los comentarios están desactivados para esta transmisión.']);
            }
            if ($request->user()->chat_restricted) {
                throw ValidationException::withMessages(['message' => 'Tu cuenta tiene restringidos los comentarios.']);
            }

            if ($lockedEvent->estado === 'en_vivo' && $stream?->iniciado_en && !$stream->finalizado_en) {
                $playbackSeconds = max(0, now()->getTimestamp() - $stream->iniciado_en->getTimestamp());
            }

            return ChatMessage::create([
                'evento_id' => $lockedEvent->id,
                'user_id' => $request->user()->id,
                'message' => trim($validated['message']),
                'playback_seconds' => $playbackSeconds,
            ]);
        });
        $feed->forget($evento);

        return response()->json([
            'id' => $message->id,
            'user' => $request->user()->name,
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
            'playback_seconds' => $message->playback_seconds,
        ], 201);
    }

    public function destroy(Request $request, Evento $evento, ChatMessage $message, ChatFeed $feed)
    {
        abort_unless($request->user()?->allowsOperatorAction('moderate_chat'), 403);
        abort_unless($message->evento_id === $evento->id, 404);

        DB::transaction(function () use ($request, $message) {
            $message->moderated_by = $request->user()->id;
            $message->save();
            $message->delete();
        });
        $feed->forget($evento);

        return response()->noContent();
    }

    public function toggle(Request $request, Evento $evento)
    {
        abort_unless($request->user()?->allowsOperatorAction('toggle_chat'), 403);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($evento, $data) {
            $locked = Evento::whereKey($evento->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->scheduledVideo, 422, 'El control de comentarios es para transmisiones en vivo.');
            abort_if(in_array($locked->estado, ['finalizado', 'cancelado']), 422, 'Esta transmisión ya terminó.');
            $locked->update(['chat_enabled' => $data['enabled']]);
        });

        return back()->with('success', $data['enabled'] ? 'Comentarios habilitados.' : 'Comentarios deshabilitados. El historial sigue visible.');
    }
}
