<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_sends_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/ingresar')->assertOk()->assertSee('Ingresar con Google');
        $this->get('/up')->assertOk();
    }
}
