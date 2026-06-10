<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAutomationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('skycenter.automation_api_token');
        $token = $request->bearerToken();

        if (! $expected || ! $token || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
