<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->mfa_confirmed_at, 403, 'MFA enrollment is required.');

        return $next($request);
    }
}
