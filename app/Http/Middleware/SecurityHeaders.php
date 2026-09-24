<?php

namespace App\Http\Middleware;

use Closure;

class SecurityHeaders
{
    /**
     * Handle an incoming request and attach standard security headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            
            // Set X-Frame-Options only if not already customized (e.g. for embeds/LFM)
            if (!$response->headers->has('X-Frame-Options')) {
                $response->header('X-Frame-Options', 'SAMEORIGIN');
            }
        }

        return $response;
    }
}
