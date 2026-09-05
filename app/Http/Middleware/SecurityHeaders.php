<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $connectSources = ["'self'"];
        $scriptSources = ["'self'"];
        $styleSources = ["'self'", "'unsafe-inline'"];
        if (app()->environment('local')) {
            $connectSources = [...$connectSources, 'ws://127.0.0.1:5173', 'http://127.0.0.1:5173', 'ws://localhost:5173', 'http://localhost:5173'];
            $scriptSources = [...$scriptSources, 'http://127.0.0.1:5173', 'http://localhost:5173'];
            $styleSources = [...$styleSources, 'http://127.0.0.1:5173', 'http://localhost:5173'];
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            'connect-src '.implode(' ', $connectSources),
            "font-src 'self' data:",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "object-src 'none'",
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
        ]));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
