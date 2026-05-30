<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_payment_methods(): void
    {
        $response = $this->getJson('/api/v1/payment-methods');
        $response->assertStatus(401);
    }

    public function test_user_can_list_payment_methods(): void
    {
        PaymentMethod::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'card_brand',
                        'last_four',
                        'expiry_month',
                        'expiry_year',
                        'is_default',
                        'display_name',
                        'is_expired',
                    ],
                ],
            ]);
    }

    public function test_user_only_sees_own_payment_methods(): void
    {
        PaymentMethod::factory()->create(['user_id' => $this->user->id]);
        PaymentMethod::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payment-methods');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_add_payment_method(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment-methods', [
                'card_brand' => 'visa',
                'last_four' => '4242',
                'expiry_month' => '12',
                'expiry_year' => '28',
                'gateway_token' => 'tok_test_123456',
                'label' => 'My Visa Card',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.card_brand', 'visa')
            ->assertJsonPath('data.last_four', '4242')
            ->assertJsonPath('data.is_default', true); // First card is auto-default
    }

    public function test_first_card_is_auto_default(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment-methods', [
                'card_brand' => 'visa',
                'last_four' => '4242',
                'expiry_month' => '12',
                'expiry_year' => '28',
                'gateway_token' => 'tok_first',
            ]);

        $this->assertDatabaseHas('payment_methods', [
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);
    }

    public function test_user_can_set_default_payment_method(): void
    {
        $first = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);
        $second = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/payment-methods/{$second->id}/default");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $second->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'id' => $first->id,
            'is_default' => false,
        ]);
    }

    public function test_user_cannot_set_default_on_other_users_card(): void
    {
        $otherCard = PaymentMethod::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/payment-methods/{$otherCard->id}/default");

        $response->assertStatus(403);
    }

    public function test_user_can_delete_payment_method(): void
    {
        $card = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/payment-methods/{$card->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('payment_methods', ['id' => $card->id]);
    }

    public function test_deleting_default_promotes_next_card(): void
    {
        $first = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);
        $second = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/payment-methods/{$first->id}");

        $this->assertDatabaseHas('payment_methods', [
            'id' => $second->id,
            'is_default' => true,
        ]);
    }

    public function test_user_cannot_delete_other_users_card(): void
    {
        $otherCard = PaymentMethod::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/payment-methods/{$otherCard->id}");

        $response->assertStatus(403);
    }

    public function test_max_10_payment_methods_enforced(): void
    {
        PaymentMethod::factory()->count(10)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment-methods', [
                'card_brand' => 'visa',
                'last_four' => '9999',
                'expiry_month' => '12',
                'expiry_year' => '28',
                'gateway_token' => 'tok_overflow',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_rules(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment-methods', [
                'card_brand' => 'invalid',
                'last_four' => '12345', // Too long
                'expiry_month' => '13', // Invalid month
                'expiry_year' => '2028', // Should be 2 digits
                'gateway_token' => '', // Required
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['card_brand', 'last_four', 'expiry_month', 'expiry_year', 'gateway_token']);
    }
}
