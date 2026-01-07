<?php

namespace App\Services;

use App\Models\Bar;
use App\Models\BarEvent;
use App\Domains\Identity\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class BarEventService
{
    public function __construct(
        private LevelService $levelService
    ) {}

    /**
     * Create a new event
     */
    public function createEvent(User $host, Bar $bar, array $data): BarEvent
    {
        // Check if user is owner (only owner can create events for now)
        if ($bar->owner_id !== $host->id) {
            throw new \Exception('Only bar owner can create events.');
        }

        $event = BarEvent::create([
            'bar_id' => $bar->id,
            'host_id' => $host->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'scheduled',
        ]);

        // Award XP for creating event
        $this->levelService->awardCreateEventXp($host);

        return $event->load(['host.profile', 'bar:id,name,slug']);
    }

    /**
     * Update an event
     */
    public function updateEvent(User $user, BarEvent $event, array $data): BarEvent
    {
        if ($event->host_id !== $user->id && $event->bar->owner_id !== $user->id) {
            throw new \Exception('Not authorized to update this event.');
        }

        if ($event->status !== 'scheduled') {
            throw new \Exception('Can only update scheduled events.');
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['cover_image_url'])) {
            $updateData['cover_image_url'] = $data['cover_image_url'];
        }
        if (isset($data['scheduled_at'])) {
            $updateData['scheduled_at'] = $data['scheduled_at'];
        }

        $event->update($updateData);
        return $event->fresh()->load(['host.profile', 'bar:id,name,slug']);
    }

    /**
     * Start an event (go live)
     */
    public function startEvent(User $user, BarEvent $event, ?int $streamSessionId = null): BarEvent
    {
        if ($event->host_id !== $user->id) {
            throw new \Exception('Only host can start the event.');
        }

        if ($event->status !== 'scheduled') {
            throw new \Exception('Event is not in scheduled status.');
        }

        // Check if bar already has a live event
        if ($event->bar->getLiveEvent()) {
            throw new \Exception('Bar already has a live event.');
        }

        $event->start($streamSessionId);

        // Award XP for hosting
        $this->levelService->awardHostStreamXp($user);

        return $event->fresh()->load(['host.profile', 'bar:id,name,slug', 'streamSession']);
    }

    /**
     * End an event
     */
    public function endEvent(User $user, BarEvent $event): BarEvent
    {
        if ($event->host_id !== $user->id && $event->bar->owner_id !== $user->id) {
            throw new \Exception('Not authorized to end this event.');
        }

        if ($event->status !== 'live') {
            throw new \Exception('Event is not live.');
        }

        $event->end();

        return $event->fresh();
    }

    /**
     * Cancel an event
     */
    public function cancelEvent(User $user, BarEvent $event): BarEvent
    {
        if ($event->host_id !== $user->id && $event->bar->owner_id !== $user->id) {
            throw new \Exception('Not authorized to cancel this event.');
        }

        if (!in_array($event->status, ['scheduled'])) {
            throw new \Exception('Can only cancel scheduled events.');
        }

        $event->cancel();

        return $event->fresh();
    }

    /**
     * Get bar events
     */
    public function getBarEvents(Bar $bar, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = BarEvent::where('bar_id', $bar->id)
            ->with(['host.profile']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('scheduled_at')->paginate($perPage);
    }

    /**
     * Get upcoming events (across all bars)
     */
    public function getUpcomingEvents(int $perPage = 20): LengthAwarePaginator
    {
        return BarEvent::where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with([
                'host.profile',
                'bar:id,name,slug,cover_image_url',
            ])
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    /**
     * Get live events (across all bars)
     */
    public function getLiveEvents(int $perPage = 20): LengthAwarePaginator
    {
        return BarEvent::where('status', 'live')
            ->with([
                'host.profile',
                'bar:id,name,slug,cover_image_url',
                'streamSession',
            ])
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    /**
     * Get user's upcoming events (as host)
     */
    public function getUserEvents(User $user, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = BarEvent::where('host_id', $user->id)
            ->with(['bar:id,name,slug']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('scheduled_at')->get();
    }
}
