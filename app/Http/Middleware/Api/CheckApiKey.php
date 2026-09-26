<?php

namespace App\Http\Middleware\Api;

use Closure;

class CheckApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        // Read through config: env() is empty once the config is cached (as on the servers).
        $apiKey = (string)config('app.api_key');

        if ($apiKey === '' || !hash_equals($apiKey, (string)$request->header('x-api-key'))) {

            return apiResponse2(0, 'client_identity_error', 'client identification failed.check the api key');
        }
        return $next($request);
    }
}
