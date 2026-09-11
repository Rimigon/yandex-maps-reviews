<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * За HTTPS-прокси Laravel должен уважать заголовки X-Forwarded-*, иначе считает
 * запрос небезопасным: ссылки идут с http, а cookie сессии — без флага Secure.
 * Поведение включается настройкой trustedproxy.proxies, за ним и следит тест.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_scheme', fn () => response()->json([
            'secure' => request()->isSecure(),
            'scheme' => request()->getScheme(),
        ]));
    }

    public function test_заголовки_прокси_учитываются_когда_прокси_доверенный(): void
    {
        config()->set('trustedproxy.proxies', '*');

        $this->withHeader('X-Forwarded-Proto', 'https')
            ->getJson('/_scheme')
            ->assertOk()
            ->assertJson(['secure' => true, 'scheme' => 'https']);
    }

    public function test_заголовки_прокси_игнорируются_по_умолчанию(): void
    {
        config()->set('trustedproxy.proxies', null);

        $this->withHeader('X-Forwarded-Proto', 'https')
            ->getJson('/_scheme')
            ->assertOk()
            ->assertJson(['secure' => false, 'scheme' => 'http']);
    }
}
