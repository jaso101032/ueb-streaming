<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function reactProps($response): array
    {
        $response->assertOk();
        preg_match('/data-props="([^"]*)"/', $response->getContent(), $match);
        $this->assertNotEmpty($match, 'La página debe proporcionar datos para React.');
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }
}
