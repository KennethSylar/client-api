<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;
use App\Controllers\Admin\Shop\Stock;

class Checkout extends BaseController
{
    /**
     * POST /shop/checkout
     *
     * Creates a pending order, decrements stock, and returns
     * the order token + payment URL (or redirect) for the chosen gateway.
     *
     * Body:
     * {
     *   "first_name": "Jane",
     *   "last_name":  "Doe",
     *   "email":      "jane@example.com",
     *   "phone":      "0821234567",          // optional
     *   "address_line1": "12 Main Rd",
     *   "address_line2": "Apt 2",            // optional
     *   "city":       "Cape Town",
     *   "province":   "Western Cape",        // optional
     *   "postal_code": "8001",
     *   "country":    "ZA",                  // default ZA
     *   "gateway":    "payfast"|"ozow",
     *   "items": [
     *     {"product_id": 1, "variant_id": null, "qty": 2, "price": 50.00},
     *     ...
     *   ],
     *   "notes": ""                          // optional
     * }
     *
     * Response 200:
     * {
     *   "order_token": "abc123",
     *   "payment_url": "https://...",  // redirect user here
     *   "gateway": "payfast"
     * }
     */
    public function place(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body = $this->jsonBody();

        // ── 1. Validate required fields ──────────────────────────────
        $required = ['first_name','last_name','email','address_line1','city','postal_code','gateway','items'];
        foreach ($required as $field) {
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
        if (!in_array($gateway, ['payfast','ozow'], true)) {
            return $this->error('Invalid payment gateway.', 400);
        }

        $db = \Config\Database::connect();

        // ── 2. Validate gateway is enabled ───────────────────────────
        $gwSetting = $gateway === 'payfast' ? 'shop_payfast_enabled' : 'shop_ozow_enabled';
        $gwEnabled = $db->table('settings')->where('key', $gwSetting)->get()->getRowArray();
        if (($gwEnabled['value'] ?? '0') !== '1') {
            return $this->error('Selected payment gateway is not available.', 400);
        }

        // ── 3. Load shop settings ─────────────────────────────────────
        $settingsRows = $db->table('settings')
            ->whereIn('key', ['shop_vat_enabled','shop_vat_rate','shop_shipping_rate','shop_free_shipping_from','shop_currency'])
            ->get()->getResultArray();
        $settings = array_column($settingsRows, 'value', 'key');

        $vatEnabled     = ($settings['shop_vat_enabled'] ?? '0') === '1';
        $vatRate        = (float)($settings['shop_vat_rate'] ?? 15);
        $shippingRate   = (float)($settings['shop_shipping_rate'] ?? 0);
        $freeShippingFrom = (float)($settings['shop_free_shipping_from'] ?? 0);
        $currency       = $settings['shop_currency'] ?? 'ZAR';

        // ── 4. Resolve items — re-validate stock & price server-side ──
        $resolvedItems  = [];
        $subtotalCents  = 0;

        foreach ($items as $cartItem) {
            $productId = (int)($cartItem['product_id'] ?? 0);
            $variantId = isset($cartItem['variant_id']) && $cartItem['variant_id'] ? (int)$cartItem['variant_id'] : null;
            $qty       = (int)($cartItem['qty'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                return $this->error('Invalid cart item.', 400);
            }

            $product = $db->table('shop_products')
                ->where('id', $productId)
                ->where('active', 1)
                ->get()->getRowArray();

            if (!$product) {
                return $this->error("Product #{$productId} is no longer available.", 409);
            }

            $effectivePrice = (float)$product['price'];
            $trackStock     = (bool)$product['track_stock'];
            $stockQty       = (int)$product['stock_qty'];
            $variantName    = null;

            if ($variantId) {
                $variant = $db->table('shop_product_variants')
                    ->where('id', $variantId)
                    ->where('product_id', $productId)
                    ->get()->getRowArray();

                if (!$variant) {
                    return $this->error("Variant #{$variantId} not found.", 409);
                }

                $effectivePrice += (float)$variant['price_adjustment'];
                $variantName     = $variant['name'];
                $trackStock      = (bool)$variant['track_stock'];
                $stockQty        = (int)$variant['stock_qty'];
            }

            // Enforce stock
            if ($trackStock) {
                if ($stockQty <= 0) {
                    return $this->error("{$product['name']} is out of stock.", 409);
                }
                if ($qty > $stockQty) {
                    return $this->error("Only {$stockQty} units of {$product['name']} available.", 409);
                }
            }

            $unitPriceCents = (int)round($effectivePrice * 100);
            $lineCents      = $unitPriceCents * $qty;
            $subtotalCents += $lineCents;

            $resolvedItems[] = [
                'product_id'       => $productId,
                'variant_id'       => $variantId,
                'product_name'     => $product['name'],
                'variant_name'     => $variantName,
                'qty'              => $qty,
                'unit_price_cents' => $unitPriceCents,
                'line_total_cents' => $lineCents,
                'sku'              => $product['slug'],
                'track_stock'      => $trackStock,
            ];
        }

        // ── 5. Calculate totals ───────────────────────────────────────
        $subtotalRand  = $subtotalCents / 100;
        $isFreeShip    = $freeShippingFrom > 0 && $subtotalRand >= $freeShippingFrom;
        $shippingCents = $isFreeShip ? 0 : (int)round($shippingRate * 100);
        $vatCents      = $vatEnabled ? (int)round(($subtotalCents + $shippingCents) * $vatRate / 100) : 0;
        $totalCents    = $subtotalCents + $shippingCents + $vatCents;

        // ── 6. Create order in transaction ────────────────────────────
        $token = bin2hex(random_bytes(24)); // 48-char token

        $db->transStart();

        $db->table('shop_orders')->insert([
            'token'             => $token,
            'customer_id'       => null,
            'first_name'        => trim($body['first_name']),
            'last_name'         => trim($body['last_name']),
            'email'             => strtolower(trim($body['email'])),
            'phone'             => trim($body['phone'] ?? ''),
            'address_line1'     => trim($body['address_line1']),
            'address_line2'     => trim($body['address_line2'] ?? ''),
            'city'              => trim($body['city']),
            'province'          => trim($body['province'] ?? ''),
            'postal_code'       => trim($body['postal_code']),
            'country'           => strtoupper(trim($body['country'] ?? 'ZA')),
            'subtotal_cents'    => $subtotalCents,
            'vat_cents'         => $vatCents,
            'shipping_cents'    => $shippingCents,
            'total_cents'       => $totalCents,
            'currency'          => $currency,
            'status'            => 'pending',
            'payment_gateway'   => $gateway,
            'notes'             => trim($body['notes'] ?? ''),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $orderId = (int)$db->insertID();

        foreach ($resolvedItems as $item) {
            $db->table('shop_order_items')->insert([
                'order_id'         => $orderId,
                'product_id'       => $item['product_id'],
                'variant_id'       => $item['variant_id'],
                'product_name'     => $item['product_name'],
                'variant_name'     => $item['variant_name'],
                'qty'              => $item['qty'],
                'unit_price_cents' => $item['unit_price_cents'],
                'line_total_cents' => $item['line_total_cents'],
                'sku'              => $item['sku'],
            ]);

            // Decrement stock
            if ($item['track_stock']) {
                if ($item['variant_id']) {
                    $db->table('shop_product_variants')
                        ->where('id', $item['variant_id'])
                        ->set('stock_qty', "stock_qty - {$item['qty']}", false)
                        ->update();
                } else {
                    $db->table('shop_products')
                        ->where('id', $item['product_id'])
                        ->set('stock_qty', "stock_qty - {$item['qty']}", false)
                        ->update();
                }

                Stock::logAdjustment($db, $item['product_id'], $item['variant_id'], -$item['qty'], 'order', $orderId);
            }
        }

        // Initial status log entry
        $db->table('shop_order_status_log')->insert([
            'order_id'    => $orderId,
            'from_status' => null,
            'to_status'   => 'pending',
            'note'        => 'Order created',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (!$db->transStatus()) {
            log_message('error', "Checkout transaction failed for token {$token}");
            return $this->error('Failed to place order. Please try again.', 500);
        }

        // ── 7. Build payment URL ──────────────────────────────────────
        $paymentUrl = $this->buildPaymentUrl($gateway, $db, $orderId, $token, $totalCents, $currency, $body);

        return $this->ok([
            'order_token' => $token,
            'payment_url' => $paymentUrl,
            'gateway'     => $gateway,
        ]);
    }

    // ── Payment URL builders ─────────────────────────────────────────

    private function buildPaymentUrl(
        string $gateway,
        \CodeIgniter\Database\BaseConnection $db,
        int $orderId,
        string $token,
        int $totalCents,
        string $currency,
        array $body
    ): string {
        return $gateway === 'payfast'
            ? $this->payfastUrl($db, $orderId, $token, $totalCents, $body)
            : $this->ozowUrl($db, $orderId, $token, $totalCents, $currency, $body);
    }

    private function payfastUrl(
        \CodeIgniter\Database\BaseConnection $db,
        int $orderId,
        string $token,
        int $totalCents,
        array $body
    ): string {
        $settings = $this->gatewaySettings($db, [
            'shop_payfast_merchant_id',
            'shop_payfast_merchant_key',
            'shop_payfast_passphrase',
        ]);

        $merchantId  = $settings['shop_payfast_merchant_id']  ?? '';
        $merchantKey = $settings['shop_payfast_merchant_key'] ?? '';
        $passphrase  = $settings['shop_payfast_passphrase']   ?? '';

        $appBase     = rtrim(env('app.baseURL', 'http://localhost:8080'), '/');
        $returnUrl   = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/') . "/shop/order/{$token}";
        $cancelUrl   = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/') . '/checkout';
        $notifyUrl   = "{$appBase}/shop/payment/payfast/notify";

        $amount = number_format($totalCents / 100, 2, '.', '');

        // PayFast requires fields in this exact order for signature calculation
        $data = [
            'merchant_id'  => $merchantId,
            'merchant_key' => $merchantKey,
            'return_url'   => $returnUrl,
            'cancel_url'   => $cancelUrl,
            'notify_url'   => $notifyUrl,
            'name_first'   => $body['first_name'],
            'name_last'    => $body['last_name'],
            'email_address'=> $body['email'],
            'm_payment_id' => (string)$orderId,
            'amount'       => $amount,
            'item_name'    => "Order #{$orderId}",
        ];

        // Signature — PayFast uses urlencode() which converts spaces to +
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

        $isTest = env('PAYFAST_TEST', 'true') !== 'false';
        $host   = $isTest ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';

        return "https://{$host}/eng/process?" . http_build_query($data);
    }

    private function ozowUrl(
        \CodeIgniter\Database\BaseConnection $db,
        int $orderId,
        string $token,
        int $totalCents,
        string $currency,
        array $body
    ): string {
        $settings = $this->gatewaySettings($db, [
            'shop_ozow_site_code',
            'shop_ozow_private_key',
            'shop_ozow_api_key',
        ]);

        $siteCode   = $settings['shop_ozow_site_code']   ?? '';
        $privateKey = $settings['shop_ozow_private_key'] ?? '';

        $siteUrl   = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/');
        $successUrl  = "{$siteUrl}/shop/order/{$token}";
        $cancelUrl   = "{$siteUrl}/checkout";
        $errorUrl    = "{$siteUrl}/checkout?error=1";
        $notifyUrl   = rtrim(env('app.baseURL', 'http://localhost:8080'), '/') . '/shop/payment/ozow/notify';

        $amount     = number_format($totalCents / 100, 2, '.', '');
        $countryCode = 'ZA';
        $currencyCode = $currency;
        $transRef   = (string)$orderId;

        // Ozow hash: lowercase(SHA512(SiteCode+CountryCode+CurrencyCode+Amount+TransactionRef+BankRef+CancelUrl+ErrorUrl+SuccessUrl+NotifyUrl+IsTest+PrivateKey))
        $isTest   = env('OZOW_TEST', 'true') !== 'false';
        $isTestStr = $isTest ? 'true' : 'false';
        $bankRef  = "ORD{$orderId}";

        $hashInput = strtolower(
            $siteCode . $countryCode . $currencyCode . $amount . $transRef .
            $bankRef . $cancelUrl . $errorUrl . $successUrl . $notifyUrl . $isTestStr . $privateKey
        );
        $hash = hash('sha512', $hashInput);

        $params = [
            'SiteCode'       => $siteCode,
            'CountryCode'    => $countryCode,
            'CurrencyCode'   => $currencyCode,
            'Amount'         => $amount,
            'TransactionReference' => $transRef,
            'BankReference'  => $bankRef,
            'CancelUrl'      => $cancelUrl,
            'ErrorUrl'       => $errorUrl,
            'SuccessUrl'     => $successUrl,
            'NotifyUrl'      => $notifyUrl,
            'IsTest'         => $isTestStr,
            'HashCheck'      => $hash,
        ];

        return 'https://pay.ozow.com/?' . http_build_query($params);
    }

    private function gatewaySettings(
        \CodeIgniter\Database\BaseConnection $db,
        array $keys
    ): array {
        $rows = $db->table('settings')->whereIn('key', $keys)->get()->getResultArray();
        return array_column($rows, 'value', 'key');
    }
}
