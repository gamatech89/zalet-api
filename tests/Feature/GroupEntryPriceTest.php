<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupEntryPriceTest extends TestCase
{
    use RefreshDatabase;

    private function giveActiveSubscription(User $user, int $level): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan Level ' . $level,
            'slug' => 'test-plan-' . $level . '-' . $user->id,
            'level' => $level,
            'price_monthly' => 500,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'price_paid' => 500,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function test_purchase_group_entry_transfers_full_price_to_owner(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::factory()->group()->create(['entry_price' => 150]);
        $conversation->users()->attach($owner->id, ['joined_at' => now(), 'role' => 'owner']);

        $coinService = app(CoinService::class);
        $coinService->credit($buyer, 500, 'test funding');

        $transaction = $coinService->purchaseGroupEntry($buyer, $conversation);

        $this->assertEquals(150, (float) $transaction->amount);
        $this->assertEquals('group_entry', $transaction->type);
        $this->assertEquals(350, (float) Wallet::where('user_id', $buyer->id)->first()->balance);
        $this->assertEquals(150, (float) Wallet::where('user_id', $owner->id)->first()->balance);
    }

    public function test_purchase_group_entry_throws_on_insufficient_balance(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::factory()->group()->create(['entry_price' => 150]);
        $conversation->users()->attach($owner->id, ['joined_at' => now(), 'role' => 'owner']);

        $this->expectException(\RuntimeException::class);
        app(CoinService::class)->purchaseGroupEntry($buyer, $conversation);
    }

    public function test_joining_priced_group_returns_entry_price_error(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::factory()->group()->create([
            'entry_price' => 100,
            'is_public' => true,
            'invite_code' => 'testcode1',
        ]);
        $conversation->users()->attach($owner->id, ['joined_at' => now(), 'role' => 'owner']);

        $response = $this->actingAs($buyer)->getJson('/api/v1/conversations/join/testcode1');

        $response->assertStatus(403)
            ->assertJsonPath('error_type', 'entry_price')
            ->assertJsonPath('can_pay', true)
            ->assertJsonPath('entry_price', 100);
    }

    public function test_subscribed_user_bypasses_entry_price(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $this->giveActiveSubscription($buyer, 1);
        $conversation = Conversation::factory()->group()->create([
            'entry_price' => 100,
            'is_public' => true,
            'invite_code' => 'testcode2',
        ]);
        $conversation->users()->attach($owner->id, ['joined_at' => now(), 'role' => 'owner']);

        $response = $this->actingAs($buyer)->getJson('/api/v1/conversations/join/testcode2');

        $response->assertStatus(201);
    }

    public function test_pay_entry_endpoint_deducts_coins_and_records_entry(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::factory()->group()->create([
            'entry_price' => 100,
            'is_public' => true,
            'invite_code' => 'testcode3',
        ]);
        $conversation->users()->attach($owner->id, ['joined_at' => now(), 'role' => 'owner']);
        app(CoinService::class)->credit($buyer, 200, 'test funding');

        $response = $this->actingAs($buyer)->postJson("/api/v1/conversations/{$conversation->id}/pay-entry");

        $response->assertStatus(200);
        $this->assertDatabaseHas('group_entries', ['user_id' => $buyer->id, 'conversation_id' => $conversation->id]);

        // Now joining should succeed without another charge
        $joinResponse = $this->actingAs($buyer)->getJson('/api/v1/conversations/join/testcode3');
        $joinResponse->assertStatus(201);
    }
}
