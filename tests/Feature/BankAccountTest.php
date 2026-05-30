<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_bank_accounts(): void
    {
        $response = $this->getJson('/api/v1/bank-accounts');
        $response->assertStatus(401);
    }

    public function test_user_can_list_bank_accounts(): void
    {
        BankAccount::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/bank-accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'bank_name',
                        'last_four',
                        'is_default',
                        'display_name',
                    ],
                ],
            ]);
    }

    public function test_user_only_sees_own_bank_accounts(): void
    {
        BankAccount::factory()->create(['user_id' => $this->user->id]);
        BankAccount::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/bank-accounts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_add_bank_account(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/bank-accounts', [
                'bank_name' => 'Banca Intesa',
                'account_number' => '1234567890123456',
                'label' => 'My Intesa Account',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bank_name', 'Banca Intesa')
            ->assertJsonPath('data.last_four', '3456')
            ->assertJsonPath('data.is_default', true); // First account auto-default
    }

    public function test_user_can_set_default_bank_account(): void
    {
        $first = BankAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);
        $second = BankAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/bank-accounts/{$second->id}/default");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $first->id,
            'is_default' => false,
        ]);
    }

    public function test_user_cannot_modify_other_users_account(): void
    {
        $otherAccount = BankAccount::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/bank-accounts/{$otherAccount->id}/default");

        $response->assertStatus(403);
    }

    public function test_user_can_delete_bank_account(): void
    {
        $account = BankAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/bank-accounts/{$account->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $account->id]);
    }

    public function test_max_5_bank_accounts_enforced(): void
    {
        BankAccount::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/bank-accounts', [
                'bank_name' => 'Overflow Bank',
                'account_number' => '9999999999999999',
            ]);

        $response->assertStatus(422);
    }

    public function test_coin_packages_endpoint_returns_data(): void
    {
        $response = $this->getJson('/api/v1/coin-packages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'coins', 'price_rsd'],
                ],
            ]);
    }
}
