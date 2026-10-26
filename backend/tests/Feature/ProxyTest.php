<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

/**
 * Detrás del proxy (192.168.100.2) que termina el HTTPS, los formularios y los recursos
 * tienen que salir con https://. Si salen con http://, el proxy redirige a https y el
 * navegador convierte los POST en GET: "405 Method Not Allowed".
 */
class ProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function loginPageFrom(string $remoteAddr): string
    {
        TrustProxies::at(['192.168.100.2']);

        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '181.1.2.3', 'X-Forwarded-Host' => 'panel.misioweb.com'])
            ->get('http://panel.misioweb.com/ingresar')
            ->assertOk()
            ->getContent();
    }

    public function test_forms_use_https_when_request_comes_from_the_proxy(): void
    {
        $html = $this->loginPageFrom('192.168.100.2');

        $this->assertStringContainsString('action="https://panel.misioweb.com/ingresar"', $html);
        $this->assertStringContainsString('href="https://panel.misioweb.com/css/panel.css"', $html);
    }

    public function test_forms_use_https_even_if_proxy_ip_is_lost_when_app_url_is_https(): void
    {
        // Así llegaba con el Nginx anterior (real_ip): la IP del visitante en vez de la del proxy.
        config(['app.url' => 'https://panel.misioweb.com']);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $html = $this->loginPageFrom('181.1.2.3');

        $this->assertStringContainsString('action="https://panel.misioweb.com/ingresar"', $html);
    }
}
