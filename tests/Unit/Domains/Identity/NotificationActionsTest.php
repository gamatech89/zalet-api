<?php

declare(strict_types=1);

use App\Domains\Identity\Actions\CreateNotificationAction;
use App\Domains\Identity\Actions\DeleteNotificationAction;
use App\Domains\Identity\Actions\GetUserNotificationsAction;
use App\Domains\Identity\Actions\MarkNotificationsReadAction;
use App\Domains\Identity\Events\NotificationSent;
use App\Domains\Identity\Events\NotificationsRead;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('CreateNotificationAction', function (): void {
    test('creates a notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->execute(
            user: $user,
            type: NotificationType::NEW_FOLLOWER,
            title: 'New Follower',
            body: 'Someone followed you',
        );

        expect($notification)->toBeInstanceOf(Notification::class)
            ->and($notification->user_id)->toBe($user->id)
            ->and($notification->type)->toBe(NotificationType::NEW_FOLLOWER)
            ->and($notification->title)->toBe('New Follower');

        Event::assertDispatched(NotificationSent::class);
    });

    test('creates notification with actor', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->execute(
            user: $user,
            type: NotificationType::NEW_FOLLOWER,
            title: 'New Follower',
            actor: $actor,
        );

        expect($notification->actor_id)->toBe($actor->id);
    });

    test('creates notification with notifiable', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->execute(
            user: $user,
            type: NotificationType::POST_LIKED,
            title: 'Post Liked',
            notifiable: $actor,
        );

        expect($notification->notifiable_type)->toBe(User::class)
            ->and($notification->notifiable_id)->toBe($actor->id);
    });

    test('creates notification with additional data', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $action = new CreateNotificationAction();
        $data = ['amount' => 100, 'gift_type' => 'rose'];

        $notification = $action->execute(
            user: $user,
            type: NotificationType::GIFT_RECEIVED,
            title: 'Gift Received',
            data: $data,
        );

        expect($notification->data)->toBe($data);
    });

    test('can skip broadcasting', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $action = new CreateNotificationAction();

        $action->execute(
            user: $user,
            type: NotificationType::NEW_FOLLOWER,
            title: 'New Follower',
            broadcast: false,
        );

        Event::assertNotDispatched(NotificationSent::class);
    });

    test('creates follow request notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $requester = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->followRequest($user, $requester);

        expect($notification->type)->toBe(NotificationType::FOLLOW_REQUEST)
            ->and($notification->actor_id)->toBe($requester->id);
    });

    test('creates follow accepted notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $followedUser = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->followAccepted($user, $followedUser);

        expect($notification->type)->toBe(NotificationType::FOLLOW_ACCEPTED)
            ->and($notification->actor_id)->toBe($followedUser->id);
    });

    test('creates new follower notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $follower = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->newFollower($user, $follower);

        expect($notification->type)->toBe(NotificationType::NEW_FOLLOWER)
            ->and($notification->actor_id)->toBe($follower->id);
    });

    test('creates gift received notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->giftReceived($user, $sender, 'Rose', 50);

        expect($notification->type)->toBe(NotificationType::GIFT_RECEIVED)
            ->and($notification->actor_id)->toBe($sender->id)
            ->and($notification->data['gift_name'])->toBe('Rose')
            ->and($notification->data['credits_value'])->toBe(50);
    });

    test('creates duel invite notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $host = User::factory()->create();
        $action = new CreateNotificationAction();

        $notification = $action->duelInvite($user, $host);

        expect($notification->type)->toBe(NotificationType::DUEL_INVITE)
            ->and($notification->actor_id)->toBe($host->id);
    });

    test('creates system announcement notification', function (): void {
        Event::fake([NotificationSent::class]);
        
        $user = User::factory()->create();
        $action = new CreateNotificationAction();

        $notifications = $action->systemAnnouncement([$user], 'Maintenance Notice', 'System will be down for maintenance.');

        expect($notifications)->toHaveCount(1)
            ->and($notifications[0]->type)->toBe(NotificationType::SYSTEM_ANNOUNCEMENT)
            ->and($notifications[0]->title)->toBe('Maintenance Notice')
            ->and($notifications[0]->body)->toBe('System will be down for maintenance.');
    });
});

describe('GetUserNotificationsAction', function (): void {
    test('gets paginated notifications for user', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->count(25)->create();
        
        $action = new GetUserNotificationsAction();
        $result = $action->execute($user);

        expect($result->count())->toBe(20) // Default per page
            ->and($result->total())->toBe(25);
    });

    test('filters by unread status', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->count(3)->create();
        Notification::factory()->for($user)->read()->count(2)->create();
        
        $action = new GetUserNotificationsAction();
        $result = $action->execute($user, unreadOnly: true);

        expect($result->total())->toBe(3);
    });

    test('filters by notification type', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->ofType(NotificationType::NEW_FOLLOWER)->count(3)->create();
        Notification::factory()->for($user)->ofType(NotificationType::GIFT_RECEIVED)->count(2)->create();
        
        $action = new GetUserNotificationsAction();
        $result = $action->execute($user, type: NotificationType::NEW_FOLLOWER);

        expect($result->total())->toBe(3);
    });

    test('gets unread count', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->count(5)->create();
        Notification::factory()->for($user)->read()->count(3)->create();
        
        $action = new GetUserNotificationsAction();

        expect($action->unreadCount($user))->toBe(5);
    });

    test('counts notifications by type', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->ofType(NotificationType::NEW_FOLLOWER)->count(3)->create();
        Notification::factory()->for($user)->ofType(NotificationType::GIFT_RECEIVED)->count(2)->create();
        
        $action = new GetUserNotificationsAction();
        $counts = $action->countByType($user);

        expect($counts[NotificationType::NEW_FOLLOWER->value])->toBe(3)
            ->and($counts[NotificationType::GIFT_RECEIVED->value])->toBe(2);
    });
});

describe('MarkNotificationsReadAction', function (): void {
    test('marks single notification as read', function (): void {
        Event::fake([NotificationsRead::class]);
        
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->unread()->create();
        
        $action = new MarkNotificationsReadAction();
        $result = $action->execute($notification);

        expect($result->isRead())->toBeTrue();
        Event::assertDispatched(NotificationsRead::class);
    });

    test('marks multiple notifications as read', function (): void {
        Event::fake([NotificationsRead::class]);
        
        $user = User::factory()->create();
        $notifications = Notification::factory()->for($user)->unread()->count(3)->create();
        $uuids = $notifications->pluck('uuid')->toArray();
        
        $action = new MarkNotificationsReadAction();
        $count = $action->markMultiple($user, $uuids);

        expect($count)->toBe(3);
        Event::assertDispatched(NotificationsRead::class);
    });

    test('marks all notifications as read', function (): void {
        Event::fake([NotificationsRead::class]);
        
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->count(5)->create();
        
        $action = new MarkNotificationsReadAction();
        $count = $action->markAllRead($user);

        expect($count)->toBe(5)
            ->and($user->unreadNotificationsCount())->toBe(0);
        Event::assertDispatched(NotificationsRead::class);
    });

    test('can skip broadcasting on mark read', function (): void {
        Event::fake([NotificationsRead::class]);
        
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->unread()->create();
        
        $action = new MarkNotificationsReadAction();
        $action->execute($notification, broadcast: false);

        Event::assertNotDispatched(NotificationsRead::class);
    });
});

describe('DeleteNotificationAction', function (): void {
    test('deletes single notification', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();
        $notificationId = $notification->id;
        
        $action = new DeleteNotificationAction();
        $result = $action->execute($notification);

        expect($result)->toBeTrue()
            ->and(Notification::find($notificationId))->toBeNull();
    });

    test('deletes multiple notifications', function (): void {
        $user = User::factory()->create();
        $notifications = Notification::factory()->for($user)->count(3)->create();
        $uuids = $notifications->pluck('uuid')->toArray();
        
        $action = new DeleteNotificationAction();
        $count = $action->deleteMultiple($user, $uuids);

        expect($count)->toBe(3)
            ->and($user->appNotifications()->count())->toBe(0);
    });

    test('deletes all read notifications', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->read()->count(3)->create();
        Notification::factory()->for($user)->unread()->count(2)->create();
        
        $action = new DeleteNotificationAction();
        $count = $action->deleteAllRead($user);

        expect($count)->toBe(3)
            ->and($user->appNotifications()->count())->toBe(2);
    });

    test('deletes notifications older than date', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->create(['created_at' => now()->subDays(31)]);
        Notification::factory()->for($user)->create(['created_at' => now()->subDays(35)]);
        Notification::factory()->for($user)->create(['created_at' => now()->subDays(5)]);
        
        $action = new DeleteNotificationAction();
        $count = $action->deleteOlderThan($user, now()->subDays(30));

        expect($count)->toBe(2)
            ->and($user->appNotifications()->count())->toBe(1);
    });
});
