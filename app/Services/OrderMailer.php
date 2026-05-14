<?php

namespace App\Services;

/**
 * Sends transactional order emails via Resend.
 * PDF invoice generation is implemented in M6.
 */
class OrderMailer
{
    /**
     * Send order confirmation email to the customer.
     * Called after payment is confirmed.
     */
    public static function sendConfirmation(
        \CodeIgniter\Database\BaseConnection $db,
        int $orderId
    ): void {
        $apiKey = env('RESEND_API_KEY', '');
        if ($apiKey === '') return; // Silent no-op in dev

        $order = $db->table('shop_orders')->where('id', $orderId)->get()->getRowArray();
        if (!$order) return;

        $items = $db->table('shop_order_items')
            ->where('order_id', $orderId)
            ->get()->getResultArray();

        $settingsRows = $db->table('settings')
            ->whereIn('key', ['site_name', 'contact_email', 'shop_notification_email', 'shop_currency', 'shop_vat_enabled', 'shop_vat_rate'])
            ->get()->getResultArray();
        $settings = array_column($settingsRows, 'value', 'key');

        $siteName    = $settings['site_name']             ?? 'Our Shop';
        $fromEmail   = $settings['contact_email']         ?? '';
        $notifyEmail = $settings['shop_notification_email'] ?? '';

        if ($fromEmail === '') return;

        $currency = $order['currency'] ?? 'ZAR';
        $fmt = new \NumberFormatter('en-ZA', \NumberFormatter::CURRENCY);

        $itemLines = array_map(fn($i) => sprintf(
            '  %s%s × %d — %s',
            $i['product_name'],
            $i['variant_name'] ? " ({$i['variant_name']})" : '',
            $i['qty'],
            $fmt->formatCurrency($i['line_total_cents'] / 100, $currency)
        ), $items);

        $body  = "Hi {$order['first_name']},\n\n";
        $body .= "Thank you for your order #{$order['id']}! Here's a summary:\n\n";
        $body .= implode("\n", $itemLines) . "\n\n";
        $body .= "Total: " . $fmt->formatCurrency($order['total_cents'] / 100, $currency) . "\n\n";
        $body .= "Your invoice is attached to this email.\n";
        $body .= "We'll be in touch with shipping details.\n\n";
        $body .= "— {$siteName}";

        // Generate PDF invoice
        $pdfBytes   = InvoicePdf::generate($order, $items, $settings);
        $pdfBase64  = base64_encode($pdfBytes);

        $payload = [
            'from'    => "{$siteName} <{$fromEmail}>",
            'to'      => [$order['email']],
            'subject' => "Order Confirmed — #{$order['id']}",
            'text'    => $body,
            'attachments' => [[
                'filename'    => "invoice-{$order['id']}.pdf",
                'content'     => $pdfBase64,
                'content_type'=> 'application/pdf',
            ]],
        ];

        // BCC merchant
        if ($notifyEmail !== '') {
            $payload['bcc'] = [$notifyEmail];
        }

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
            log_message('error', "OrderMailer failed [{$status}]: {$response}");
        }
    }
}
