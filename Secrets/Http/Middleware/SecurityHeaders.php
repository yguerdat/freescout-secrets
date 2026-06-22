<?php

namespace Modules\Secrets\Http\Middleware;

use Closure;

/**
 * Hardened security headers for the public-facing secret pages and API.
 *
 * The pages ship no inline scripts: all JavaScript lives in module asset files
 * (script-src 'self'), and data is passed through data-* attributes. This keeps
 * a strict CSP that materially reduces the XSS blast radius around plaintext
 * secrets handled in the browser.
 */
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!method_exists($response, 'header')) {
            return $response;
        }

        // Allow an admin-configured brand logo hosted on another origin.
        $imgSrc = "'self' data:";
        $logo = \Option::get('secrets.logo_url');
        if ($logo) {
            $scheme = parse_url($logo, PHP_URL_SCHEME);
            $host = parse_url($logo, PHP_URL_HOST);
            if ($scheme && $host) {
                $imgSrc .= ' ' . $scheme . '://' . $host;
            }
        }

        $csp = implode('; ', [
            "default-src 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src " . $imgSrc,
            "font-src 'self'",
            "connect-src 'self'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // Never let a secret page be cached anywhere.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
