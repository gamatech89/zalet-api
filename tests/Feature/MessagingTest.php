<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'content' => 'Hello, world!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Hello, world!')
            ->assertJsonPath('data.sender.id', $user->id);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => 'Hello, world!',
        ]);
    }

    public function test_user_can_get_messages(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::factory()->create();
        $conversation->users()->attach([$user->id, $otherUser->id]);

        // Create some messages
        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'content', 'sender', 'created_at'],
                ],
                'meta',
            ]);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();

        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($participant->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_participant_cannot_read_messages(): void
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();

        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($participant->id);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }

    public function test_message_requires_content(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_can_send_typing_indicator(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/conversations/{$conversation->id}/typing");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Typing indicator sent.']);
    }

    public function test_messages_are_paginated(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id);

        Message::factory()->count(60)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 60)
            ->assertJsonPath('meta.last_page', 2);
    }
}
