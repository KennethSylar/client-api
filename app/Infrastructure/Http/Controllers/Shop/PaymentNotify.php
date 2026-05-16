<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Application\Orders\Commands\CancelOrderCommand;
use App\Application\Orders\Commands\RecordPaymentCommand;
use App\Application\Orders\Queries\GetOrderQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class PaymentNotify extends BaseController
{
    public function payfast(): \CodeIgniter\HTTP\ResponseInterface
    {
        $post = $this->request->getPost();

        if (empty($post['m_payment_id']) || empty($post['payment_status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        // Rate limit: 30 notifications per minute per IP
        $ip = $this->request->getIPAddress();
        if ($this->rateLimited("payfast_notify_{$ip}", 30, 60)) {
            log_message('warning', "PayFast ITN rate limit exceeded from {$ip}");
            return $this->response->setStatusCode(429)->setBody('Too Many Requests');
        }

        $orderId = (int) $post['m_payment_id'];

        $gatewaySettings = service('settingsRepository')->getMany([
            'shop_payfast_merchant_id',
            'shop_payfast_merchant_key',
            'shop_payfast_passphrase',
        ]);

        if (!service('payfastGateway')->verifyNotification($post, $gatewaySettings)) {
            log_message('error', "PayFast ITN signature mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid notification');
        }

        $order = service('getOrderHandler')->handle(new GetOrderQuery(id: $orderId));
        if (!$order) {
            log_message('error', "PayFast ITN for unknown order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid notification');
        }

        $expectedRand = $order->total->amountCents / 100;
        $receivedRand = (float) ($post['amount_gross'] ?? 0);
        if (abs($expectedRand - $receivedRand) > 0.05) {
            log_message('error', "PayFast amount mismatch: expected {$expectedRand}, got {$receivedRand} for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Amount mismatch');
        }

        $paymentStatus = $post['payment_status'];
        $pfPaymentId   = $post['pf_payment_id'] ?? '';

        if ($paymentStatus === 'COMPLETE') {
            // Idempotency: skip if already paid with the same gateway reference
            if ($order->isPaid() && $order->paymentReference === $pfPaymentId) {
                return $this->response->setStatusCode(200)->setBody('OK');
            }

            service('recordPaymentHandler')->handle(new RecordPaymentCommand(
                orderId:   $orderId,
                gateway:   'payfast',
                reference: $pfPaymentId,
            ));
        } elseif (in_array($paymentStatus, ['FAILED', 'CANCELLED'], true)) {
            service('cancelOrderHandler')->handle(new CancelOrderCommand(
                orderId: $orderId,
                note:    "PayFast: {$paymentStatus}",
            ));
        }

        return $this->response->setStatusCode(200)->setBody('OK');
    }

    public function ozow(): \CodeIgniter\HTTP\ResponseInterface
    {
        // Ozow sends form-encoded (application/x-www-form-urlencoded)
        $body = $this->request->getPost();

        if (empty($body['TransactionReference']) || empty($body['Status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        // Rate limit: 30 notifications per minute per IP
        $ip = $this->request->getIPAddress();
        if ($this->rateLimited("ozow_notify_{$ip}", 30, 60)) {
            log_message('warning', "Ozow notify rate limit exceeded from {$ip}");
            return $this->response->setStatusCode(429)->setBody('Too Many Requests');
        }

        $orderId = (int) $body['TransactionReference'];

        $gatewaySettings = service('settingsRepository')->getMany([
            'shop_ozow_site_code',
            'shop_ozow_private_key',
        ]);

        if (!service('ozowGateway')->verifyNotification($body, $gatewaySettings)) {
            log_message('error', "Ozow hash mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid notification');
        }

        $order = service('getOrderHandler')->handle(new GetOrderQuery(id: $orderId));
        if (!$order) {
            log_message('error', "Ozow notify for unknown order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid notification');
        }

        $expectedRand = $order->total->amountCents / 100;
        $receivedRand = (float) ($body['Amount'] ?? 0);
        if (abs($expectedRand - $receivedRand) > 0.05) {
            log_message('error', "Ozow amount mismatch: expected {$expectedRand}, got {$receivedRand} for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Amount mismatch');
        }

        $status = strtolower($body['Status'] ?? '');
        $ref    = $body['TransactionId'] ?? '';

        match ($status) {
            'complete' => ($order->isPaid() && $order->paymentReference === $ref)
                ? null // idempotency: already processed
                : service('recordPaymentHandler')->handle(new RecordPaymentCommand(
                    orderId:   $orderId,
                    gateway:   'ozow',
                    reference: $ref,
                )),
            'cancelled',
            'error' => service('cancelOrderHandler')->handle(new CancelOrderCommand(
                orderId: $orderId,
                note:    "Ozow: {$status}",
            )),
            default => null,
        };

        return $this->response->setStatusCode(200)->setBody('OK');
    }
}
