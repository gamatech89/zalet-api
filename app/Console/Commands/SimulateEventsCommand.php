<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Enums\MediaProvider;
use App\Enums\MediaType;
use App\Models\Gift;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Achievements\Payloads\DailyLoginPayload;
use App\Services\Achievements\Payloads\FollowerGainedPayload;
use App\Services\Achievements\Payloads\GiftSentPayload;
use App\Services\Achievements\Payloads\MediaPostedPayload;
use App\Services\Achievements\Payloads\MessageSentPayload;
use App\Services\Achievements\Payloads\StreamStartedPayload;
use App\Services\Achievements\Payloads\UserFollowedPayload;
use Illuminate\Console\Command;

class SimulateEventsCommand extends Command
{
    protected $signature = 'achievements:simulate
        {email : User email}
        {event : Event type (message_sent, gift_sent, user_followed, follower_gained, stream_started, daily_login, media_posted)}
        {--count=1 : Number of events to fire}';

    protected $description = 'Simulate user events to test achievement progression';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $eventType = EventType::from($this->argument('event'));
        $count = (int) $this->option('count');

        $this->info("Simulating {$count}x {$eventType->value} for {$user->email}...");

        for ($i = 0; $i < $count; $i++) {
            $payload = match ($eventType) {
                EventType::MESSAGE_SENT => new MessageSentPayload(conversationId: 'simulated'),
                EventType::GIFT_SENT => new GiftSentPayload(
                    giftId: Gift::first()?->id ?? 'simulated',
                    recipientId: User::where('id', '!=', $user->id)->inRandomOrder()->first()?->id ?? 'simulated',
                    coinPrice: 10.0,
                ),
                EventType::USER_FOLLOWED => new UserFollowedPayload(
                    followedId: User::where('id', '!=', $user->id)->inRandomOrder()->first()?->id ?? 'simulated',
                ),
                EventType::FOLLOWER_GAINED => new FollowerGainedPayload(
                    followerId: User::where('id', '!=', $user->id)->inRandomOrder()->first()?->id ?? 'simulated',
                ),
                EventType::STREAM_STARTED => new StreamStartedPayload(),
                EventType::DAILY_LOGIN => new DailyLoginPayload(),
                EventType::MEDIA_POSTED => new MediaPostedPayload(
                    mediaType: MediaType::MOMENT,
                    provider: MediaProvider::NATIVE,
                ),
            };

            UserEvent::record($user, $eventType, $payload);
        }

        $this->info("Done. {$count} events created.");

        return self::SUCCESS;
    }
}
