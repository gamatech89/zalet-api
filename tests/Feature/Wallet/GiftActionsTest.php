<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Wallet\Actions\GetCreatorEarningsAction;
use App\Domains\Wallet\Actions\GetGiftCatalogAction;
use App\Domains\Wallet\Actions\SendGiftAction;
use App\Domains\Wallet\Events\GiftSent;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('GetGiftCatalogAction', function (): void {

    it('returns all gifts from config', function (): void {
        $action = app(GetGiftCatalogAction::class);
        $gifts = $action->execute();

        expect($gifts)->toBeArray()
            ->and($gifts)->toHaveKey('rakija')
            ->and($gifts)->toHaveKey('rose')
            ->and($gifts)->toHaveKey('heart')
            ->and($gifts)->toHaveKey('crown')
            ->and($gifts)->toHaveKey('car');
    });

    it('returns correct gift structure', function (): void {
        $action = app(GetGiftCatalogAction::class);
        $gifts = $action->execute();

        $rakija = $gifts['rakija'];

        expect($rakija)->toHaveKey('name')
            ->and($rakija)->toHaveKey('credits')
            ->and($rakija)->toHaveKey('icon')
            ->and($rakija)->toHaveKey('animation')
            ->and($rakija['name'])->toBe('Rakija')
            ->and($rakija['credits'])->toBe(5)
            ->and($rakija['icon'])->toBe('🥃')
            ->and($rakija['animation'])->toBe('bounce');
    });

    it('can get a specific gift', function (): void {
        $action = app(GetGiftCatalogAction::class);
        $gift = $action->getGift('crown');

        expect($gift)->not->toBeNull()
            ->and($gift['name'])->toBe('Kruna')
            ->and($gift['credits'])->toBe(100);
    });

    it('returns null for invalid gift type', function (): void {
        $action = app(GetGiftCatalogAction::class);
        $gift = $action->getGift('invalid_gift');

        expect($gift)->toBeNull();
    });

    it('validates gift type correctly', function (): void {
        $action = app(GetGiftCatalogAction::class);

        expect($action->isValidGiftType('heart'))->toBeTrue()
            ->and($action->isValidGiftType('invalid'))->toBeFalse();
    });

    it('returns correct gift cost', function (): void {
        $action = app(GetGiftCatalogAction::class);

        expect($action->getGiftCost('car'))->toBe(500)
            ->and($action->getGiftCost('rose'))->toBe(10)
            ->and($action->getGiftCost('invalid'))->toBe(0);
    });

});

describe('SendGiftAction', function (): void {

    it('sends a gift successfully', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(100)->create();

        $action = app(SendGiftAction::class);
        $result = $action->execute($sender, $recipient, 'heart');

        $sender->refresh();
        $recipient->refresh();

        expect($sender->wallet->balance)->toBe(475) // 500 - 25
            ->and($recipient->wallet->balance)->toBe(125) // 100 + 25
            ->and($result['sender_entry']->type)->toBe(LedgerEntry::TYPE_GIFT_SENT)
            ->and($result['recipient_entry']->type)->toBe(LedgerEntry::TYPE_GIFT_RECEIVED)
            ->and($result['gift']['name'])->toBe('Srce')
            ->and($result['gift']['credits'])->toBe(25);

        Event::assertDispatched(GiftSent::class, function (GiftSent $event) use ($sender, $recipient): bool {
            return $event->senderId === $sender->id
                && $event->recipientId === $recipient->id
                && $event->giftType === 'heart'
                && $event->credits === 25;
        });
    });

    it('includes live session id in meta when provided', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $action = app(SendGiftAction::class);
        $result = $action->execute($sender, $recipient, 'rakija', liveSessionId: 123);

        /** @var array<string, mixed> $meta */
        $meta = $result['sender_entry']->meta;

        expect($meta)->toHaveKey('live_session_id')
            ->and($meta['live_session_id'])->toBe(123);

        Event::assertDispatched(GiftSent::class, function (GiftSent $event): bool {
            return $event->liveSessionId === 123;
        });
    });

    it('throws exception for invalid gift type', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();

        $action = app(SendGiftAction::class);
        $action->execute($sender, $recipient, 'invalid_gift');
    })->throws(\InvalidArgumentException::class, 'Invalid gift type: invalid_gift');

    it('throws exception when sending gift to yourself', function (): void {
        $user = User::factory()->create();

        Wallet::factory()->forUser($user)->withBalance(500)->create();

        $action = app(SendGiftAction::class);
        $action->execute($user, $user, 'heart');
    })->throws(\InvalidArgumentException::class, 'Cannot send a gift to yourself');

    it('throws exception for insufficient balance', function (): void {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(10)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $action = app(SendGiftAction::class);
        $action->execute($sender, $recipient, 'heart'); // 25 credits needed
    })->throws(\RuntimeException::class, 'Insufficient balance');

    it('creates wallets if they do not exist', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        // Only create sender wallet with balance
        Wallet::factory()->forUser($sender)->withBalance(100)->create();

        $action = app(SendGiftAction::class);
        $result = $action->execute($sender, $recipient, 'rakija'); // 5 credits

        $sender->refresh();
        $recipient->refresh();

        expect($sender->wallet->balance)->toBe(95)
            ->and($recipient->wallet)->not->toBeNull()
            ->and($recipient->wallet->balance)->toBe(5);
    });

    it('records correct meta data for gift transactions', function (): void {
        Event::fake([GiftSent::class]);

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Wallet::factory()->forUser($sender)->withBalance(500)->create();
        Wallet::factory()->forUser($recipient)->withBalance(0)->create();

        $action = app(SendGiftAction::class);
        $result = $action->execute($sender, $recipient, 'crown');

        /** @var array<string, mixed> $senderMeta */
        $senderMeta = $result['sender_entry']->meta;
        /** @var array<string, mixed> $recipientMeta */
        $recipientMeta = $result['recipient_entry']->meta;

        expect($senderMeta['gift_type'])->toBe('crown')
            ->and($senderMeta['gift_name'])->toBe('Kruna')
            ->and($senderMeta['gift_icon'])->toBe('👑')
            ->and($senderMeta['gift_animation'])->toBe('sparkle')
            ->and($recipientMeta['gift_type'])->toBe('crown');
    });

});

describe('GetCreatorEarningsAction', function (): void {

    it('returns zero earnings for user without wallet', function (): void {
        $user = User::factory()->create();

        $action = app(GetCreatorEarningsAction::class);
        $result = $action->execute($user);

        expect($result['total_credits'])->toBe(0)
            ->and($result['total_gifts_received'])->toBe(0);
    });

    it('returns zero earnings for user with no gift transactions', function (): void {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->withBalance(100)->create();

        // Add a deposit (not a gift)
        $wallet->credit(50, LedgerEntry::TYPE_DEPOSIT);

        $action = app(GetCreatorEarningsAction::class);
        $result = $action->execute($user);

        expect($result['total_credits'])->toBe(0)
            ->and($result['total_gifts_received'])->toBe(0);
    });

    it('calculates total earnings from received gifts', function (): void {
        Event::fake([GiftSent::class]);

        $creator = User::factory()->create();
        $fan1 = User::factory()->create();
        $fan2 = User::factory()->create();

        Wallet::factory()->forUser($creator)->withBalance(0)->create();
        Wallet::factory()->forUser($fan1)->withBalance(500)->create();
        Wallet::factory()->forUser($fan2)->withBalance(500)->create();

        $action = app(SendGiftAction::class);

        // Fan1 sends heart (25 credits)
        $action->execute($fan1, $creator, 'heart');
        // Fan1 sends rose (10 credits)
        $action->execute($fan1, $creator, 'rose');
        // Fan2 sends crown (100 credits)
        $action->execute($fan2, $creator, 'crown');

        $earningsAction = app(GetCreatorEarningsAction::class);
        $result = $earningsAction->execute($creator);

        expect($result['total_credits'])->toBe(135) // 25 + 10 + 100
            ->and($result['total_gifts_received'])->toBe(3);
    });

    it('filters by date range', function (): void {
        Event::fake([GiftSent::class]);

        $creator = User::factory()->create();
        $fan = User::factory()->create();

        Wallet::factory()->forUser($creator)->withBalance(0)->create();
        Wallet::factory()->forUser($fan)->withBalance(1000)->create();

        $action = app(SendGiftAction::class);

        // Send some gifts
        $action->execute($fan, $creator, 'heart');
        $action->execute($fan, $creator, 'rose');

        $earningsAction = app(GetCreatorEarningsAction::class);

        // Filter with future start date should return 0
        $futureResult = $earningsAction->execute(
            $creator,
            startDate: now()->addDay(),
        );

        expect($futureResult['total_credits'])->toBe(0)
            ->and($futureResult['total_gifts_received'])->toBe(0);

        // Filter with past start date should include all
        $pastResult = $earningsAction->execute(
            $creator,
            startDate: now()->subDay(),
        );

        expect($pastResult['total_credits'])->toBe(35)
            ->and($pastResult['total_gifts_received'])->toBe(2);
    });

    it('includes period dates in result', function (): void {
        $user = User::factory()->create();
        Wallet::factory()->forUser($user)->withBalance(0)->create();

        $startDate = now()->subDays(7);
        $endDate = now();

        $action = app(GetCreatorEarningsAction::class);
        $result = $action->execute($user, $startDate, $endDate);

        expect($result['period_start'])->toBe($startDate->toIso8601String())
            ->and($result['period_end'])->toBe($endDate->toIso8601String());
    });

    it('returns breakdown by gift type', function (): void {
        Event::fake([GiftSent::class]);

        $creator = User::factory()->create();
        $fan = User::factory()->create();

        Wallet::factory()->forUser($creator)->withBalance(0)->create();
        Wallet::factory()->forUser($fan)->withBalance(1000)->create();

        $sendAction = app(SendGiftAction::class);

        // Send multiple gifts of different types
        $sendAction->execute($fan, $creator, 'heart');
        $sendAction->execute($fan, $creator, 'heart');
        $sendAction->execute($fan, $creator, 'rose');
        $sendAction->execute($fan, $creator, 'crown');

        $action = app(GetCreatorEarningsAction::class);
        $breakdown = $action->getBreakdown($creator);

        expect($breakdown)->toHaveKey('heart')
            ->and($breakdown)->toHaveKey('rose')
            ->and($breakdown)->toHaveKey('crown')
            ->and($breakdown['heart']['count'])->toBe(2)
            ->and($breakdown['heart']['total_credits'])->toBe(50) // 25 * 2
            ->and($breakdown['rose']['count'])->toBe(1)
            ->and($breakdown['rose']['total_credits'])->toBe(10)
            ->and($breakdown['crown']['count'])->toBe(1)
            ->and($breakdown['crown']['total_credits'])->toBe(100);
    });

    it('returns empty breakdown for user without wallet', function (): void {
        $user = User::factory()->create();

        $action = app(GetCreatorEarningsAction::class);
        $breakdown = $action->getBreakdown($user);

        expect($breakdown)->toBeArray()
            ->and($breakdown)->toBeEmpty();
    });

});
