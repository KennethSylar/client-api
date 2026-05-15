<?php

namespace App\Infrastructure\Gateways;

use App\Application\Ports\PaymentGatewayInterface;
use App\Domain\Orders\Order;

class PayFastGateway implements PaymentGatewayInterface
{
    public function buildPaymentUrl(
        Order  $order,
        array  $gatewaySettings,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl,
    ): string {
        $merchantId  = $gatewaySettings['shop_payfast_merchant_id']  ?? '';
        $merchantKey = $gatewaySettings['shop_payfast_merchant_key'] ?? '';
        $passphrase  = $gatewaySettings['shop_payfast_passphrase']   ?? '';

        $amount = number_format($order->total->amountCents / 100, 2, '.', '');

        // PayFast requires fields in this exact order for signature calculation
        $isTest = env('PAYFAST_TEST', 'true') !== 'false';
        $host   = $isTest ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';

        // PayFast requires fields in this exact order for signature calculation.
        // In sandbox mode use the official buyer account — PayFast blocks payment
        // when the buyer email matches the merchant's account email.
        $data = [
            'merchant_id'   => $merchantId,
            'merchant_key'  => $merchantKey,
            'return_url'    => $returnUrl,
            'cancel_url'    => $cancelUrl,
            'notify_url'    => $notifyUrl,
            'name_first'    => $order->firstName,
            'name_last'     => $order->lastName,
            'email_address' => $isTest ? 'sbtu01@payfast.co.za' : $order->email,
            'm_payment_id'  => (string) $order->id,
            'amount'        => $amount,
            'item_name'     => "Order #{$order->id}",
        ];

        $sigParts = [];
        foreach ($data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $sigParts[] = $key . '=' . urlencode($value);
            }
        }
        $sigString = implode('&', $sigParts);
        if ($passphrase !== '') {
            $sigString .= '&passphrase=' . urlencode($passphrase);
        }
        $data['signature'] = md5($sigString);

        return "https://{$host}/eng/process?" . http_build_query($data);
    }

    public function verifyNotification(array $payload, array $gatewaySettings): bool
    {
        $passphrase = $gatewaySettings['shop_payfast_passphrase'] ?? '';

        // Build signature string preserving PayFast's field order (do NOT ksort)
        $pfParamString = '';
        foreach ($payload as $key => $value) {
            if ($key === 'signature') continue;
            $pfParamString .= $key . '=' . urlencode(trim((string) $value)) . '&';
        }
        $sigString = rtrim($pfParamString, '&');
        if ($passphrase !== '') {
            $sigString .= '&passphrase=' . urlencode($passphrase);
        }

        return md5($sigString) === ($payload['signature'] ?? '');
    }
}
