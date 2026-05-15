<?php

namespace App\Controllers\Shop;

use App\Application\Orders\Commands\CancelOrderCommand;
use App\Application\Orders\Commands\RecordPaymentCommand;
use App\Application\Orders\Queries\GetOrderQuery;
use App\Controllers\BaseController;

class PaymentNotify extends BaseController
{
    public function payfast(): \CodeIgniter\HTTP\ResponseInterface
    {
        $post = $this->request->getPost();

        if (empty($post['m_payment_id']) || empty($post['payment_status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        $orderId = (int) $post['m_payment_id'];

        $gatewaySettings = service('settingsRepository')->getMany([
            'shop_payfast_merchant_id',
            'shop_payfast_merchant_key',
            'shop_payfast_passphrase',
        ]);

        if (!service('payfastGateway')->verifyNotification($post, $gatewaySettings)) {
            log_message('error', "PayFast ITN signature mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid signature');
        }

        $order = service('getOrderHandler')->handle(new GetOrderQuery(id: $orderId));
        if (!$order) {
            return $this->response->setStatusCode(404)->setBody('Order not found');
        }

        $expectedRand = $order->total->amountCents / 100;
        $receivedRand = (float) ($post['amount_gross'] ?? 0);
        if (abs($expectedRand - $receivedRand) > 0.05) {
            log_message('error', "PayFast amount mismatch: expected {$expectedRand}, got {$receivedRand}");
            return $this->response->setStatusCode(400)->setBody('Amount mismatch');
        }

        $paymentStatus = $post['payment_status'];

        if ($paymentStatus === 'COMPLETE') {
            service('recordPaymentHandler')->handle(new RecordPaymentCommand(
                orderId:   $orderId,
                gateway:   'payfast',
                reference: $post['pf_payment_id'] ?? '',
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
        $body = $this->jsonBody();
        if (empty($body)) {
            $body = $this->request->getPost();
        }

        if (empty($body['TransactionReference']) || empty($body['Status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        $orderId = (int) $body['TransactionReference'];

        $gatewaySettings = service('settingsRepository')->getMany([
            'shop_ozow_site_code',
            'shop_ozow_private_key',
        ]);

        if (!service('ozowGateway')->verifyNotification($body, $gatewaySettings)) {
            log_message('error', "Ozow hash mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid hash');
        }

        $order = service('getOrderHandler')->handle(new GetOrderQuery(id: $orderId));
        if (!$order) {
            return $this->response->setStatusCode(404)->setBody('Order not found');
        }

        $status = strtolower($body['Status'] ?? '');
        $ref    = $body['TransactionId'] ?? '';

        match ($status) {
            'complete' => service('recordPaymentHandler')->handle(new RecordPaymentCommand(
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
