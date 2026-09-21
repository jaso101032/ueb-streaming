<?php

namespace App\Services;

use Illuminate\Http\Request;

class StreamCallback
{
    public function verify(Request $request): void
    {
        $token = config('streaming.callback_token');
        abort_unless(is_string($token) && $token !== '' &&
            is_string($request->query('token')) && hash_equals($token, $request->query('token')), 403);
        abort_unless($request->input('app') === 'live' && is_string($request->input('name')) &&
            preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $request->input('name')), 403);
    }
}
