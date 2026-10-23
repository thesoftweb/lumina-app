<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArtisanEndpointRemovedTest extends TestCase
{
    /** O antigo POST /artisan rodava qualquer comando sem autenticação (S1-01). */
    public function test_artisan_endpoint_no_longer_exists(): void
    {
        $this->post('/artisan')->assertNotFound();
        $this->post('/artisan', ['command' => 'about'])->assertNotFound();
    }
}
