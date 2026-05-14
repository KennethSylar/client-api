<?php

namespace App\Infrastructure\Services;

use App\Application\Ports\MailerInterface;
use App\Domain\Orders\Order;

class ResendMailer implements MailerInterface
{
    public function sendOrderConfirmation(Order $order, array $items, array $settings, string $pdfBase64): void
    {
        $apiKey = env('RESEND_API_KEY', '');
        if ($apiKey === '') return;

        $siteName    = $settings['site_name']               ?? 'Our Shop';
        $fromEmail   = $settings['contact_email']           ?? '';
        $notifyEmail = $settings['shop_notification_email'] ?? '';

        if ($fromEmail === '') return;

        $currency = $order->currency;
        $fmt = new \NumberFormatter('en-ZA', \NumberFormatter::CURRENCY);

        $itemLines = array_map(fn($i) => sprintf(
            '  %s%s × %d — %s',
            $i['product_name'],
            $i['variant_name'] ? " ({$i['variant_name']})" : '',
            $i['qty'],
            $fmt->formatCurrency($i['line_total_cents'] / 100, $currency)
        ), $items);

        $body  = "Hi {$order->firstName},\n\n";
        $body .= "Thank you for your order #{$order->id}! Here's a summary:\n\n";
        $body .= implode("\n", $itemLines) . "\n\n";
        $body .= "Total: " . $fmt->formatCurrency($order->total->toFloat(), $currency) . "\n\n";
        $body .= "Your invoice is attached to this email.\n";
        $body .= "We'll be in touch with shipping details.\n\n";
        $body .= "— {$siteName}";

        $payload = [
            'from'    => "{$siteName} <{$fromEmail}>",
            'to'      => [$order->email],
            'subject' => "Order Confirmed — #{$order->id}",
            'text'    => $body,
            'attachments' => [[
                'filename'     => "invoice-{$order->id}.pdf",
                'content'      => $pdfBase64,
                'content_type' => 'application/pdf',
            ]],
        ];

        if ($notifyEmail !== '') {
            $payload['bcc'] = [$notifyEmail];
        }

        $this->post($apiKey, $payload);
    }

    public function sendLowStockAlert(array $product, array $settings): void
    {
        $apiKey = getenv('RESEND_API_KEY');
        if (empty($apiKey)) return;

        $toEmail = $settings['shop_low_stock_alert_email']
            ?? getenv('app.contactToEmail')
            ?: '';

        if ($toEmail === '') {
            log_message('error', "ResendMailer: no recipient for low-stock alert product #{$product['id']}");
            return;
        }

        $from = getenv('RESEND_FROM')
            ?: ('noreply@contact.' . parse_url(getenv('app.baseURL') ?: 'localhost', PHP_URL_HOST));

        $qty       = (int) $product['stock_qty'];
        $threshold = (int) $product['low_stock_threshold'];

        try {
            $resend = \Resend::client($apiKey);
            $resend->emails->send([
                'from'    => "Shop Alerts <{$from}>",
                'to'      => [$toEmail],
                'subject' => $qty === 0
                    ? "[Stock Alert] {$product['name']} is OUT OF STOCK"
                    : "[Stock Alert] {$product['name']} is running low ({$qty} remaining)",
                'html'    => $this->buildLowStockHtml($product, $qty, $threshold),
            ]);
        } catch (\Exception $e) {
            log_message('error', "ResendMailer: Resend error for product #{$product['id']}: " . $e->getMessage());
        }
    }

    public function sendContactEnquiry(
        string  $name,
        string  $email,
        ?string $phone,
        ?string $service,
        string  $message,
        array   $settings,
    ): void {
        $apiKey = env('RESEND_API_KEY', '');
        if ($apiKey === '') return;

        $siteName  = $settings['site_name']   ?? 'Our Shop';
        $toEmail   = $settings['contact_email'] ?? '';
        if ($toEmail === '') return;

        $phoneLine   = $phone   ? "\nPhone: {$phone}"     : '';
        $serviceLine = $service ? "\nService: {$service}" : '';

        $body  = "New enquiry from {$name} <{$email}>{$phoneLine}{$serviceLine}\n\n";
        $body .= $message;

        $payload = [
            'from'    => "{$siteName} <{$toEmail}>",
            'to'      => [$toEmail],
            'reply_to'=> $email,
            'subject' => "New Enquiry from {$name}",
            'text'    => $body,
        ];

        $this->post($apiKey, $payload);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function post(string $apiKey, array $payload): void
    {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            log_message('error', "ResendMailer failed [{$status}]: {$response}");
        }
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function buildLowStockHtml(array $product, int $qty, int $threshold): string
    {
        $name       = $this->e($product['name']);
        $slug       = $this->e($product['slug']);
        $qtyColor   = $qty === 0 ? '#dc2626' : '#d97706';
        $qtyLabel   = $qty === 0 ? 'OUT OF STOCK' : "{$qty} remaining";
        $statusText = $qty === 0
            ? 'This product is <strong>out of stock</strong> and cannot be purchased until restocked.'
            : "Stock has dropped to <strong>{$qty} units</strong>, which is at or below the alert threshold of <strong>{$threshold}</strong>.";
        $year   = date('Y');
        $sentAt = date('l, d F Y \a\t H:i');

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
  <tr><td align="center" style="padding:32px 16px;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="560"
      style="max-width:560px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <tr><td style="background-color:#7c2d12;padding:24px 32px;">
        <p style="margin:0;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#fca5a5;">Shop Inventory Alert</p>
        <h1 style="margin:6px 0 0;font-size:20px;font-weight:700;color:#ffffff;line-height:1.3;">Low Stock Warning</h1>
      </td></tr>
      <tr><td style="padding:28px 32px;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
          <tr><td style="background-color:{$qtyColor};border-radius:6px;padding:6px 14px;">
            <span style="font-size:13px;font-weight:700;color:#ffffff;letter-spacing:0.5px;">{$qtyLabel}</span>
          </td></tr>
        </table>
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
          {$statusText} Please log in to the admin panel to update the stock level.
        </p>
      </td></tr>
      <tr><td style="background-color:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 32px;">
        <p style="margin:0;font-size:11px;color:#9ca3af;">Automated alert &bull; {$sentAt} &bull; &copy; {$year}</p>
        <p style="margin:4px 0 0;font-size:11px;color:#9ca3af;">Alerts are sent at most once every 24 hours per product.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
