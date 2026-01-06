<?php

declare(strict_types=1);

use App\Domains\Identity\Events\NotificationSent;
use App\Domains\Identity\Events\NotificationsRead;
use App\Domains\Identity\Models\Notification;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([NotificationSent::class, NotificationsRead::class]);
});

describe('GET /api/v1/notifications', function (): void {
    test('returns paginated notifications for authenticated user', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->count(5)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'type',
                        'title',
                        'body',
                        'actor',
                        'data',
                        'isRead',
                        'createdAt',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonCount(5, 'data');
    });

    test('filters by unread only', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->count(3)->create();
        Notification::factory()->for($user)->read()->count(2)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications?unread_only=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('filters by notification type', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->ofType(NotificationType::NEW_FOLLOWER)->count(2)->create();
        Notification::factory()->for($user)->ofType(NotificationType::GIFT_RECEIVED)->count(3)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications?type=' . NotificationType::NEW_FOLLOWER->value);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('returns empty for user with no notifications', function (): void {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    });

    test('only returns own notifications', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        Notification::factory()->for($user)->count(3)->create();
        Notification::factory()->for($otherUser)->count(5)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('GET /api/v1/notifications/summary', function (): void {
    test('returns unread count and counts by type', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->ofType(NotificationType::NEW_FOLLOWER)->count(2)->create();
        Notification::factory()->for($user)->unread()->ofType(NotificationType::GIFT_RECEIVED)->count(3)->create();
        Notification::factory()->for($user)->read()->count(1)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'unreadCount',
                    'countByType',
                ],
            ])
            ->assertJsonPath('data.unreadCount', 5);
    });

    test('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/notifications/summary');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/notifications/{uuid}', function (): void {
    test('returns notification details', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()
            ->for($user)
            ->create([
                'type' => NotificationType::NEW_FOLLOWER,
                'title' => 'New Follower',
                'body' => 'Someone followed you',
            ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $notification->uuid)
            ->assertJsonPath('data.type', NotificationType::NEW_FOLLOWER->value)
            ->assertJsonPath('data.title', 'New Follower');
    });

    test('returns 404 for non-existent notification', function (): void {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/99999999-9999-9999-9999-999999999999');

        $response->assertNotFound();
    });

    test('returns 404 for notification belonging to another user', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->for($otherUser)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertNotFound(); // Returns 404 not 403 because query filters by user_id
    });

    test('requires authentication', function (): void {
        $notification = Notification::factory()->create();

        $response = $this->getJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/notifications/{uuid}/read', function (): void {
    test('marks notification as read', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->unread()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/' . $notification->uuid . '/read');

        $response->assertOk()
            ->assertJsonPath('data.isRead', true);

        expect($notification->fresh()->isRead())->toBeTrue();
        Event::assertDispatched(NotificationsRead::class);
    });

    test('returns 404 for notification belonging to another user', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->for($otherUser)->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/' . $notification->uuid . '/read');

        $response->assertNotFound(); // Returns 404 not 403 because query filters by user_id
    });

    test('requires authentication', function (): void {
        $notification = Notification::factory()->create();

        $response = $this->postJson('/api/v1/notifications/' . $notification->uuid . '/read');

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/notifications/read', function (): void {
    test('marks multiple notifications as read', function (): void {
        $user = User::factory()->create();
        $notifications = Notification::factory()->for($user)->unread()->count(3)->create();
        $uuids = $notifications->pluck('uuid')->toArray();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/read', [
            'uuids' => $uuids,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.markedCount', 3);

        foreach ($notifications as $notification) {
            expect($notification->fresh()->isRead())->toBeTrue();
        }
    });

    test('marks all notifications as read when all=true', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->unread()->count(5)->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/read', [
            'all' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.markedCount', 5);

        expect($user->unreadNotificationsCount())->toBe(0);
    });

    test('requires authentication', function (): void {
        $response = $this->postJson('/api/v1/notifications/read');

        $response->assertUnauthorized();
    });
});

describe('DELETE /api/v1/notifications/{uuid}', function (): void {
    test('deletes notification', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();
        $uuid = $notification->uuid;
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/notifications/' . $uuid);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Notification deleted');

        expect(Notification::where('uuid', $uuid)->exists())->toBeFalse();
    });

    test('returns 404 for notification belonging to another user', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->for($otherUser)->create();
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertNotFound(); // Returns 404 not 403 because query filters by user_id
    });

    test('requires authentication', function (): void {
        $notification = Notification::factory()->create();

        $response = $this->deleteJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertUnauthorized();
    });
});

describe('DELETE /api/v1/notifications', function (): void {
    test('deletes multiple notifications', function (): void {
        $user = User::factory()->create();
        $notifications = Notification::factory()->for($user)->count(3)->create();
        $uuids = $notifications->pluck('uuid')->toArray();
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/notifications', [
            'uuids' => $uuids,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deletedCount', 3);

        expect($user->appNotifications()->count())->toBe(0);
    });

    test('deletes all read notifications when all_read=true', function (): void {
        $user = User::factory()->create();
        Notification::factory()->for($user)->read()->count(3)->create();
        Notification::factory()->for($user)->unread()->count(2)->create();
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/notifications', [
            'all_read' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deletedCount', 3);

        expect($user->appNotifications()->count())->toBe(2);
    });

    test('requires authentication', function (): void {
        $response = $this->deleteJson('/api/v1/notifications');

        $response->assertUnauthorized();
    });
});

describe('Notification Resource', function (): void {
    test('includes actor when present', function (): void {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $notification = Notification::factory()
            ->for($user)
            ->withActor($actor)
            ->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertOk()
            ->assertJsonPath('data.actor.uuid', $actor->uuid);
    });

    test('includes type metadata', function (): void {
        $user = User::factory()->create();
        $notification = Notification::factory()
            ->for($user)
            ->ofType(NotificationType::NEW_FOLLOWER)
            ->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/' . $notification->uuid);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'typeLabel',
                    'icon',
                ],
            ]);
    });
});
