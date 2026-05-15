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
        $siteCode   = trim($gatewaySettings['shop_ozow_site_code']   ?? '');
        $privateKey = trim($gatewaySettings['shop_ozow_private_key'] ?? '');
        $apiKey     = trim($gatewaySettings['shop_ozow_api_key']     ?? '');

        $isTest    = env('OZOW_TEST', true) !== false;
        $isTestStr = $isTest ? 'true' : 'false';

        $errorUrl   = $cancelUrl . (str_contains($cancelUrl, '?') ? '&' : '?') . 'error=1';
        $amount     = number_format($order->total->amountCents / 100, 2, '.', '');
        $transRef   = (string) $order->id;
        $bankRef    = "ORD{$order->id}";

        // Hash field order per v1.0 docs (Step 1: Post from merchant website)
        // SiteCode + CountryCode + CurrencyCode + Amount + TransactionReference +
        // BankReference + CancelUrl + ErrorUrl + SuccessUrl + NotifyUrl + IsTest + PrivateKey
        $hashInput = strtolower(
            $siteCode . 'ZA' . $order->currency . $amount . $transRef .
            $bankRef . $cancelUrl . $errorUrl . $returnUrl . $notifyUrl . $isTestStr . $privateKey
        );
        $hash = hash('sha512', $hashInput);

        $payload = [
            'siteCode'             => $siteCode,
            'countryCode'          => 'ZA',
            'currencyCode'         => $order->currency,
            'amount'               => $amount,
            'transactionReference' => $transRef,
            'bankReference'        => $bankRef,
            'cancelUrl'            => $cancelUrl,
            'errorUrl'             => $errorUrl,
            'successUrl'           => $returnUrl,
            'notifyUrl'            => $notifyUrl,
            'isTest'               => $isTest,
            'hashCheck'            => $hash,
        ];

        $baseUrl = 'https://api.ozow.com/postpaymentrequest';

        $ch = curl_init($baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'ApiKey: ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException("Ozow payment request failed (HTTP {$httpCode}).");
        }

        $data = json_decode($response, true);
        $url  = $data['url'] ?? '';

        if (empty($url)) {
            $error = $data['errorMessage'] ?? 'Unknown error';
            throw new \RuntimeException("Ozow did not return a payment URL: {$error}");
        }

        return $url;
    }

    public function verifyNotification(array $payload, array $gatewaySettings): bool
    {
        $privateKey = trim($gatewaySettings['shop_ozow_private_key'] ?? '');

        // Hash field order per v1.0 docs (Step 2: Response hash check)
        // Fields 1–13: SiteCode, TransactionId, TransactionReference, Amount, Status,
        // Optional1–5, CurrencyCode, IsTest, StatusMessage — then append PrivateKey
        $hashInput = strtolower(
            ($payload['SiteCode']             ?? '') .
            ($payload['TransactionId']        ?? '') .
            ($payload['TransactionReference'] ?? '') .
            ($payload['Amount']               ?? '') .
            ($payload['Status']               ?? '') .
            ($payload['Optional1']            ?? '') .
            ($payload['Optional2']            ?? '') .
            ($payload['Optional3']            ?? '') .
            ($payload['Optional4']            ?? '') .
            ($payload['Optional5']            ?? '') .
            ($payload['CurrencyCode']         ?? '') .
            ($payload['IsTest']               ?? '') .
            ($payload['StatusMessage']        ?? '') .
            $privateKey
        );
        $expected = hash('sha512', $hashInput);

        // Trim leading zeros before comparing (per Ozow warning)
        return ltrim(strtolower($payload['Hash'] ?? ''), '0') === ltrim($expected, '0');
    }
}
