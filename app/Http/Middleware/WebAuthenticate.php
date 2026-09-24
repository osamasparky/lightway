<?php

namespace App\Http\Middleware;

use Closure;

class WebAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            return $next($request);
        }

        // Remember where the guest was going (e.g. /cart) so login/register can send them back.
        if ($request->isMethod('get') and !$request->ajax() and !$request->expectsJson()) {
            session()->put('url.intended', $request->fullUrl());
        } else {
            // For POST actions (checkout, direct payment...) return to the page the action came from.
            $previous = url()->previous();

            if (!empty($previous) and parse_url($previous, PHP_URL_HOST) == $request->getHost()) {
                session()->put('url.intended', $previous);
            }
        }

        return redirect('/login');
    }
}
