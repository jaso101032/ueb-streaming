<?php

namespace Tests\Feature;

use App\Models\PlayerWatermark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerWatermarkPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_corner_without_uploading_a_logo_and_player_receives_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('admin.watermark.position'), ['position' => 'bottom-left'])
            ->assertRedirect(route('admin.watermark.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('player_watermarks', ['id' => 1, 'path' => '', 'position' => 'bottom-left']);
        $this->assertSame('bottom-left', PlayerWatermark::current()['position']);

        auth()->logout();
        $response = $this->get('/senal')->assertOk();
        preg_match('/data-shell="([^"]*)"/', $response->getContent(), $match);
        $shell = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertSame('bottom-left', $shell['playerWatermark']['position']);
    }

    public function test_position_is_limited_to_the_four_corners(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->from(route('admin.watermark.edit'))
            ->put(route('admin.watermark.position'), ['position' => 'center'])
            ->assertRedirect(route('admin.watermark.edit'))
            ->assertSessionHasErrors('position');

        $this->assertDatabaseCount('player_watermarks', 0);
    }
}
