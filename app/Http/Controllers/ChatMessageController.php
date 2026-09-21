<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Evento;
use App\Services\ChatFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatMessageController extends Controller
{
    public function index(Request $request, Evento $evento, ChatFeed $feed): JsonResponse
    {
        $messages = $feed->messages($evento);
        $response = response()->json($messages);
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->isNotModified($request);

        return $response;
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

        $message = ChatMessage::create([
            'evento_id' => $evento->id,
            'user_id' => $request->user()->id,
            'message' => trim($validated['message']),
        ]);
        $feed->forget($evento);

        return response()->json([
            'id' => $message->id,
            'user' => $request->user()->name,
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, Evento $evento, ChatMessage $message, ChatFeed $feed)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'operador'], true), 403);
        abort_unless($message->evento_id === $evento->id, 404);

        DB::transaction(function () use ($request, $message) {
            $message->moderated_by = $request->user()->id;
            $message->save();
            $message->delete();
        });
        $feed->forget($evento);

        return response()->noContent();
    }
}
