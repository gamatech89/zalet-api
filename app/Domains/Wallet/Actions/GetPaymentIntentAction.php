<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\PaymentIntent;
use Illuminate\Support\Str;

final class GetPaymentIntentAction
{
    /**
     * Get a payment intent by UUID.
     *
     * @param string $uuid Payment intent UUID
     * @param User|null $user Optional user to verify ownership
     * @return PaymentIntent|null
     */
    public function execute(string $uuid, ?User $user = null): ?PaymentIntent
    {
        // Validate UUID format to avoid PostgreSQL errors
        if (!Str::isUuid($uuid)) {
            return null;
        }

        $query = PaymentIntent::where('uuid', $uuid);

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    /**
     * Get user's payment intents.
     *
     * @param User $user
     * @param string|null $status Filter by status
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection<int, PaymentIntent>
     */
    public function forUser(User $user, ?string $status = null, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = PaymentIntent::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }
}
