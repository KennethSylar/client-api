<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\UpdateSettingsCommand;
use App\Application\Core\Queries\GetSettingsQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Settings extends BaseController
{
    private const ADMIN_SETTINGS_KEYS = [
        'site_name', 'site_tagline', 'contact_email', 'contact_phone',
        'contact_address', 'social_facebook', 'social_instagram',
        'social_linkedin', 'social_twitter', 'accreditations',
        'shop_enabled', 'shop_mode', 'shop_featured_product_slug',
        'shop_currency', 'shop_vat_enabled', 'shop_vat_rate',
        'shop_shipping_rate', 'shop_free_shipping_from',
        'shop_notification_email', 'shop_low_stock_alert_email',
        'shop_payfast_enabled', 'shop_payfast_merchant_id',
        'shop_payfast_merchant_key', 'shop_payfast_passphrase',
        'shop_ozow_enabled', 'shop_ozow_site_code',
        'shop_ozow_private_key', 'shop_ozow_api_key',
        'shop_wishlist_enabled',
        // Appearance
        'hero_variant', 'footer_variant', 'cta_headline',
        // Contact (frontend keys)
        'phone_mobile', 'phone_office', 'email', 'tagline',
        'whatsapp_number', 'whatsapp_display',
        'address_physical', 'address_postal',
    ];

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $settings = service('getSettingsHandler')->handle(
            new GetSettingsQuery(keys: self::ADMIN_SETTINGS_KEYS)
        );
        return $this->ok($settings);
    }

    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body)) {
            return $this->error('No data provided.', 400);
        }

        service('updateSettingsHandler')->handle(new UpdateSettingsCommand($body));

        return $this->ok();
    }
}
