<?php

namespace App\Http\Middleware;

use Closure;

class WebAuthenticate
{
    /**
     * Guest POST actions that are replayed after login instead of being lost.
     */
    public const RESUMABLE_POSTS = [
        'course/direct-payment',
        'products/direct-payment',
        'bundles/direct-payment',
    ];

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
        } elseif ($request->isMethod('post') and in_array($request->path(), self::RESUMABLE_POSTS) and !$request->ajax()) {
            // "Buy now": keep the submitted item so it can be re-posted right after login/register.
            session()->put('pending_purchase', [
                'action' => '/' . $request->path(),
                'data' => $request->except(['_token']),
            ]);
            session()->put('url.intended', url('/resume-purchase'));
        } else {
            // For other POST actions return to the page the action came from.
            $previous = url()->previous();

            if (!empty($previous) and parse_url($previous, PHP_URL_HOST) == $request->getHost()) {
                session()->put('url.intended', $previous);
            }
        }

        return redirect('/login');
    }
}
