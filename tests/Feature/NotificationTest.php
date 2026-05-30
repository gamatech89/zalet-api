<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // === List Notifications ===

    public function test_can_list_notifications(): void
    {
        Notification::createForUser($this->user->id, 'follow', 'New follower', 'User X started following you', ['sender_id' => 'abc']);
        Notification::createForUser($this->user->id, 'like', 'New like', 'User Y liked your post');

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_are_ordered_newest_first(): void
    {
        $older = Notification::createForUser($this->user->id, 'follow', 'Older', null);
        $older->update(['created_at' => now()->subDay()]);

        Notification::createForUser($this->user->id, 'like', 'Newer', null);

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $otherUser = User::factory()->create();
        Notification::createForUser($otherUser->id, 'follow', 'Not yours', null);
        Notification::createForUser($this->user->id, 'gift', 'Yours', null);

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Yours');
    }

    public function test_requires_auth_to_list_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    }

    // === Unread Count ===

    public function test_can_get_unread_count(): void
    {
        Notification::createForUser($this->user->id, 'follow', 'Unread 1', null);
        Notification::createForUser($this->user->id, 'like', 'Unread 2', null);
        $readNotif = Notification::createForUser($this->user->id, 'gift', 'Read', null);
        $readNotif->markAsRead();

        $response = $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    // === Mark Single Read ===

    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::createForUser($this->user->id, 'comment', 'New comment', null);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('data.read_at', fn ($val) => $val !== null);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_others_notification_as_read(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::createForUser($otherUser->id, 'follow', 'Not yours', null);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertForbidden();
    }

    // === Mark All Read ===

    public function test_can_mark_all_as_read(): void
    {
        Notification::createForUser($this->user->id, 'follow', 'One', null);
        Notification::createForUser($this->user->id, 'like', 'Two', null);
        Notification::createForUser($this->user->id, 'comment', 'Three', null);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk();

        $unread = Notification::forUser($this->user->id)->unread()->count();
        $this->assertEquals(0, $unread);
    }

    public function test_mark_all_read_only_affects_own_notifications(): void
    {
        $otherUser = User::factory()->create();
        Notification::createForUser($otherUser->id, 'follow', 'Other', null);
        Notification::createForUser($this->user->id, 'like', 'Mine', null);

        $this->withToken($this->token)->postJson('/api/v1/notifications/read-all');

        // Other user's notification should still be unread
        $otherUnread = Notification::forUser($otherUser->id)->unread()->count();
        $this->assertEquals(1, $otherUnread);
    }
}
