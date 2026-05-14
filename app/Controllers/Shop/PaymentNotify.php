<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;

/**
 * Handles ITN (Instant Transaction Notifications) from PayFast and Ozow.
 * These endpoints are called server-to-server — NOT by the browser.
 */
class PaymentNotify extends BaseController
{
    // ── PayFast ITN ──────────────────────────────────────────────────

    /**
     * POST /shop/payment/payfast/notify
     */
    public function payfast(): \CodeIgniter\HTTP\ResponseInterface
    {
        $post = $this->request->getPost();

        if (empty($post['m_payment_id']) || empty($post['payment_status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        $orderId = (int)$post['m_payment_id'];
        $db      = \Config\Database::connect();

        $order = $db->table('shop_orders')->where('id', $orderId)->get()->getRowArray();
        if (!$order) {
            return $this->response->setStatusCode(404)->setBody('Order not found');
        }

        // Verify signature
        if (!$this->verifyPayfastSignature($post, $db)) {
            log_message('error', "PayFast ITN signature mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid signature');
        }

        // Verify amount matches (within 5 cents to account for rounding)
        $expectedRand  = $order['total_cents'] / 100;
        $receivedRand  = (float)($post['amount_gross'] ?? 0);
        if (abs($expectedRand - $receivedRand) > 0.05) {
            log_message('error', "PayFast amount mismatch: expected {$expectedRand}, got {$receivedRand}");
            return $this->response->setStatusCode(400)->setBody('Amount mismatch');
        }

        $paymentStatus = $post['payment_status'];

        if ($paymentStatus === 'COMPLETE') {
            $this->markPaid($db, $order, 'payfast', $post['pf_payment_id'] ?? '');
        } elseif (in_array($paymentStatus, ['FAILED','CANCELLED'], true)) {
            $this->markCancelled($db, $order, "PayFast: {$paymentStatus}");
        }
        // PENDING — do nothing, wait for COMPLETE

        return $this->response->setStatusCode(200)->setBody('OK');
    }

    // ── Ozow notify ──────────────────────────────────────────────────

    /**
     * POST /shop/payment/ozow/notify
     */
    public function ozow(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        if (empty($body)) {
            $body = $this->request->getPost();
        }

        if (empty($body['TransactionReference']) || empty($body['Status'])) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        $orderId = (int)$body['TransactionReference'];
        $db      = \Config\Database::connect();

        $order = $db->table('shop_orders')->where('id', $orderId)->get()->getRowArray();
        if (!$order) {
            return $this->response->setStatusCode(404)->setBody('Order not found');
        }

        // Verify hash
        if (!$this->verifyOzowHash($body, $db)) {
            log_message('error', "Ozow hash mismatch for order {$orderId}");
            return $this->response->setStatusCode(400)->setBody('Invalid hash');
        }

        $status = strtolower($body['Status'] ?? '');
        $ref    = $body['TransactionId'] ?? '';

        match ($status) {
            'complete'   => $this->markPaid($db, $order, 'ozow', $ref),
            'cancelled',
            'error'      => $this->markCancelled($db, $order, "Ozow: {$status}"),
            default      => null,
        };

        return $this->response->setStatusCode(200)->setBody('OK');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function markPaid(
        \CodeIgniter\Database\BaseConnection $db,
        array $order,
        string $gateway,
        string $reference
    ): void {
        if ($order['status'] !== 'pending') return; // idempotent

        $db->table('shop_orders')->where('id', $order['id'])->update([
            'status'             => 'paid',
            'payment_reference'  => $reference,
            'paid_at'            => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $db->table('shop_order_status_log')->insert([
            'order_id'    => $order['id'],
            'from_status' => 'pending',
            'to_status'   => 'paid',
            'note'        => "Payment confirmed via {$gateway}. Ref: {$reference}",
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Send confirmation email (fire-and-forget; M6 implements this)
        try {
            \App\Services\OrderMailer::sendConfirmation($db, (int)$order['id']);
        } catch (\Throwable $e) {
            log_message('error', 'Order confirmation email failed: ' . $e->getMessage());
        }
    }

    private function markCancelled(
        \CodeIgniter\Database\BaseConnection $db,
        array $order,
        string $note
    ): void {
        if ($order['status'] !== 'pending') return;

        $db->table('shop_orders')->where('id', $order['id'])->update([
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->table('shop_order_status_log')->insert([
            'order_id'    => $order['id'],
            'from_status' => 'pending',
            'to_status'   => 'cancelled',
            'note'        => $note,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Restore stock
        $items = $db->table('shop_order_items')->where('order_id', $order['id'])->get()->getResultArray();
        foreach ($items as $item) {
            if ($item['variant_id']) {
                $variant = $db->table('shop_product_variants')->where('id', $item['variant_id'])->get()->getRowArray();
                if ($variant && $variant['track_stock']) {
                    $db->table('shop_product_variants')
                        ->where('id', $item['variant_id'])
                        ->set('stock_qty', "stock_qty + {$item['qty']}", false)
                        ->update();
                    \App\Controllers\Admin\Shop\Stock::logAdjustment(
                        $db, (int)$item['product_id'], (int)$item['variant_id'], $item['qty'], 'refund', $order['id']
                    );
                }
            } elseif ($item['product_id']) {
                $product = $db->table('shop_products')->where('id', $item['product_id'])->get()->getRowArray();
                if ($product && $product['track_stock']) {
                    $db->table('shop_products')
                        ->where('id', $item['product_id'])
                        ->set('stock_qty', "stock_qty + {$item['qty']}", false)
                        ->update();
                    \App\Controllers\Admin\Shop\Stock::logAdjustment(
                        $db, (int)$item['product_id'], null, $item['qty'], 'refund', $order['id']
                    );
                }
            }
        }
    }

    private function verifyPayfastSignature(array $post, \CodeIgniter\Database\BaseConnection $db): bool
    {
        $passphrase = $db->table('settings')->where('key', 'shop_payfast_passphrase')->get()->getRowArray()['value'] ?? '';

        // Remove signature from data before hashing
        $data = $post;
        unset($data['signature']);
        ksort($data);

        $sigString = http_build_query($data);
        if ($passphrase !== '') {
            $sigString .= '&passphrase=' . urlencode($passphrase);
        }

        return md5($sigString) === ($post['signature'] ?? '');
    }

    private function verifyOzowHash(array $body, \CodeIgniter\Database\BaseConnection $db): bool
    {
        $privateKey  = $db->table('settings')->where('key', 'shop_ozow_private_key')->get()->getRowArray()['value'] ?? '';
        $siteCode    = $body['SiteCode']    ?? '';
        $countryCode = $body['CountryCode'] ?? '';
        $currencyCode= $body['CurrencyCode']?? '';
        $amount      = $body['Amount']      ?? '';
        $status      = $body['Status']      ?? '';
        $transRef    = $body['TransactionReference'] ?? '';
        $transId     = $body['TransactionId'] ?? '';
        $isTest      = $body['IsTest']      ?? '';

        $hashInput = strtolower(
            $siteCode . $countryCode . $currencyCode . $amount . $transRef .
            $transId . $status . $isTest . $privateKey
        );
        $expected = hash('sha512', $hashInput);

        return strtolower($body['HashCheck'] ?? '') === $expected;
    }
}
