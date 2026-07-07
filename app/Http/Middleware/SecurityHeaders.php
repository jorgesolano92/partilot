<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de endurecimiento para el panel web (SEC-032).
 * CSP permisiva: el panel usa scripts inline (jQuery, DataTables, CKEditor).
 */
class SecurityHeaders
{
    /** Rutas públicas de documentos legales embebibles en la app móvil (iframe). */
    private const LEGAL_EMBEDDABLE_PATHS = [
        'aviso-legal',
        'politica-de-privacidad',
        'politica-de-cookies',
        'terminos-y-condiciones',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $embeddableLegal = $this->isLegalEmbeddableRequest($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($embeddableLegal) {
            $response->headers->remove('X-Frame-Options');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $frameAncestors = $embeddableLegal
                ? 'frame-ancestors '.trim((string) config('legal.embeddable_frame_ancestors', "'self'"))
                : "frame-ancestors 'self'";

            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                $frameAncestors,
                "object-src 'none'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https://fonts.gstatic.com",
                "connect-src 'self' https://api.stripe.com https://*.googleapis.com wss:",
                "frame-src 'self' https://js.stripe.com https://hooks.stripe.com",
            ]));
        }

        return $response;
    }

    protected function isLegalEmbeddableRequest(Request $request): bool
    {
        return in_array(trim($request->path(), '/'), self::LEGAL_EMBEDDABLE_PATHS, true);
    }
}
