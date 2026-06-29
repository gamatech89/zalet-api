<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
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

        Log::info("[LiveKit Webhook] viewer joined stream={$stream->id} current={$session->fresh()->current_viewers}");
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

        Log::info("[LiveKit Webhook] viewer left stream={$stream->id} current={$session->fresh()->current_viewers}");
    }

    private function onRoomFinished($stream): void
    {
        // If stream is still marked live, force-end it
        if ($stream->is_live) {
            $stream->update(['is_live' => false]);
            $session = $stream->currentSession;
            if ($session && !$session->end_time) {
                $session->update([
                    'end_time'        => now(),
                    'current_viewers' => 0,
                ]);
            }
            Log::info("[LiveKit Webhook] room_finished — stream {$stream->id} force-ended.");
        }
    }
}
