<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_public_home_opens_the_catalog_when_there_is_no_live_event(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('eventos.index');
    }
}
