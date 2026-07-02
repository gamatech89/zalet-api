<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StreamEndedEvent;
use App\Events\ViewerJoinedEvent;
use App\Events\ViewerLeftEvent;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\User;
use Agence104\LiveKit\WebhookReceiver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LiveKitWebhookController extends Controller
{
    /**
     * Handle incoming LiveKit webhook events.
     *
     * LiveKit signs each request with a JWT in the Authorization header.
     * The token's sha256 claim must match SHA256(body).
     *
     * POST /api/v1/webhooks/livekit
     */
    public function handle(Request $request): Response
    {
        $apiKey    = config('services.livekit.api_key', '');
        $apiSecret = config('services.livekit.api_secret', '');

        if (empty($apiKey) || empty($apiSecret)) {
            Log::warning('[LiveKit Webhook] API key/secret not configured — ignoring event.');
            return response('', 200);
        }

        try {
            $receiver = new WebhookReceiver($apiKey, $apiSecret);
            $event    = $receiver->receive(
                $request->getContent(),
                $request->header('Authorization')
            );
        } catch (\Exception $e) {
            Log::warning('[LiveKit Webhook] Auth failed: ' . $e->getMessage());
            return response('Unauthorized', 401);
        }

        $eventName = $event->getEvent();
        $room      = $event->getRoom();
        $roomName  = $room ? $room->getName() : null;

        Log::info("[LiveKit Webhook] event={$eventName} room={$roomName}");

        if (!$roomName) {
            return response('', 200);
        }

        // Room names follow the pattern "stream-{liveStreamId}"
        $streamId = str_replace('stream-', '', $roomName);
        $stream   = LiveStream::find($streamId);

        if (!$stream) {
            return response('', 200);
        }

        $session = $stream->currentSession;

        match ($eventName) {
            'participant_joined' => $this->onParticipantJoined($stream, $session, $event),
            'participant_left'   => $this->onParticipantLeft($stream, $session, $event),
            'room_finished'      => $this->onRoomFinished($stream),
            default              => null,
        };

        return response('', 200);
    }

    private function onParticipantJoined($stream, $session, $event): void
    {
        if (!$session) return;

        $participant = $event->getParticipant();
        $identity    = $participant ? $participant->getIdentity() : '';

        // Skip the streamer's own publisher identity
        if (str_starts_with($identity, 'streamer-')) {
            return;
        }

        $session->viewerJoined();
        $currentViewers = $session->fresh()->current_viewers;

        // Resolve username from identity "viewer-{userId}"
        $username = $this->resolveUsername($identity);

        broadcast(new ViewerJoinedEvent($stream->id, $username, $currentViewers));

        Log::info("[LiveKit Webhook] viewer joined stream={$stream->id} user={$username} current={$currentViewers}");
    }

    private function onParticipantLeft($stream, $session, $event): void
    {
        if (!$session) return;

        $participant = $event->getParticipant();
        $identity    = $participant ? $participant->getIdentity() : '';

        if (str_starts_with($identity, 'streamer-')) {
            return;
        }

        $session->viewerLeft();
        $currentViewers = $session->fresh()->current_viewers;

        $username = $this->resolveUsername($identity);

        broadcast(new ViewerLeftEvent($stream->id, $username, $currentViewers));

        Log::info("[LiveKit Webhook] viewer left stream={$stream->id} user={$username} current={$currentViewers}");
    }

    /**
     * Resolve a display name from a LiveKit participant identity.
     * Identity format: "viewer-{userUuid}-{rand}" (or legacy "viewer-{userUuid}")
     * and "guest-{random}".
     */
    private function resolveUsername(string $identity): string
    {
        if (preg_match('/^viewer-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $identity, $m)) {
            $user = User::find($m[1]);
            if ($user) return $user->username;
        }

        // Guest or unknown identity — return a friendly label
        return 'Gost';
    }

    private function onRoomFinished($stream): void
    {
        // If stream is still marked live, force-end it
        if ($stream->is_live) {
            $stream->update(['is_live' => false, 'livekit_room_name' => null]);
            $session = $stream->currentSession;
            if ($session && !$session->end_time) {
                $session->update([
                    'end_time'        => now(),
                    'current_viewers' => 0,
                ]);
            }
            broadcast(new StreamEndedEvent(
                $stream->id,
                $session?->fresh()?->getDurationMinutes(),
                (int) ($session?->peak_viewers ?? 0),
                (float) ($session?->total_coins_collected ?? 0),
            ));
            Log::info("[LiveKit Webhook] room_finished — stream {$stream->id} force-ended.");
        } else {
            // Room finished cleanly — still clear the room name
            $stream->update(['livekit_room_name' => null]);
        }
    }
}
