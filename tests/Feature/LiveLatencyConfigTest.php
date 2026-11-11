<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * RNF-06: la latencia objetivo del directo es de 5 segundos. Se alcanza con
 * segmentos HLS de 1 s, OBS con fotogramas clave cada 1 s y el reproductor a
 * 2 segmentos del borde en vivo. Esta prueba evita que la configuración cambie
 * sin advertirlo.
 */
class LiveLatencyConfigTest extends TestCase
{
    public function test_hls_segments_last_one_second_in_production_and_local_servers(): void
    {
        foreach (['docker/rtmp.conf.template', 'scripts/nginx-rtmp-local.conf.template'] as $file) {
            $conf = file_get_contents(base_path($file));
            $this->assertMatchesRegularExpression('/^\s*hls_fragment 1s;$/m', $conf, $file);
            $this->assertMatchesRegularExpression('/^\s*hls_playlist_length 90s;$/m', $conf, $file);
        }
    }

    public function test_player_stays_two_segments_behind_the_live_edge(): void
    {
        $player = file_get_contents(resource_path('js/platform/Player.jsx'));
        $this->assertStringContainsString('liveSyncDurationCount: 2,', $player);
        $this->assertStringContainsString('liveMaxLatencyDurationCount: 5,', $player);
    }
}
