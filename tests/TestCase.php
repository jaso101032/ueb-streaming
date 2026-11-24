<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Crea los eventos de prueba con una autorización de difusión vigente. */
    protected bool $authorizeEvents = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Los eventos de prueba se crean con una autorización de difusión vigente,
        // salvo que la prueba indique otra cosa (AuthorizationRequiredTest).
        \App\Models\Evento::creating(function (\App\Models\Evento $evento) {
            if (! $this->authorizeEvents) return;
            $attributes = $evento->getAttributes();
            if (array_key_exists('autorizacion_fecha', $attributes)) return;
            $evento->autorizacion_responsable ??= 'Rectorado';
            $evento->autorizacion_referencia ??= 'Oficio de prueba';
            $evento->autorizacion_fecha = now()->subDay()->toDateString();
        });
    }

    protected function reactProps($response): array
    {
        $response->assertOk();
        preg_match('/data-props="([^"]*)"/', $response->getContent(), $match);
        $this->assertNotEmpty($match, 'La página debe proporcionar datos para React.');
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }
}
