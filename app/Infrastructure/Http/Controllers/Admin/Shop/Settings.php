<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Core\Commands\UpdateSettingsCommand;
use App\Application\Core\Queries\GetSettingsQuery;
use App\Infrastructure\Http\Controllers\BaseController;

/**
 * Shop-scoped settings — accessible to both admin and shop_admin roles.
 * Exposes only shop_* keys; does NOT include site/contact/appearance settings.
 */
class Settings extends BaseController
{
    private const SHOP_KEYS = [
        'shop_enabled',
        'shop_mode',
        'shop_featured_product_slug',
        'shop_currency',
        'shop_vat_enabled',
        'shop_vat_rate',
        'shop_shipping_rate',
        'shop_free_shipping_from',
        'shop_notification_email',
        'shop_low_stock_alert_email',
        'shop_payfast_enabled',
        'shop_payfast_merchant_id',
        'shop_payfast_merchant_key',
        'shop_payfast_passphrase',
        'shop_ozow_enabled',
        'shop_ozow_site_code',
        'shop_ozow_private_key',
        'shop_ozow_api_key',
        'shop_wishlist_enabled',
    ];

    private const SENSITIVE_KEYS = [
        'shop_payfast_merchant_key',
        'shop_payfast_passphrase',
        'shop_ozow_private_key',
        'shop_ozow_api_key',
    ];

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $settings = service('getSettingsHandler')->handle(
            new GetSettingsQuery(keys: self::SHOP_KEYS)
        );

        foreach (self::SENSITIVE_KEYS as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = '••••••••';
            }
        }

        return $this->ok($settings);
    }

    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body)) {
            return $this->error('No data provided.', 400);
        }

        // Only allow shop_* keys — strip anything else
        $filtered = array_filter(
            $body,
            fn($key) => in_array($key, self::SHOP_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );

        service('updateSettingsHandler')->handle(new UpdateSettingsCommand($filtered));

        return $this->ok();
    }
}
