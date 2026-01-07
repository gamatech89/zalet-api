<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Services\BarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarController extends Controller
{
    public function __construct(
        private BarService $barService
    ) {}

    /**
     * List public bars
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $bars = $this->barService->getPublicBars($perPage);

        return response()->json($bars);
    }

    /**
     * Search bars
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $bars = $this->barService->searchBars($request->input('q'));

        return response()->json($bars);
    }

    /**
     * Get user's bars (member of)
     */
    public function myBars(Request $request): JsonResponse
    {
        $bars = $this->barService->getUserBars($request->user());

        return response()->json([
            'data' => $bars,
        ]);
    }

    /**
     * Get user's owned bars
     */
    public function ownedBars(Request $request): JsonResponse
    {
        $bars = $this->barService->getOwnedBars($request->user());

        return response()->json([
            'data' => $bars,
        ]);
    }

    /**
     * Create a new bar
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|url',
            'is_public' => 'boolean',
            'password' => 'nullable|string|min:4|max:50',
            'member_limit' => 'nullable|integer|min:10|max:10000',
        ]);

        try {
            $bar = $this->barService->createBar($request->user(), $validated);

            return response()->json([
                'data' => $bar->load('owner.profile'),
                'message' => 'Bar created successfully!',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get single bar
     */
    public function show(Bar $bar): JsonResponse
    {
        $bar->load(['owner.profile']);
        $bar->loadCount('members');

        return response()->json([
            'data' => $bar,
        ]);
    }

    /**
     * Update bar
     */
    public function update(Request $request, Bar $bar): JsonResponse
    {
        if ($bar->owner_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only owner can update the bar.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|url',
            'is_public' => 'boolean',
            'password' => 'nullable|string|min:4|max:50',
            'member_limit' => 'integer|min:10|max:10000',
        ]);

        try {
            $bar = $this->barService->updateBar($bar, $validated);

            return response()->json([
                'data' => $bar,
                'message' => 'Bar updated successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete bar
     */
    public function destroy(Request $request, Bar $bar): JsonResponse
    {
        try {
            $this->barService->deleteBar($request->user(), $bar);

            return response()->json([
                'message' => 'Bar deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Join a bar
     */
    public function join(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'nullable|string',
        ]);

        try {
            $member = $this->barService->joinBar(
                $request->user(),
                $bar,
                $validated['password'] ?? null
            );

            return response()->json([
                'data' => $member->load('user.profile'),
                'message' => 'Joined bar successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Leave a bar
     */
    public function leave(Request $request, Bar $bar): JsonResponse
    {
        try {
            $this->barService->leaveBar($request->user(), $bar);

            return response()->json([
                'message' => 'Left bar successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get bar members
     */
    public function members(Bar $bar): JsonResponse
    {
        $members = $bar->members()
            ->with('user.profile')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END")
            ->get();

        return response()->json([
            'data' => $members,
        ]);
    }

    /**
     * Kick a member
     */
    public function kickMember(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $target = \App\Models\User::findOrFail($validated['user_id']);
            $this->barService->kickMember($request->user(), $bar, $target);

            return response()->json([
                'message' => 'Member kicked successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Promote member to moderator
     */
    public function promoteMember(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $target = \App\Models\User::findOrFail($validated['user_id']);
            $member = $this->barService->promoteMember($request->user(), $bar, $target);

            return response()->json([
                'data' => $member,
                'message' => 'Member promoted to moderator!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Demote moderator to member
     */
    public function demoteMember(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $target = \App\Models\User::findOrFail($validated['user_id']);
            $member = $this->barService->demoteMember($request->user(), $bar, $target);

            return response()->json([
                'data' => $member,
                'message' => 'Moderator demoted to member!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mute a member
     */
    public function muteMember(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'minutes' => 'required|integer|min:1|max:1440', // Max 24 hours
        ]);

        try {
            $target = \App\Models\User::findOrFail($validated['user_id']);
            $member = $this->barService->muteMember(
                $request->user(),
                $bar,
                $target,
                $validated['minutes']
            );

            return response()->json([
                'data' => $member,
                'message' => "Member muted for {$validated['minutes']} minutes!",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
