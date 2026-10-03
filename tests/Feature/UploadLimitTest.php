<?php

namespace Tests\Feature;

use App\Support\UploadLimits;
use Tests\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_oversized_request_returns_a_readable_page_before_authentication(): void
    {
        $size = UploadLimits::bytes(ini_get('post_max_size'));
        if (!$size) $this->markTestSkipped('PHP has no POST size limit.');
        $this->call('POST', '/admin/contenido-programado', [], [], [], ['CONTENT_LENGTH' => $size + 1])
            ->assertStatus(413)->assertSee('data-react-page="UploadError"', false)->assertDontSee('Exception trace');
    }
    public function test_oversized_json_request_returns_actionable_message(): void
    {
        $size = UploadLimits::bytes(ini_get('post_max_size'));
        if (!$size) $this->markTestSkipped('PHP has no POST size limit.');
        $this->call('POST', '/admin/contenido-programado', [], [], [], ['CONTENT_LENGTH' => $size + 1, 'HTTP_ACCEPT' => 'application/json'])
            ->assertStatus(413)->assertJsonPath('message', fn ($message) => str_contains($message, 'límite del servidor'));
    }
}
