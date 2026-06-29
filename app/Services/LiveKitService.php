<?php

namespace App\Services;

use App\Models\LiveStream;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use Agence104\LiveKit\RoomServiceClient;
use Agence104\LiveKit\IngressServiceClient;
use Livekit\IngressInput;
use Livekit\CreateIngressRequest;

class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $host;
    private string $wsUrl;

    public function __construct()
    {
        $this->apiKey = config('services.livekit.api_key', '');
        $this->apiSecret = config('services.livekit.api_secret', '');
        $this->host = config('services.livekit.host', 'https://your-livekit-host.livekit.cloud');
        $this->wsUrl = config('services.livekit.ws_url', 'wss://your-livekit-host.livekit.cloud');
    }

    /**
     * Generate a room name for a stream.
     */
    public function generateRoomName(LiveStream $stream): string
    {
        return 'stream-' . $stream->id;
    }

    /**
     * Create a LiveKit room for the stream.
     */
    public function createRoom(LiveStream $stream): string
    {
        $roomName = $this->generateRoomName($stream);

        if ($this->isConfigured()) {
            try {
                $roomService = new RoomServiceClient($this->host, $this->apiKey, $this->apiSecret);
                $options = (new \Agence104\LiveKit\RoomCreateOptions())
                    ->setName($roomName)
                    ->setEmptyTimeout(3600) // 1 hour — allows streamer reconnect after drop
                    ->setMaxParticipants(1000);
                $roomService->createRoom($options);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('LiveKit room creation failed, will be auto-created on join: ' . $e->getMessage());
            }
        }

        return $roomName;
    }

    /**
     * Generate a token for the streamer (publisher).
     */
    public function generatePublisherToken(LiveStream $stream, string $identity, string $name): string
    {
        $roomName = $stream->livekit_room_name ?? $this->generateRoomName($stream);

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($identity)
            ->setName($name)
            ->setTtl(3600 * 8); // 8 hour max stream

        $videoGrant = (new VideoGrant())
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish(true)
            ->setCanSubscribe(true)
            ->setCanPublishData(true);

        $token = new AccessToken($this->apiKey, $this->apiSecret);
        $token->init($tokenOptions)
            ->setGrant($videoGrant);

        return $token->toJwt();
    }

    /**
     * Generate a token for a viewer (subscriber only).
     */
    public function generateViewerToken(LiveStream $stream, string $identity, string $name): string
    {
        $roomName = $stream->livekit_room_name ?? $this->generateRoomName($stream);

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($identity)
            ->setName($name)
            ->setTtl(3600 * 8);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish(false)
            ->setCanSubscribe(true)
            ->setCanPublishData(true);

        $token = new AccessToken($this->apiKey, $this->apiSecret);
        $token->init($tokenOptions)
            ->setGrant($videoGrant);

        return $token->toJwt();
    }

    /**
     * Create an RTMP ingress for OBS streaming.
     */
    public function createRtmpIngress(LiveStream $stream, string $participantName): ?array
    {
        if (!$this->isConfigured()) {
            return $this->getFallbackIngressInfo($stream);
        }

        try {
            $ingressService = new IngressServiceClient($this->host, $this->apiKey, $this->apiSecret);
            $roomName = $stream->livekit_room_name ?? $this->generateRoomName($stream);

            $ingress = $ingressService->createIngress(
                inputType: IngressInput::RTMP_INPUT,
                name: 'stream-' . $stream->id,
                roomName: $roomName,
                participantIdentity: 'streamer-' . $stream->user_id,
                participantName: $participantName,
            );

            return [
                'ingress_id' => $ingress->getIngressId(),
                'url' => $ingress->getUrl(),
                'stream_key' => $ingress->getStreamKey(),
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LiveKit RTMP ingress creation failed: ' . $e->getMessage());
            return $this->getFallbackIngressInfo($stream);
        }
    }

    /**
     * Get the WebSocket URL for client connections.
     */
    public function getWsUrl(): string
    {
        return $this->wsUrl;
    }

    /**
     * Check if LiveKit is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey)
            && !empty($this->apiSecret)
            && $this->apiKey !== 'your-api-key';
    }

    /**
     * Fallback ingress info when LiveKit is not yet configured.
     */
    private function getFallbackIngressInfo(LiveStream $stream): array
    {
        return [
            'ingress_id' => null,
            'url' => 'rtmp://stream.zalet.app/live',
            'stream_key' => $stream->stream_key,
        ];
    }

    /**
     * Delete a room when stream ends.
     */
    public function deleteRoom(LiveStream $stream): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        try {
            $roomName = $stream->livekit_room_name ?? $this->generateRoomName($stream);
            $roomService = new RoomServiceClient($this->host, $this->apiKey, $this->apiSecret);
            $roomService->deleteRoom($roomName);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('LiveKit room deletion failed: ' . $e->getMessage());
        }
    }
}
