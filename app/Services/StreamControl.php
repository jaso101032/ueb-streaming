<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StreamControl
{
    public function stop(string $key): void
    {
        if ($key === '' || !config('streaming.callback_token')) {
            throw new RuntimeException('Streaming control is not configured.');
        }

        // Only the configured server is contacted; never use a URL from the request.
        $response = Http::connectTimeout(2)->timeout(5)->withoutRedirecting()
            ->get(rtrim(config('streaming.control_url'), '/').'/drop/publisher', [
                'app' => 'live', 'name' => $key,
            ]);

        // nginx-rtmp returns the number of publishers disconnected (0 is safe to retry).
        if (!$response->successful() || !preg_match('/^\d+$/', trim($response->body()))) {
            throw new RuntimeException('Streaming server did not confirm the stop.');
        }
    }
}
