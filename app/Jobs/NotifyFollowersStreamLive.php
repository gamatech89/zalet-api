<?php

namespace App\Jobs;

use App\Events\NewNotificationEvent;
use App\Models\LiveStream;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class NotifyFollowersStreamLive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LiveStream $stream) {}

    public function handle(): void
    {
        $streamer = $this->stream->user;
        if (!$streamer || !$this->stream->is_live) {
            return;
        }

        // Anti-spam: at most one go-live blast per creator per 30 minutes
        if (!Cache::add("golive-notif:{$streamer->id}", 1, now()->addMinutes(30))) {
            return;
        }

        $title = "🔴 {$streamer->username} je uživo";
        $data  = [
            'live_stream_id'  => $this->stream->id,
            'stream_mode'     => $this->stream->stream_mode,
            'sender_username' => $streamer->username,
        ];

        $streamer->followers()
            ->select('users.id', 'users.username')
            ->chunkById(500, function ($followers) use ($title, $data) {
                foreach ($followers as $follower) {
                    $notification = Notification::create([
                        'user_id' => $follower->id,
                        'type'    => 'stream_live',
                        'title'   => $title,
                        'body'    => $this->stream->title,
                        'data'    => $data,
                    ]);
                    broadcast(new NewNotificationEvent($follower, $notification));
                }
            }, 'users.id', 'id');
    }
}
