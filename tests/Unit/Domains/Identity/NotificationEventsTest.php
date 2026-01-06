<?php

declare(strict_types=1);

use App\Domains\Identity\Events\NotificationSent;
use App\Domains\Identity\Events\NotificationsRead;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('NotificationSent Event', function (): void {
    test('broadcasts on user private notification channel', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->unread()->create();

        $event = new NotificationSent($notification);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-notifications.'.$user->id);
    });

    test('has correct broadcast name', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();

        $event = new NotificationSent($notification);

        expect($event->broadcastAs())->toBe('notification.sent');
    });

    test('broadcasts notification data', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()
            ->for($user)
            ->create([
                'type' => NotificationType::NEW_FOLLOWER,
                'title' => 'New Follower',
            ]);

        $event = new NotificationSent($notification);
        $data = $event->broadcastWith();

        expect($data)->toHaveKey('notification')
            ->and($data['notification']->resolve()['type'])->toBe(NotificationType::NEW_FOLLOWER->value);
    });

    test('only broadcasts unread notifications', function (): void {
        $user = User::factory()->create();
        
        $unread = Notification::factory()->for($user)->unread()->create();
        $read = Notification::factory()->for($user)->read()->create();

        $unreadEvent = new NotificationSent($unread);
        $readEvent = new NotificationSent($read);

        expect($unreadEvent->broadcastWhen())->toBeTrue()
            ->and($readEvent->broadcastWhen())->toBeFalse();
    });
});

describe('NotificationsRead Event', function (): void {
    test('broadcasts on user private notification channel', function (): void {
        $userId = 123;
        $event = new NotificationsRead($userId, ['uuid-1', 'uuid-2'], 5);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-notifications.123');
    });

    test('has correct broadcast name', function (): void {
        $event = new NotificationsRead(1, [], 0);

        expect($event->broadcastAs())->toBe('notifications.read');
    });

    test('broadcasts notification uuids and unread count', function (): void {
        $uuids = ['uuid-1', 'uuid-2'];
        $event = new NotificationsRead(1, $uuids, 3);
        $data = $event->broadcastWith();

        expect($data['notification_uuids'])->toBe($uuids)
            ->and($data['unread_count'])->toBe(3)
            ->and($data['all_read'])->toBeFalse();
    });

    test('indicates when all notifications marked as read', function (): void {
        $event = new NotificationsRead(1, [], 0);
        $data = $event->broadcastWith();

        expect($data['all_read'])->toBeTrue()
            ->and($data['unread_count'])->toBe(0);
    });
});
