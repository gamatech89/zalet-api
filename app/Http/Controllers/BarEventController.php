<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Models\BarEvent;
use App\Services\BarEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarEventController extends Controller
{
    public function __construct(
        private BarEventService $eventService
    ) {}

    /**
     * Get events for a bar
     */
    public function index(Request $request, Bar $bar): JsonResponse
    {
        $status = $request->input('status');
        $events = $this->eventService->getBarEvents($bar, $status);

        return response()->json($events);
    }

    /**
     * Get all upcoming events
     */
    public function upcoming(Request $request): JsonResponse
    {
        $events = $this->eventService->getUpcomingEvents();

        return response()->json($events);
    }

    /**
     * Get all live events
     */
    public function live(Request $request): JsonResponse
    {
        $events = $this->eventService->getLiveEvents();

        return response()->json($events);
    }

    /**
     * Get current user's events
     */
    public function myEvents(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $events = $this->eventService->getUserEvents($request->user(), $status);

        return response()->json([
            'data' => $events,
        ]);
    }

    /**
     * Create a new event
     */
    public function store(Request $request, Bar $bar): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|url',
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $event = $this->eventService->createEvent($request->user(), $bar, $validated);

            return response()->json([
                'data' => $event,
                'message' => 'Event created successfully!',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get single event
     */
    public function show(Bar $bar, BarEvent $event): JsonResponse
    {
        if ($event->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Event not found in this bar.',
            ], 404);
        }

        $event->load([
            'host.profile',
            'bar:id,name,slug,cover_image_url',
            'streamSession',
        ]);

        return response()->json([
            'data' => $event,
        ]);
    }

    /**
     * Update event
     */
    public function update(Request $request, Bar $bar, BarEvent $event): JsonResponse
    {
        if ($event->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Event not found in this bar.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|url',
            'scheduled_at' => 'date|after:now',
        ]);

        try {
            $event = $this->eventService->updateEvent($request->user(), $event, $validated);

            return response()->json([
                'data' => $event,
                'message' => 'Event updated successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Start event (go live)
     */
    public function start(Request $request, Bar $bar, BarEvent $event): JsonResponse
    {
        if ($event->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Event not found in this bar.',
            ], 404);
        }

        $validated = $request->validate([
            'stream_session_id' => 'nullable|exists:stream_sessions,id',
        ]);

        try {
            $event = $this->eventService->startEvent(
                $request->user(),
                $event,
                $validated['stream_session_id'] ?? null
            );

            return response()->json([
                'data' => $event,
                'message' => 'Event started! You are now live!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * End event
     */
    public function end(Request $request, Bar $bar, BarEvent $event): JsonResponse
    {
        if ($event->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Event not found in this bar.',
            ], 404);
        }

        try {
            $event = $this->eventService->endEvent($request->user(), $event);

            return response()->json([
                'data' => $event,
                'message' => 'Event ended.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel event
     */
    public function cancel(Request $request, Bar $bar, BarEvent $event): JsonResponse
    {
        if ($event->bar_id !== $bar->id) {
            return response()->json([
                'message' => 'Event not found in this bar.',
            ], 404);
        }

        try {
            $event = $this->eventService->cancelEvent($request->user(), $event);

            return response()->json([
                'data' => $event,
                'message' => 'Event cancelled.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
