<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Domains\Identity\Models\User;
use App\Domains\Streaming\Models\StreamSession;

class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $host;
    private int $tokenTtlViewer;
    private int $tokenTtlStreamer;

    public function __construct()
    {
        $this->apiKey = config('livekit.api_key');
        $this->apiSecret = config('livekit.api_secret');
        $this->host = config('livekit.host');
        $this->tokenTtlViewer = config('livekit.token_ttl_viewer', 14400); // 4 hours
        $this->tokenTtlStreamer = config('livekit.token_ttl_streamer', 28800); // 8 hours
    }

    /**
     * Generate a token for a streamer (publisher)
     */
    public function generateStreamerToken(User $user, string $roomName): array
    {
        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($user->id . ':' . ($user->profile?->username ?? 'user'))
            ->setName($user->profile?->display_name ?? $user->email)
            ->setTtl($this->tokenTtlStreamer);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish(true)
            ->setCanPublishData(true)
            ->setCanSubscribe(true);

        $token = (new AccessToken($this->apiKey, $this->apiSecret))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        return [
            'token' => $token,
            'room_name' => $roomName,
            'ws_url' => config('livekit.ws_url'),
            'identity' => $tokenOptions->getIdentity(),
            'expires_in' => $this->tokenTtlStreamer,
        ];
    }

    /**
     * Generate a token for a viewer (subscriber only)
     */
    public function generateViewerToken(User $user, string $roomName): array
    {
        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($user->id . ':viewer:' . ($user->profile?->username ?? 'user'))
            ->setName($user->profile?->display_name ?? $user->email)
            ->setTtl($this->tokenTtlViewer);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish(false)  // Viewers can't publish
            ->setCanPublishData(true)  // But can send chat messages
            ->setCanSubscribe(true);

        $token = (new AccessToken($this->apiKey, $this->apiSecret))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        return [
            'token' => $token,
            'room_name' => $roomName,
            'ws_url' => config('livekit.ws_url'),
            'identity' => $tokenOptions->getIdentity(),
            'expires_in' => $this->tokenTtlViewer,
        ];
    }

    /**
     * Generate a unique room name for a stream session
     */
    public function generateRoomName(StreamSession $session): string
    {
        return 'stream_' . $session->id . '_' . substr(md5($session->created_at->timestamp), 0, 8);
    }

    /**
     * Generate room name for a duel
     */
    public function generateDuelRoomName(int $duelId): string
    {
        return 'duel_' . $duelId;
    }

    /**
     * Generate room name for a bar event
     */
    public function generateBarEventRoomName(int $barId, int $eventId): string
    {
        return 'bar_' . $barId . '_event_' . $eventId;
    }

    /**
     * Get LiveKit server info
     */
    public function getServerInfo(): array
    {
        return [
            'host' => $this->host,
            'ws_url' => config('livekit.ws_url'),
        ];
    }
}
