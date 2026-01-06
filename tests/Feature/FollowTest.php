<?php

declare(strict_types=1);

use App\Domains\Identity\Models\Follow;
use App\Domains\Identity\Models\Profile;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    Profile::factory()->create(['user_id' => $this->user->id]);

    $this->targetUser = User::factory()->create();
    Profile::factory()->create(['user_id' => $this->targetUser->id]);
});

describe('Follow User', function (): void {
    test('authenticated user can follow another user', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'follower_id',
                    'following_id',
                    'is_pending',
                    'accepted_at',
                    'created_at',
                ],
            ]);

        expect(Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->exists())->toBeTrue();
    });

    test('following a private profile creates a pending request', function (): void {
        $this->targetUser->profile->update(['is_private' => true]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(201)
            ->assertJsonPath('data.is_pending', true);

        $follow = Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->first();
        expect($follow)->not->toBeNull();
        expect($follow->accepted_at)->toBeNull();
    });

    test('following a public profile auto-accepts', function (): void {
        $this->targetUser->profile->update(['is_private' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(201)
            ->assertJsonPath('data.is_pending', false);

        $follow = Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->first();
        expect($follow)->not->toBeNull();
        expect($follow->accepted_at)->not->toBeNull();
    });

    test('user cannot follow themselves', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/users/{$this->user->uuid}/follow");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'You cannot follow yourself.');
    });

    test('user cannot follow same user twice', function (): void {
        // First follow
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Already following this user.');
    });

    test('guest cannot follow users', function (): void {
        $response = $this->postJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(401);
    });
});

describe('Unfollow User', function (): void {
    beforeEach(function (): void {
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => now(),
        ]);
    });

    test('user can unfollow another user', function (): void {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Successfully unfollowed user.');

        expect(Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->exists())->toBeFalse();
    });

    test('user can cancel pending follow request', function (): void {
        // Update to pending
        Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->update(['accepted_at' => null]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(200);
        expect(Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->exists())->toBeFalse();
    });

    test('returns error when not following user', function (): void {
        Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->delete();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Not following this user.');
    });

    test('guest cannot unfollow users', function (): void {
        $response = $this->deleteJson("/api/v1/users/{$this->targetUser->uuid}/follow");

        $response->assertStatus(401);
    });
});

describe('Followers List', function (): void {
    beforeEach(function (): void {
        // Create followers
        $this->followers = [];
        for ($i = 0; $i < 3; $i++) {
            $follower = User::factory()->create();
            Profile::factory()->create(['user_id' => $follower->id]);
            Follow::create([
                'follower_id' => $follower->id,
                'following_id' => $this->targetUser->id,
                'accepted_at' => now(),
            ]);
            $this->followers[] = $follower;
        }

        // Add a pending follower (shouldn't show in list)
        $this->pendingFollower = User::factory()->create();
        Profile::factory()->create(['user_id' => $this->pendingFollower->id]);
        Follow::create([
            'follower_id' => $this->pendingFollower->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => null,
        ]);
    });

    test('can get followers list', function (): void {
        $response = $this->getJson("/api/v1/users/{$this->targetUser->uuid}/followers");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('pending followers are not included in list', function (): void {
        $response = $this->getJson("/api/v1/users/{$this->targetUser->uuid}/followers");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('uuid');
        expect($ids)->not->toContain($this->pendingFollower->uuid);
    });

    test('followers list is paginated', function (): void {
        $response = $this->getJson("/api/v1/users/{$this->targetUser->uuid}/followers?per_page=2");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'currentPage',
                    'lastPage',
                    'perPage',
                    'total',
                ],
            ]);
    });
});

describe('Following List', function (): void {
    beforeEach(function (): void {
        // Create users that target is following
        for ($i = 0; $i < 3; $i++) {
            $followed = User::factory()->create();
            Profile::factory()->create(['user_id' => $followed->id]);
            Follow::create([
                'follower_id' => $this->targetUser->id,
                'following_id' => $followed->id,
                'accepted_at' => now(),
            ]);
        }
    });

    test('can get following list', function (): void {
        $response = $this->getJson("/api/v1/users/{$this->targetUser->uuid}/following");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('following list is paginated', function (): void {
        $response = $this->getJson("/api/v1/users/{$this->targetUser->uuid}/following?per_page=2");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'currentPage',
                    'lastPage',
                    'perPage',
                    'total',
                ],
            ]);
    });
});

describe('Pending Follow Requests', function (): void {
    beforeEach(function (): void {
        $this->targetUser->profile->update(['is_private' => true]);

        // Create pending followers
        $this->pendingFollowers = [];
        for ($i = 0; $i < 3; $i++) {
            $follower = User::factory()->create();
            Profile::factory()->create(['user_id' => $follower->id]);
            Follow::create([
                'follower_id' => $follower->id,
                'following_id' => $this->targetUser->id,
                'accepted_at' => null,
            ]);
            $this->pendingFollowers[] = $follower;
        }

        // Create accepted follower (shouldn't show in pending)
        $this->acceptedFollower = User::factory()->create();
        Profile::factory()->create(['user_id' => $this->acceptedFollower->id]);
        Follow::create([
            'follower_id' => $this->acceptedFollower->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => now(),
        ]);
    });

    test('user can get pending follow requests', function (): void {
        $response = $this->actingAs($this->targetUser)
            ->getJson('/api/v1/follow-requests');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('accepted followers are not in pending list', function (): void {
        $response = $this->actingAs($this->targetUser)
            ->getJson('/api/v1/follow-requests');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('uuid');
        expect($ids)->not->toContain($this->acceptedFollower->uuid);
    });

    test('guest cannot get pending requests', function (): void {
        $response = $this->getJson('/api/v1/follow-requests');

        $response->assertStatus(401);
    });
});

describe('Accept Follow Request', function (): void {
    beforeEach(function (): void {
        $this->targetUser->profile->update(['is_private' => true]);
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => null,
        ]);
    });

    test('user can accept follow request', function (): void {
        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/v1/follow-requests/{$this->user->uuid}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Follow request accepted.');

        $follow = Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->first();
        expect($follow->accepted_at)->not->toBeNull();
    });

    test('cannot accept non-existent request', function (): void {
        $newUser = User::factory()->create();
        Profile::factory()->create(['user_id' => $newUser->id]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/v1/follow-requests/{$newUser->uuid}/accept");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No pending follow request from this user.');
    });

    test('cannot accept already accepted request', function (): void {
        // Accept the request first
        Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->update(['accepted_at' => now()]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/v1/follow-requests/{$this->user->uuid}/accept");

        $response->assertStatus(422);
    });

    test('guest cannot accept requests', function (): void {
        $response = $this->postJson("/api/v1/follow-requests/{$this->user->uuid}/accept");

        $response->assertStatus(401);
    });
});

describe('Reject Follow Request', function (): void {
    beforeEach(function (): void {
        $this->targetUser->profile->update(['is_private' => true]);
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
            'accepted_at' => null,
        ]);
    });

    test('user can reject follow request', function (): void {
        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/v1/follow-requests/{$this->user->uuid}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Follow request rejected.');

        expect(Follow::where('follower_id', $this->user->id)
            ->where('following_id', $this->targetUser->id)
            ->exists())->toBeFalse();
    });

    test('cannot reject non-existent request', function (): void {
        $newUser = User::factory()->create();
        Profile::factory()->create(['user_id' => $newUser->id]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/v1/follow-requests/{$newUser->uuid}/reject");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No pending follow request from this user.');
    });

    test('guest cannot reject requests', function (): void {
        $response = $this->postJson("/api/v1/follow-requests/{$this->user->uuid}/reject");

        $response->assertStatus(401);
    });
});
