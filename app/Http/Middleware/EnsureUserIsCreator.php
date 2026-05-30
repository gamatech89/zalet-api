<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCreator
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has creator or admin role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isCreator()) {
            return response()->json([
                'message' => 'Forbidden. Creator access required.',
            ], 403);
        }

        return $next($request);
    }
}
