<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    #[Test]
    public function web_middleware_adds_basic_security_headers(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/dashboard', 'GET');

        $response = $middleware->handle($request, static fn () => response('ok'));

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }
}
