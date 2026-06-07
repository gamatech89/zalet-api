<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_dm_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', [
                'user_ids' => [$otherUser->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'is_group', 'participants'],
            ]);

        $this->assertDatabaseHas('conversations', ['is_group' => false]);
    }

    public function test_user_can_create_group_conversation(): void
    {
        $user = User::factory()->create();
        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', [
                'user_ids' => [$other1->id, $other2->id],
                'name' => 'Test Group',
                'is_group' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_group', true)
            ->assertJsonPath('data.name', 'Test Group');
    }

    public function test_group_requires_name(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', [
                'user_ids' => [$otherUser->id],
                'is_group' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Group conversations require a name.']);
    }

    public function test_existing_dm_is_returned(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create existing DM
        $conversation = Conversation::factory()->create(['is_group' => false]);
        $conversation->users()->attach([$user->id, $otherUser->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', [
                'user_ids' => [$otherUser->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJson(['message' => 'Conversation already exists.']);
    }

    public function test_user_can_list_conversations(): void
    {
        $user = User::factory()->create();

        $conv1 = Conversation::factory()->create();
        $conv1->users()->attach($user->id);

        $conv2 = Conversation::factory()->group()->create();
        $conv2->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'is_group', 'participants', 'updated_at'],
                ],
                'meta',
            ]);
    }

    public function test_can_view_conversation_details(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::factory()->create();
        $conversation->users()->attach([$user->id, $otherUser->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $conversation->id);
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();

        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($participant->id);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_conversation_requires_user_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);
    }

    public function test_user_can_get_unread_conversations_count(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create a conversation
        $conversation = Conversation::factory()->create(['is_group' => false]);
        $conversation->users()->attach([
            $user->id => ['joined_at' => now(), 'last_read_at' => null],
            $otherUser->id => ['joined_at' => now(), 'last_read_at' => null],
        ]);

        // Send a message from otherUser
        $message = $conversation->messages()->create([
            'sender_id' => $otherUser->id,
            'content' => 'Hello there',
            'message_type' => 'text',
        ]);

        // Fetch unread count, should be 1
        $response = $this->actingAs($user)
            ->getJson('/api/v1/conversations/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        // Mark read (by loading messages)
        $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation->id}/messages");

        // Fetch unread count, should be 0
        $response = $this->actingAs($user)
            ->getJson('/api/v1/conversations/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }
}
