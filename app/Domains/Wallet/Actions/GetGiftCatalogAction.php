<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Actions;

/**
 * @phpstan-type GiftItem array{name: string, credits: int, icon: string, animation: string}
 */
final class GetGiftCatalogAction
{
    /**
     * Get all available gifts from the catalog.
     *
     * @return array<string, GiftItem>
     */
    public function execute(): array
    {
        /** @var array<string, GiftItem> $gifts */
        $gifts = config('gifts', []);

        return $gifts;
    }

    /**
     * Get a specific gift by type.
     *
     * @return GiftItem|null
     */
    public function getGift(string $giftType): ?array
    {
        /** @var array<string, GiftItem> $gifts */
        $gifts = config('gifts', []);

        return $gifts[$giftType] ?? null;
    }

    /**
     * Check if a gift type is valid.
     */
    public function isValidGiftType(string $giftType): bool
    {
        return $this->getGift($giftType) !== null;
    }

    /**
     * Get the credit cost of a gift.
     */
    public function getGiftCost(string $giftType): int
    {
        $gift = $this->getGift($giftType);

        return $gift['credits'] ?? 0;
    }
}
