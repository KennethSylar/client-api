<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Infrastructure\Http\Controllers\BaseController;

/**
 * Handles post-payment browser redirects.
 *
 * Payment gateways (Ozow, PayFast) reject localhost URLs as return/cancel targets.
 * These endpoints live on the public API URL (ngrok / production) so gateways accept
 * them, then bounce the user's browser to the correct frontend page.
 */
class PaymentReturn extends BaseController
{
    /** Gateway redirects user here on success — bounce to frontend order page. */
    public function success(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        $siteBase = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/');
        return redirect()->to("{$siteBase}/shop/order/{$token}?ref=payment");
    }

    /** Gateway redirects user here on cancel — bounce to frontend checkout. */
    public function cancel(): \CodeIgniter\HTTP\ResponseInterface
    {
        $siteBase = rtrim(env('NUXT_SITE_URL', 'http://localhost:3000'), '/');
        return redirect()->to("{$siteBase}/checkout");
    }
}
