<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_abre_tipo_cambio_sin_redirigir_al_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Tipo Cambio')
            ->assertDontSee('Iniciar sesión');
    }
}
