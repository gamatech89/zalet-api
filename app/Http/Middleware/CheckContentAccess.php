<?php

namespace App\Http\Middleware;

use App\Services\ContentAccessService;
use App\Models\Media;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckContentAccess
{
    public function __construct(
        protected ContentAccessService $contentAccessService
    ) {}

    /**
     * Handle an incoming request.
     * Check if the user has access to the requested media content.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $media = $request->route('media');

        if (!$media instanceof Media) {
            return $next($request);
        }

        $user = $request->user();
        $accessInfo = $this->contentAccessService->getAccessInfo($user, $media);

        if (!$accessInfo['can_access']) {
            return response()->json([
                'message' => 'This content requires a subscription or purchase.',
                'access_info' => $accessInfo,
            ], 403);
        }

        return $next($request);
    }
}
