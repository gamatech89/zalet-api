<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\DTOs\CreateOrderDTO;
use App\Domains\Wallet\DTOs\CreateSessionDTO;
use App\Domains\Wallet\DTOs\OrderResponse;
use App\Domains\Wallet\DTOs\RefundDTO;
use App\Domains\Wallet\DTOs\RefundResponse;
use App\Domains\Wallet\DTOs\SessionResponse;
use App\Domains\Wallet\DTOs\WebhookPayload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stub implementation of RaiAccept payment provider for development.
 * Simulates the full payment flow locally.
 */
class StubRaiAcceptService implements PaymentProviderInterface
{
    private string $behavior;

    /** @var array<string, array<string, mixed>> In-memory order storage */
    private static array $orders = [];

    public function __construct(?string $behavior = null)
    {
        $this->behavior = $behavior ?? config('services.raiaccept.stub_behavior', 'success');
    }

    public function authenticate(): string
    {
        // Return a fake token
        return 'stub-token-' . Str::random(32);
    }

    public function createOrder(CreateOrderDTO $dto): OrderResponse
    {
        $orderIdentification = 'STUB-' . strtoupper(Str::random(8));

        // Store order in memory for later retrieval
        self::$orders[$orderIdentification] = [
            'orderIdentification' => $orderIdentification,
            'merchantOrderReference' => $dto->merchantOrderReference,
            'amountCents' => $dto->amountCents,
            'currency' => $dto->currency,
            'customerEmail' => $dto->customerEmail,
            'customerReference' => $dto->customerReference,
            'successUrl' => $dto->successUrl,
            'failureUrl' => $dto->failureUrl,
            'cancelUrl' => $dto->cancelUrl,
            'notificationUrl' => $dto->notificationUrl,
            'status' => 'created',
            'createdAt' => now()->toIso8601String(),
        ];

        return new OrderResponse(
            orderIdentification: $orderIdentification,
            merchantOrderReference: $dto->merchantOrderReference,
            status: 'created',
            meta: self::$orders[$orderIdentification],
        );
    }

    public function createPaymentSession(CreateSessionDTO $dto): SessionResponse
    {
        // Return URL to our stub payment form
        $sessionUrl = route('stub.payment.form', [
            'order' => $dto->orderIdentification,
            'lang' => $dto->language,
        ]);

        return new SessionResponse(
            sessionUrl: $sessionUrl,
            expiresAt: Carbon::now()->addHour(),
        );
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $data = $request->all();

        return WebhookPayload::fromArray([
            'orderIdentification' => $data['orderIdentification'],
            'transactionId' => $data['transactionId'] ?? 'STUB-TXN-' . Str::random(8),
            'status' => $data['status'] ?? 'success',
            'responseCode' => $data['responseCode'] ?? '0000',
            'amountCents' => $data['amountCents'] ?? 0,
        ]);
    }

    public function issueRefund(RefundDTO $dto): RefundResponse
    {
        $shouldSucceed = $this->behavior !== 'failure';

        if (!$shouldSucceed) {
            throw new \RuntimeException('Stub refund failed (configured behavior)');
        }

        $order = self::$orders[$dto->orderIdentification] ?? null;
        $refundAmount = $dto->amountCents ?? ($order['amountCents'] ?? 0);

        return new RefundResponse(
            refundId: 'STUB-REFUND-' . Str::random(8),
            status: 'completed',
            amountCents: $refundAmount,
            meta: [
                'orderIdentification' => $dto->orderIdentification,
                'transactionId' => $dto->transactionId,
                'isPartial' => $dto->isPartialRefund(),
            ],
        );
    }

    public function getOrderDetails(string $providerOrderId): array
    {
        if (!isset(self::$orders[$providerOrderId])) {
            throw new \RuntimeException("Order not found: {$providerOrderId}");
        }

        return self::$orders[$providerOrderId];
    }

    public function getProviderName(): string
    {
        return 'stub_raiaccept';
    }

    /**
     * Simulate payment completion (for stub payment form).
     *
     * @param string $orderIdentification
     * @param bool $success
     * @return array<string, mixed>
     */
    public function simulatePayment(string $orderIdentification, bool $success = true): array
    {
        $order = self::$orders[$orderIdentification] ?? null;

        if (!$order) {
            throw new \RuntimeException("Order not found: {$orderIdentification}");
        }

        $transactionId = 'STUB-TXN-' . Str::random(8);
        $status = $success ? 'success' : 'failure';
        $responseCode = $success ? '0000' : '9999';

        // Update stored order
        self::$orders[$orderIdentification]['status'] = $status;
        self::$orders[$orderIdentification]['transactionId'] = $transactionId;

        return [
            'orderIdentification' => $orderIdentification,
            'transactionId' => $transactionId,
            'status' => $status,
            'responseCode' => $responseCode,
            'amountCents' => $order['amountCents'],
            'redirectUrl' => $success ? $order['successUrl'] : $order['failureUrl'],
            'notificationUrl' => $order['notificationUrl'],
        ];
    }

    /**
     * Get stored order (for stub purposes).
     *
     * @param string $orderIdentification
     * @return array<string, mixed>|null
     */
    public static function getStoredOrder(string $orderIdentification): ?array
    {
        return self::$orders[$orderIdentification] ?? null;
    }

    /**
     * Get configured behavior.
     */
    public function getBehavior(): string
    {
        return $this->behavior;
    }
}
