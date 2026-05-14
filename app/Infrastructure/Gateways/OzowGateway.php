<?php

namespace App\Infrastructure\Gateways;

use App\Application\Ports\PaymentGatewayInterface;
use App\Domain\Orders\Order;

class OzowGateway implements PaymentGatewayInterface
{
    public function buildPaymentUrl(
        Order  $order,
        array  $gatewaySettings,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl,
    ): string {
        $siteCode   = $gatewaySettings['shop_ozow_site_code']   ?? '';
        $privateKey = $gatewaySettings['shop_ozow_private_key'] ?? '';

        // Ozow needs a separate error URL; derive from cancelUrl
        $errorUrl = $cancelUrl . (str_contains($cancelUrl, '?') ? '&' : '?') . 'error=1';

        $amount      = number_format($order->total->amountCents / 100, 2, '.', '');
        $countryCode = 'ZA';
        $currencyCode= $order->currency;
        $transRef    = (string) $order->id;
        $bankRef     = "ORD{$order->id}";

        $isTest    = env('OZOW_TEST', 'true') !== 'false';
        $isTestStr = $isTest ? 'true' : 'false';

        // Ozow hash: SHA512 of lowercase concat (specific field order)
        $hashInput = strtolower(
            $siteCode . $countryCode . $currencyCode . $amount . $transRef .
            $bankRef . $cancelUrl . $errorUrl . $returnUrl . $notifyUrl . $isTestStr . $privateKey
        );
        $hash = hash('sha512', $hashInput);

        $params = [
            'SiteCode'             => $siteCode,
            'CountryCode'          => $countryCode,
            'CurrencyCode'         => $currencyCode,
            'Amount'               => $amount,
            'TransactionReference' => $transRef,
            'BankReference'        => $bankRef,
            'CancelUrl'            => $cancelUrl,
            'ErrorUrl'             => $errorUrl,
            'SuccessUrl'           => $returnUrl,
            'NotifyUrl'            => $notifyUrl,
            'IsTest'               => $isTestStr,
            'HashCheck'            => $hash,
        ];

        return 'https://pay.ozow.com/?' . http_build_query($params);
    }

    public function verifyNotification(array $payload, array $gatewaySettings): bool
    {
        $privateKey   = $gatewaySettings['shop_ozow_private_key'] ?? '';
        $siteCode     = $payload['SiteCode']             ?? '';
        $countryCode  = $payload['CountryCode']          ?? '';
        $currencyCode = $payload['CurrencyCode']         ?? '';
        $amount       = $payload['Amount']               ?? '';
        $transRef     = $payload['TransactionReference'] ?? '';
        $transId      = $payload['TransactionId']        ?? '';
        $status       = $payload['Status']               ?? '';
        $isTest       = $payload['IsTest']               ?? '';

        // Verify hash uses a different field order than build hash (no URLs, adds TransactionId)
        $hashInput = strtolower(
            $siteCode . $countryCode . $currencyCode . $amount . $transRef .
            $transId . $status . $isTest . $privateKey
        );
        $expected = hash('sha512', $hashInput);

        return strtolower($payload['HashCheck'] ?? '') === $expected;
    }
}
