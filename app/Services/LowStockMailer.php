<?php

namespace App\Services;

/**
 * LowStockMailer
 *
 * Sends a low-stock alert email to the admin when a product's stock_qty
 * drops to or below its low_stock_threshold.
 *
 * Debounce: one email per product per 24 hours (tracked via low_stock_alerted_at).
 *
 * Called from:
 *   - Admin\Shop\Stock::adjust()   (manual adjustment)
 *   - [M5] Order checkout flow     (stock decrement on order)
 *   - [M6] Refund flow             (restock after refund — no alert needed, skip)
 *
 * Usage:
 *   LowStockMailer::checkAndSend($db, $productId);
 */
class LowStockMailer
{
    /**
     * Check the product's stock and send an alert if:
     *   1. track_stock is enabled
     *   2. stock_qty is > 0 and <= low_stock_threshold  (positive stock, but low)
     *      OR stock_qty is 0                             (out of stock)
     *   3. No alert has been sent in the last 24 hours
     *
     * Silently no-ops if RESEND_API_KEY is not configured (e.g. in test/dev).
     */
    public static function checkAndSend(
        \CodeIgniter\Database\BaseConnection $db,
        int $productId
    ): void {
        $product = $db->table('shop_products')
            ->where('id', $productId)
            ->get()->getRowArray();

        if (!$product) return;

        // Only for tracked-stock products that are low or out
        if (!(bool) $product['track_stock']) return;

        $qty       = (int) $product['stock_qty'];
        $threshold = (int) $product['low_stock_threshold'];

        if ($qty > $threshold) return; // still healthy

        // Debounce: skip if an alert was sent in the last 24 hours
        if ($product['low_stock_alerted_at'] !== null) {
            $lastAlert = strtotime($product['low_stock_alerted_at']);
            if ((time() - $lastAlert) < 86400) return;
        }

        // Stamp before sending so concurrent requests don't double-send
        $db->table('shop_products')
           ->where('id', $productId)
           ->update(['low_stock_alerted_at' => date('Y-m-d H:i:s')]);

        self::send($db, $product, $qty, $threshold);
    }

    // ----------------------------------------------------------------
    // Private
    // ----------------------------------------------------------------

    private static function send(
        \CodeIgniter\Database\BaseConnection $db,
        array $product,
        int   $qty,
        int   $threshold
    ): void {
        $apiKey = getenv('RESEND_API_KEY');

        if (empty($apiKey)) {
            log_message('info', "LowStockMailer: RESEND_API_KEY not set, skipping alert for product #{$product['id']}");
            return;
        }

        // Resolve recipient: shop_low_stock_alert_email setting, else contactToEmail env var
        $toEmail = self::alertEmail($db);

        if (empty($toEmail)) {
            log_message('error', "LowStockMailer: no recipient configured for product #{$product['id']}");
            return;
        }

        $from = getenv('RESEND_FROM') ?: ('noreply@contact.' . parse_url(getenv('app.baseURL') ?: 'localhost', PHP_URL_HOST));

        try {
            $resend = \Resend::client($apiKey);
            $resend->emails->send([
                'from'    => 'Shop Alerts <' . $from . '>',
                'to'      => [$toEmail],
                'subject' => $qty === 0
                    ? "[Stock Alert] {$product['name']} is OUT OF STOCK"
                    : "[Stock Alert] {$product['name']} is running low ({$qty} remaining)",
                'html'    => self::buildHtml($product, $qty, $threshold),
            ]);
        } catch (\Exception $e) {
            log_message('error', "LowStockMailer: Resend error for product #{$product['id']}: " . $e->getMessage());
        }
    }

    private static function alertEmail(\CodeIgniter\Database\BaseConnection $db): string
    {
        $row = $db->table('settings')
            ->where('key', 'shop_low_stock_alert_email')
            ->get()->getRowArray();

        if (!empty($row['value'])) {
            return $row['value'];
        }

        // Fall back to the general contact email
        return getenv('app.contactToEmail') ?: '';
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function buildHtml(array $product, int $qty, int $threshold): string
    {
        $name       = self::e($product['name']);
        $slug       = self::e($product['slug']);
        $qtyColor   = $qty === 0 ? '#dc2626' : '#d97706';
        $qtyLabel   = $qty === 0 ? 'OUT OF STOCK' : "{$qty} remaining";
        $statusText = $qty === 0
            ? 'This product is <strong>out of stock</strong> and cannot be purchased until restocked.'
            : "Stock has dropped to <strong>{$qty} units</strong>, which is at or below the alert threshold of <strong>{$threshold}</strong>.";
        $year       = date('Y');
        $sentAt     = date('l, d F Y \a\t H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Low Stock Alert</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f5;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="560"
        style="max-width:560px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background-color:#7c2d12;padding:24px 32px;">
            <p style="margin:0;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#fca5a5;">
              Shop Inventory Alert
            </p>
            <h1 style="margin:6px 0 0;font-size:20px;font-weight:700;color:#ffffff;line-height:1.3;">
              Low Stock Warning
            </h1>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:28px 32px;">

            <!-- Stock badge -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
              <tr>
                <td style="background-color:{$qtyColor};border-radius:6px;padding:6px 14px;">
                  <span style="font-size:13px;font-weight:700;color:#ffffff;letter-spacing:0.5px;">{$qtyLabel}</span>
                </td>
              </tr>
            </table>

            <!-- Product details -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
              style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px;">
              <tr style="background-color:#f9fafb;">
                <td style="padding:10px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;width:120px;">Product</td>
                <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#111827;border-bottom:1px solid #e5e7eb;">{$name}</td>
              </tr>
              <tr>
                <td style="padding:10px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;background-color:#f9fafb;border-bottom:1px solid #e5e7eb;">Slug</td>
                <td style="padding:10px 16px;font-size:13px;font-family:monospace;color:#374151;border-bottom:1px solid #e5e7eb;">{$slug}</td>
              </tr>
              <tr style="background-color:#f9fafb;">
                <td style="padding:10px 16px;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#6b7280;">Stock Qty</td>
                <td style="padding:10px 16px;font-size:14px;font-weight:700;color:{$qtyColor};">{$qty}</td>
              </tr>
            </table>

            <p style="margin:0 0 24px;font-size:14px;color:#374151;line-height:1.7;">
              {$statusText}
              Please log in to the admin panel to update the stock level.
            </p>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background-color:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 32px;">
            <p style="margin:0;font-size:11px;color:#9ca3af;">
              Automated alert &bull; {$sentAt} &bull; &copy; {$year}
            </p>
            <p style="margin:4px 0 0;font-size:11px;color:#9ca3af;">
              Alerts are sent at most once every 24 hours per product.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
    }
}
