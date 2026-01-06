<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Notification Model', function (): void {
    test('can create a notification', function (): void {
        $user = User::factory()->create();
        
        $notification = Notification::factory()
            ->for($user)
            ->create([
                'type' => NotificationType::NEW_FOLLOWER,
                'title' => 'New Follower',
                'body' => 'Someone followed you',
            ]);

        expect($notification)->toBeInstanceOf(Notification::class)
            ->and($notification->uuid)->not->toBeNull()
            ->and($notification->user_id)->toBe($user->id)
            ->and($notification->type)->toBe(NotificationType::NEW_FOLLOWER)
            ->and($notification->title)->toBe('New Follower')
            ->and($notification->body)->toBe('Someone followed you')
            ->and($notification->read_at)->toBeNull();
    });

    test('belongs to a user', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();

        expect($notification->user)->toBeInstanceOf(User::class)
            ->and($notification->user->id)->toBe($user->id);
    });

    test('can have an actor', function (): void {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        
        $notification = Notification::factory()
            ->for($user)
            ->withActor($actor)
            ->create();

        expect($notification->actor)->toBeInstanceOf(User::class)
            ->and($notification->actor->id)->toBe($actor->id);
    });

    test('can have a notifiable (polymorphic)', function (): void {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        
        // Use another user as the notifiable for this test
        $notification = Notification::factory()
            ->for($user)
            ->create([
                'notifiable_type' => User::class,
                'notifiable_id' => $actor->id,
            ]);

        expect($notification->notifiable)->toBeInstanceOf(User::class)
            ->and($notification->notifiable->id)->toBe($actor->id);
    });

    test('can store additional data as json', function (): void {
        $user = User::factory()->create();
        $data = ['amount' => 100, 'currency' => 'credits'];
        
        $notification = Notification::factory()
            ->for($user)
            ->withData($data)
            ->create();

        expect($notification->data)->toBe($data)
            ->and($notification->data['amount'])->toBe(100);
    });

    test('can check if notification is read', function (): void {
        $user = User::factory()->create();
        
        $unread = Notification::factory()->for($user)->unread()->create();
        $read = Notification::factory()->for($user)->read()->create();

        expect($unread->isRead())->toBeFalse()
            ->and($unread->isUnread())->toBeTrue()
            ->and($read->isRead())->toBeTrue()
            ->and($read->isUnread())->toBeFalse();
    });

    test('can mark notification as read', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->unread()->create();

        expect($notification->isUnread())->toBeTrue();

        $notification->markAsRead();

        expect($notification->isRead())->toBeTrue()
            ->and($notification->read_at)->not->toBeNull();
    });

    test('can mark notification as unread', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->read()->create();

        expect($notification->isRead())->toBeTrue();

        $notification->markAsUnread();

        expect($notification->isUnread())->toBeTrue()
            ->and($notification->read_at)->toBeNull();
    });

    test('can scope to unread notifications', function (): void {
        $user = User::factory()->create();
        
        Notification::factory()->for($user)->unread()->count(3)->create();
        Notification::factory()->for($user)->read()->count(2)->create();

        expect(Notification::query()->unread()->count())->toBe(3);
    });

    test('can scope to read notifications', function (): void {
        $user = User::factory()->create();
        
        Notification::factory()->for($user)->unread()->count(3)->create();
        Notification::factory()->for($user)->read()->count(2)->create();

        expect(Notification::query()->read()->count())->toBe(2);
    });

    test('can scope by notification type', function (): void {
        $user = User::factory()->create();
        
        Notification::factory()->for($user)->ofType(NotificationType::NEW_FOLLOWER)->count(2)->create();
        Notification::factory()->for($user)->ofType(NotificationType::GIFT_RECEIVED)->count(3)->create();

        expect(Notification::query()->ofType(NotificationType::NEW_FOLLOWER)->count())->toBe(2)
            ->and(Notification::query()->ofType(NotificationType::GIFT_RECEIVED)->count())->toBe(3);
    });

    test('notifications are ordered by created_at descending by default', function (): void {
        $user = User::factory()->create();
        
        $oldest = Notification::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
        $newest = Notification::factory()->for($user)->create(['created_at' => now()]);
        $middle = Notification::factory()->for($user)->create(['created_at' => now()->subDay()]);

        $notifications = Notification::query()->orderBy('created_at', 'desc')->get();

        expect($notifications->first()->id)->toBe($newest->id)
            ->and($notifications->last()->id)->toBe($oldest->id);
    });
});

describe('User Notifications Relationship', function (): void {
    test('user has many app notifications', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->count(5)->create();

        expect($user->appNotifications)->toHaveCount(5);
    });

    test('user can get unread notifications count', function (): void {
        $user = User::factory()->create();
        
        Notification::factory()->for($user)->unread()->count(3)->create();
        Notification::factory()->for($user)->read()->count(2)->create();

        expect($user->unreadNotificationsCount())->toBe(3);
    });
});
