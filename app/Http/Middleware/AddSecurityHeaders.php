<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard defense-in-depth headers, applied to every response. This is a
 * JSON API consumed by a separate SPA, so these matter less than they would
 * for a server-rendered app, but they're free insurance: they stop a
 * browser from MIME-sniffing a JSON/PDF response into something executable,
 * stop this origin's pages (the default Laravel welcome page, any PDF
 * served directly) from being framed by another site, and avoid leaking the
 * full request URL to third parties via the Referer header.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
