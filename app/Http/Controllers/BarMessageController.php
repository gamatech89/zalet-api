<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Models\BarMessage;
use App\Services\BarMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarMessageController extends Controller
{
    public function __construct(
        private BarMessageService $messageService
    ) {}

    /**
     * Get messages for a bar
     */
    public function index(Request $request, Bar $bar): JsonResponse
    {
        // Check if user is member
        if (!$bar->hasMember($request->user())) {
            return response()->json([
                'message' => 'Not a member of this bar.',
            ], 403);
        }

        $perPage = $request->input('per_page', 50);
        $beforeId = $request->input('before_id');

        $messages = $this->messageService->getMessages($bar, $perPage, $beforeId);

        return response()->json($messages);
    }

    /**
     * Get messages since a specific ID (for polling)
     */
    public function since(Request $request, Bar $bar): JsonResponse
    {
        // Check if user is member
        if (!$bar->hasMember($request->user())) {
            return response()->json([
                'message' => 'Not a member of this bar.',
            ], 403);
        }

        $sinceId = $request->input('since_id', 0);
        $messages = $this->messageService->getMessagesSince($bar, $sinceId);

        return response()->json([
            'data' => $messages,
        ]);
    }

    /**
     * Send a message
     */
    public function store(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'reply_to_id' => 'nullable|exists:bar_messages,id',
        ]);

        try {
            $message = $this->messageService->sendMessage(
                $request->user(),
                $bar,
                $validated['content'],
                $validated['reply_to_id'] ?? null
            );

            return response()->json([
                'data' => $message,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a message
     */
    public function destroy(Request $request, Bar $bar, BarMessage $message): JsonResponse
    {
        if ($message->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Message not found in this bar.',
            ], 404);
        }

        try {
            $this->messageService->deleteMessage($request->user(), $message);

            return response()->json([
                'message' => 'Message deleted.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Add reaction to message
     */
    public function addReaction(Request $request, Bar $bar, BarMessage $message): JsonResponse
    {
        if ($message->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Message not found in this bar.',
            ], 404);
        }

        $validated = $request->validate([
            'emoji' => 'required|string|max:32',
        ]);

        try {
            $reaction = $this->messageService->addReaction(
                $request->user(),
                $message,
                $validated['emoji']
            );

            return response()->json([
                'data' => $reaction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(Request $request, Bar $bar, BarMessage $message): JsonResponse
    {
        if ($message->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Message not found in this bar.',
            ], 404);
        }

        $validated = $request->validate([
            'emoji' => 'required|string|max:32',
        ]);

        $this->messageService->removeReaction(
            $request->user(),
            $message,
            $validated['emoji']
        );

        return response()->json([
            'message' => 'Reaction removed.',
        ]);
    }
}
