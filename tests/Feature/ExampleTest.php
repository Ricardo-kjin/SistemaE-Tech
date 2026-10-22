<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_redirige_al_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }
}
