<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreatorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorRequestController extends Controller
{
    /**
     * Submit a request to become a creator.
     *
     * POST /api/v1/creator-requests
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'portfolio_url' => ['nullable', 'url', 'max:500'],
        ]);

        $user = $request->user();

        // Check if user is already a creator
        if ($user->isCreator()) {
            return response()->json([
                'message' => 'You are already a creator.',
            ], 400);
        }

        // Check for existing pending request
        $existingRequest = CreatorRequest::where('user_id', $user->id)
            ->pending()
            ->first();

        if ($existingRequest) {
            return response()->json([
                'message' => 'You already have a pending request.',
                'data' => $this->formatRequest($existingRequest),
            ], 409);
        }

        $creatorRequest = CreatorRequest::create([
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'portfolio_url' => $request->input('portfolio_url'),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Your request has been submitted. We will review it shortly.',
            'data' => $this->formatRequest($creatorRequest),
        ], 201);
    }

    /**
     * Get the current user's latest creator request.
     *
     * GET /api/v1/creator-requests/mine
     */
    public function show(Request $request): JsonResponse
    {
        $creatorRequest = CreatorRequest::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$creatorRequest) {
            return response()->json([
                'data' => null,
                'message' => 'No creator request found.',
            ]);
        }

        return response()->json([
            'data' => $this->formatRequest($creatorRequest),
        ]);
    }

    protected function formatRequest(CreatorRequest $request): array
    {
        return [
            'id' => $request->id,
            'message' => $request->message,
            'portfolio_url' => $request->portfolio_url,
            'status' => $request->status,
            'admin_notes' => $request->status !== 'pending' ? $request->admin_notes : null,
            'submitted_at' => $request->created_at->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
        ];
    }
}
