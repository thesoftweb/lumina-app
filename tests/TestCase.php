<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste fala com APIs de verdade (ex.: Asaas): toda chamada HTTP precisa de Http::fake
        Http::preventStrayRequests();
    }
}
