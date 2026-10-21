<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class FanoutAudienceAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId, public int $attempt = 0) {}

    public function handle(): void
    {
        $announcement = DB::table('audience_announcements')->find($this->announcementId);
        if (! $announcement) return;
        $event = DB::table('eventos')->find($announcement->evento_id);
        if (! $event || $event->deleted_at || $event->estado === 'cancelado') return;
        if ($announcement->kind === 'live') {
            $stream = DB::table('transmisiones')->where('evento_id', $event->id)->where('activo', true)
                ->where('last_signal_at', '>', now()->subSeconds(15))->first();
            if ($event->estado !== 'en_vivo' || ! $stream) return;
            // The OBS authorization callback precedes the first HLS segment.
            $playlist = rtrim(config('streaming.hls_root'), '/').'/'.$stream->stream_key.'.m3u8';
            $ready = is_file($playlist) && str_contains((string) @file_get_contents($playlist), '#EXTINF');
            if (! $ready) {
                if ($this->attempt < 8 && config('queue.default') !== 'sync') self::dispatch($this->announcementId, $this->attempt + 1)->delay(now()->addSeconds(5));
                return;
            }
        } else {
            $scheduled = \App\Models\ScheduledVideo::where('evento_id', $event->id)->first();
            if ($scheduled) {
                $playback = $scheduled->playback();
                if ($playback['state'] === 'upcoming') {
                    if (config('queue.default') !== 'sync') self::dispatch($this->announcementId, $this->attempt + 1)->delay(\Carbon\Carbon::parse($playback['starts_at'])->addSecond());
                    return;
                }
                if ($playback['state'] === 'paused') {
                    if (config('queue.default') !== 'sync') self::dispatch($this->announcementId, $this->attempt + 1)->delay(now()->addMinute());
                    return;
                }
                if (! in_array($playback['state'], ['playing', 'ended'], true)) return;
            }
        }

        $column = $announcement->kind === 'live' ? 'notify_live' : 'notify_videos';
        User::query()->where($column, true)->select('id')->chunkById(25, function ($users) {
            DeliverAudienceAnnouncement::dispatch($this->announcementId, $users->pluck('id')->all());
        });
    }
}
