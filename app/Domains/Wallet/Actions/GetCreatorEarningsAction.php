<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Carbon\Carbon;

/**
 * @phpstan-type EarningsSummary array{total_credits: int, total_gifts_received: int, period_start: string|null, period_end: string|null}
 */
final class GetCreatorEarningsAction
{
    /**
     * Get creator earnings (total credits received as gifts).
     *
     * @param User $user The creator to get earnings for
     * @param Carbon|null $startDate Optional start date filter
     * @param Carbon|null $endDate Optional end date filter
     * @return EarningsSummary
     */
    public function execute(
        User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $wallet = Wallet::where('user_id', $user->id)->first();

        if ($wallet === null) {
            return [
                'total_credits' => 0,
                'total_gifts_received' => 0,
                'period_start' => $startDate?->toIso8601String(),
                'period_end' => $endDate?->toIso8601String(),
            ];
        }

        $query = LedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerEntry::TYPE_GIFT_RECEIVED);

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        // Sum of all gift_received amounts (which are positive credits)
        $totalCredits = (int) $query->sum('amount');
        $totalGiftsReceived = $query->count();

        return [
            'total_credits' => $totalCredits,
            'total_gifts_received' => $totalGiftsReceived,
            'period_start' => $startDate?->toIso8601String(),
            'period_end' => $endDate?->toIso8601String(),
        ];
    }

    /**
     * Get detailed earnings breakdown by gift type.
     *
     * @param User $user The creator to get earnings for
     * @param Carbon|null $startDate Optional start date filter
     * @param Carbon|null $endDate Optional end date filter
     * @return array<string, array{gift_type: string, gift_name: string, count: int, total_credits: int}>
     */
    public function getBreakdown(
        User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): array {
        $wallet = Wallet::where('user_id', $user->id)->first();

        if ($wallet === null) {
            return [];
        }

        $query = LedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerEntry::TYPE_GIFT_RECEIVED);

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        $entries = $query->get();

        $breakdown = [];

        foreach ($entries as $entry) {
            /** @var array<string, mixed> $meta */
            $meta = $entry->meta;
            $giftType = (string) ($meta['gift_type'] ?? 'unknown');
            $giftName = (string) ($meta['gift_name'] ?? 'Unknown');

            if (!isset($breakdown[$giftType])) {
                $breakdown[$giftType] = [
                    'gift_type' => $giftType,
                    'gift_name' => $giftName,
                    'count' => 0,
                    'total_credits' => 0,
                ];
            }

            $breakdown[$giftType]['count']++;
            $breakdown[$giftType]['total_credits'] += $entry->amount;
        }

        return $breakdown;
    }
}
