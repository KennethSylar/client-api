<?php

namespace App\Controllers\Shop;

use App\Application\Orders\Commands\PlaceOrderCommand;
use App\Application\Orders\DTOs\CartItemDTO;
use App\Controllers\BaseController;

class Checkout extends BaseController
{
    public function place(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body = $this->jsonBody();

        // ── 1. Validate required fields ──────────────────────────────
        foreach (['first_name', 'last_name', 'email', 'address_line1', 'city', 'postal_code', 'gateway', 'items'] as $field) {
            if (empty($body[$field])) {
                return $this->error("Missing required field: {$field}", 400);
            }
        }

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address.', 400);
        }

        $items = $body['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            return $this->error('Cart is empty.', 400);
        }

        $gateway = $body['gateway'];
        if (!in_array($gateway, ['payfast', 'ozow'], true)) {
            return $this->error('Invalid payment gateway.', 400);
        }

        // ── 2. Check gateway is enabled ───────────────────────────────
        $gwKey     = $gateway === 'payfast' ? 'shop_payfast_enabled' : 'shop_ozow_enabled';
        $gwEnabled = service('settingsRepository')->get($gwKey);
        if ($gwEnabled !== '1') {
            return $this->error('Selected payment gateway is not available.', 400);
        }

        // ── 3. Build cart DTOs ────────────────────────────────────────
        $cartItems = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['variant_id']) && $item['variant_id'] ? (int) $item['variant_id'] : null;
            $qty       = (int) ($item['qty'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                return $this->error('Invalid cart item.', 400);
            }

            $cartItems[] = new CartItemDTO($productId, $variantId, $qty);
        }

        // ── 4. Place order (validates stock, computes totals, creates order) ──
        try {
            $result = service('placeOrderHandler')->handle(new PlaceOrderCommand(
                firstName:    trim($body['first_name']),
                lastName:     trim($body['last_name']),
                email:        strtolower(trim($body['email'])),
                phone:        trim($body['phone'] ?? '') ?: null,
                addressLine1: trim($body['address_line1']),
                addressLine2: trim($body['address_line2'] ?? ''),
                city:         trim($body['city']),
                province:     trim($body['province'] ?? ''),
                postalCode:   trim($body['postal_code']),
                country:      strtoupper(trim($body['country'] ?? 'ZA')),
                gateway:      $gateway,
                items:        $cartItems,
                notes:        trim($body['notes'] ?? ''),
            ));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            log_message('error', 'Checkout failed: ' . $e->getMessage());
            return $this->error('Failed to place order. Please try again.', 500);
        }

        $order = $result->order;

        // ── 5. Build payment URL ──────────────────────────────────────
        $appBase  = rtrim(env('app.baseURL', 'http://localhost:8080'), '/');
        $siteBase = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/');

        if ($gateway === 'payfast') {
            $gatewaySettings = service('settingsRepository')->getMany([
                'shop_payfast_merchant_id',
                'shop_payfast_merchant_key',
                'shop_payfast_passphrase',
            ]);
            $paymentUrl = service('payfastGateway')->buildPaymentUrl(
                order:           $order,
                gatewaySettings: $gatewaySettings,
                returnUrl:       "{$siteBase}/shop/order/{$order->token}",
                cancelUrl:       "{$siteBase}/checkout",
                notifyUrl:       "{$appBase}/shop/payment/payfast/notify",
            );
        } else {
            $gatewaySettings = service('settingsRepository')->getMany([
                'shop_ozow_site_code',
                'shop_ozow_private_key',
                'shop_ozow_api_key',
            ]);
            $paymentUrl = service('ozowGateway')->buildPaymentUrl(
                order:           $order,
                gatewaySettings: $gatewaySettings,
                returnUrl:       "{$siteBase}/shop/order/{$order->token}",
                cancelUrl:       "{$siteBase}/checkout",
                notifyUrl:       "{$appBase}/shop/payment/ozow/notify",
            );
        }

        return $this->ok([
            'order_token' => $order->token,
            'payment_url' => $paymentUrl,
            'gateway'     => $gateway,
        ]);
    }
}
