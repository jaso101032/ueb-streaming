<?php

namespace App\Services;

use App\Models\Transmision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChannelClock
{
    public const SIGNAL_TTL = 30;

    // Called under the existing stream row lock. Heartbeats renew a finite lease,
    // so an abruptly lost publisher cannot freeze the channel indefinitely.
    public function signal(Transmision $stream): void
    {
        $last = DB::table('channel_interruptions')->where('transmision_id', $stream->id)->latest('id')->first();
        $end = now()->addSeconds(self::SIGNAL_TTL);
        if ($last && Carbon::parse($last->ends_at)->gte(now())) {
            DB::table('channel_interruptions')->where('id', $last->id)->update(['ends_at' => $end]);
        } else {
            DB::table('channel_interruptions')->insert(['transmision_id' => $stream->id, 'starts_at' => now(), 'ends_at' => $end]);
        }
    }

    public function stop(Transmision $stream): void
    {
        DB::table('channel_interruptions')->where('transmision_id', $stream->id)
            ->where('ends_at', '>', now())->update(['ends_at' => now()]);
    }

    public function intervals(): array
    {
        $merged = [];
        foreach (DB::table('channel_interruptions')->orderBy('starts_at')->get() as $row) {
            $start = Carbon::parse($row->starts_at)->timestamp;
            $end = Carbon::parse($row->ends_at)->timestamp;
            $i = count($merged) - 1;
            if ($i >= 0 && $start <= $merged[$i][1]) {
                $merged[$i][1] = max($merged[$i][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
