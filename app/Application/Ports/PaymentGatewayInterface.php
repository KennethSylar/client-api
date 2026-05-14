<?php

namespace App\Application\Ports;

use App\Domain\Orders\Order;

interface PaymentGatewayInterface
{
    /**
     * Build the redirect URL for the payment gateway.
     *
     * @param array<string,string> $gatewaySettings  Merchant credentials from settings table
     * @param string               $returnUrl         User lands here after payment
     * @param string               $cancelUrl         User lands here on cancel
     * @param string               $notifyUrl         Server-to-server ITN/notify endpoint
     */
    public function buildPaymentUrl(
        Order  $order,
        array  $gatewaySettings,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl,
    ): string;

    /**
     * Verify the incoming ITN/notify payload signature.
     *
     * @param array<string,string> $payload          Raw POST fields from the gateway
     * @param array<string,string> $gatewaySettings  Merchant credentials
     */
    public function verifyNotification(array $payload, array $gatewaySettings): bool;
}
