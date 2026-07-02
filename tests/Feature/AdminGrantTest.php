<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGrantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function plan(int $level, string $name): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name'          => $name,
            'slug'          => strtolower($name) . '-' . $level,
            'level'         => $level,
            'price_monthly' => $level * 1000,
            'is_active'     => true,
        ]);
    }

    private function activeSub(User $user, SubscriptionPlan $plan, int $daysLeft): Subscription
    {
        return Subscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle'        => 'monthly',
            'price_paid'           => 1000,
            'starts_at'            => now()->subDays(5),
            'ends_at'              => now()->addDays($daysLeft),
            'status'               => 'active',
            'auto_renew'           => true,
        ]);
    }

    // ── Coins ──

    public function test_grant_coins_increases_balance_and_writes_transaction(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-coins", ['amount' => 500, 'note' => 'test grant']);

        $response->assertStatus(200)->assertJsonPath('new_balance', 500);
        $this->assertEquals(500, (float) Wallet::where('user_id', $target->id)->first()->balance);
        $this->assertDatabaseHas('transactions', [
            'type'        => 'deposit',
            'status'      => 'completed',
            'amount'      => 500,
            'description' => 'Admin grant: test grant',
        ]);
    }

    public function test_grant_coins_forbidden_for_non_admin(): void
    {
        $target = User::factory()->create();
        $nonAdmin = User::factory()->create(['role' => 'user']);

        $this->actingAs($nonAdmin)
            ->postJson("/api/v1/admin/users/{$target->id}/grant-coins", ['amount' => 500])
            ->assertStatus(403);
    }

    public function test_grant_coins_rejects_invalid_amount(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-coins", ['amount' => 0])
            ->assertStatus(422);
    }

    // ── Subscription ──

    public function test_grant_subscription_creates_sub_for_user_without_one(): void
    {
        $target = User::factory()->create();
        $this->plan(1, 'Premium');

        $response = $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-subscription", ['plan_level' => 1, 'days' => 30]);

        $response->assertStatus(200)->assertJsonPath('data.level', 1);
        $this->assertEquals(1, $target->fresh()->subscription_level);
        $this->assertDatabaseHas('subscriptions', [
            'user_id'     => $target->id,
            'status'      => 'active',
            'auto_renew'  => false,
            'price_paid'  => 0,
        ]);
    }

    public function test_grant_subscription_extends_existing_same_level(): void
    {
        $target = User::factory()->create();
        $premium = $this->plan(1, 'Premium');
        $existing = $this->activeSub($target, $premium, 10); // ends in 10 days
        $originalEnd = $existing->ends_at->copy();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-subscription", ['plan_level' => 1, 'days' => 30])
            ->assertStatus(200);

        $existing->refresh();
        $this->assertEquals($originalEnd->addDays(30)->toDateString(), $existing->ends_at->toDateString());
        $this->assertEquals(1, $target->fresh()->subscription_level);
        // No second subscription created
        $this->assertEquals(1, Subscription::where('user_id', $target->id)->count());
    }

    public function test_grant_higher_level_upgrades_existing(): void
    {
        $target = User::factory()->create();
        $premium = $this->plan(1, 'Premium');
        $vip = $this->plan(2, 'VIP');
        $existing = $this->activeSub($target, $premium, 10);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-subscription", ['plan_level' => 2, 'days' => 30])
            ->assertStatus(200);

        $existing->refresh();
        $this->assertEquals($vip->id, $existing->subscription_plan_id);
        $this->assertEquals(2, $target->fresh()->subscription_level);
    }

    public function test_grant_lower_level_does_not_downgrade(): void
    {
        $target = User::factory()->create();
        $premium = $this->plan(1, 'Premium');
        $vip = $this->plan(2, 'VIP');
        $existing = $this->activeSub($target, $vip, 10);
        $originalEnd = $existing->ends_at->copy();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/users/{$target->id}/grant-subscription", ['plan_level' => 1, 'days' => 30])
            ->assertStatus(200);

        $existing->refresh();
        // Still VIP, but days were extended
        $this->assertEquals($vip->id, $existing->subscription_plan_id);
        $this->assertEquals(2, $target->fresh()->subscription_level);
        $this->assertEquals($originalEnd->addDays(30)->toDateString(), $existing->ends_at->toDateString());
    }

    public function test_grant_subscription_forbidden_for_non_admin(): void
    {
        $target = User::factory()->create();
        $this->plan(1, 'Premium');
        $nonAdmin = User::factory()->create(['role' => 'user']);

        $this->actingAs($nonAdmin)
            ->postJson("/api/v1/admin/users/{$target->id}/grant-subscription", ['plan_level' => 1, 'days' => 30])
            ->assertStatus(403);
    }
}
