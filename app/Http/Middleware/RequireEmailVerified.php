<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEmailVerified
{
    // These endpoints stay accessible even without a verified email
    private array $exempt = [
        'api/v1/auth/logout',
        'api/v1/auth/me',
        'api/v1/auth/verify-email/resend',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->exempt as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        if ($request->user() && !$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Molimo potvrdi svoju email adresu pre korišćenja aplikacije.',
                'error_type' => 'email_not_verified',
            ], 403);
        }

        return $next($request);
    }
}
