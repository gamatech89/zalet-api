<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Contracts;

use App\Domains\Wallet\DTOs\CreateOrderDTO;
use App\Domains\Wallet\DTOs\CreateSessionDTO;
use App\Domains\Wallet\DTOs\OrderResponse;
use App\Domains\Wallet\DTOs\RefundDTO;
use App\Domains\Wallet\DTOs\RefundResponse;
use App\Domains\Wallet\DTOs\SessionResponse;
use App\Domains\Wallet\DTOs\WebhookPayload;
use Illuminate\Http\Request;

/**
 * Interface for payment provider implementations.
 * Allows swapping between stub, sandbox, and production providers.
 */
interface PaymentProviderInterface
{
    /**
     * Authenticate with the payment provider and get access token.
     *
     * @return string Access token
     * @throws \RuntimeException If authentication fails
     */
    public function authenticate(): string;

    /**
     * Create an order entry in the provider's system.
     *
     * @param CreateOrderDTO $dto Order creation data
     * @return OrderResponse Provider's order response
     * @throws \RuntimeException If order creation fails
     */
    public function createOrder(CreateOrderDTO $dto): OrderResponse;

    /**
     * Create a payment session/form URL.
     *
     * @param CreateSessionDTO $dto Session creation data
     * @return SessionResponse Payment form URL and metadata
     * @throws \RuntimeException If session creation fails
     */
    public function createPaymentSession(CreateSessionDTO $dto): SessionResponse;

    /**
     * Parse and validate incoming webhook payload.
     *
     * @param Request $request The incoming webhook request
     * @return WebhookPayload Parsed webhook data
     * @throws \RuntimeException If webhook is invalid or cannot be parsed
     */
    public function parseWebhook(Request $request): WebhookPayload;

    /**
     * Issue a refund for a completed transaction.
     *
     * @param RefundDTO $dto Refund request data
     * @return RefundResponse Refund response
     * @throws \RuntimeException If refund fails
     */
    public function issueRefund(RefundDTO $dto): RefundResponse;

    /**
     * Get order details from the provider.
     *
     * @param string $providerOrderId The provider's order ID
     * @return array<string, mixed> Order details
     * @throws \RuntimeException If order not found
     */
    public function getOrderDetails(string $providerOrderId): array;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
