<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates a PDF invoice for a shop order using dompdf.
 */
class InvoicePdf
{
    /**
     * @param array $order      Row from shop_orders
     * @param array $items      Rows from shop_order_items
     * @param array $settings   Key-value settings (site_name, currency, vat, etc.)
     * @return string           Raw PDF bytes
     */
    public static function generate(array $order, array $items, array $settings): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::buildHtml($order, $items, $settings));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function buildHtml(array $order, array $items, array $settings): string
    {
        $currency  = $order['currency']    ?? $settings['shop_currency'] ?? 'ZAR';
        $siteName  = $settings['site_name'] ?? 'Our Shop';
        $vatRate   = (float)($settings['shop_vat_rate'] ?? 15);
        $vatEnabled= ($settings['shop_vat_enabled'] ?? '0') === '1';

        $fmt = fn(int $cents) => (new \NumberFormatter('en-ZA', \NumberFormatter::CURRENCY))
            ->formatCurrency($cents / 100, $currency);

        $invoiceDate = date('d F Y', strtotime($order['created_at'] ?? 'now'));
        $orderRef    = '#' . $order['id'];

        // Item rows
        $itemRows = '';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['product_name']);
            if (!empty($item['variant_name'])) {
                $name .= ' <span style="color:#6b7280;font-size:11px;">(' . htmlspecialchars($item['variant_name']) . ')</span>';
            }
            $itemRows .= sprintf(
                '<tr><td>%s</td><td class="center">%d</td><td class="right">%s</td><td class="right">%s</td></tr>',
                $name,
                $item['qty'],
                $fmt((int)$item['unit_price_cents']),
                $fmt((int)$item['line_total_cents'])
            );
        }

        // Totals
        $totals = '<tr class="subtotal"><td colspan="3" class="right">Subtotal</td><td class="right">' . $fmt((int)$order['subtotal_cents']) . '</td></tr>';
        if ($vatEnabled && (int)$order['vat_cents'] > 0) {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">VAT (' . $vatRate . '%)</td><td class="right">' . $fmt((int)$order['vat_cents']) . '</td></tr>';
        }
        if ((int)$order['shipping_cents'] > 0) {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">Shipping</td><td class="right">' . $fmt((int)$order['shipping_cents']) . '</td></tr>';
        } else {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">Shipping</td><td class="right">Free</td></tr>';
        }
        $totals .= '<tr class="total"><td colspan="3" class="right">Total</td><td class="right">' . $fmt((int)$order['total_cents']) . '</td></tr>';

        $address  = htmlspecialchars($order['address_line1']);
        if (!empty($order['address_line2'])) $address .= ', ' . htmlspecialchars($order['address_line2']);
        $address .= ', ' . htmlspecialchars($order['city']);
        if (!empty($order['province'])) $address .= ', ' . htmlspecialchars($order['province']);
        $address .= ' ' . htmlspecialchars($order['postal_code']);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#1f2937; line-height:1.5; }
            .wrap { padding:40px; }
            .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
            .company-name { font-size:20px; font-weight:700; color:#111827; }
            .invoice-title { font-size:28px; font-weight:700; color:#111827; text-align:right; }
            .invoice-meta { text-align:right; color:#6b7280; font-size:11px; margin-top:4px; }
            .section { margin-bottom:28px; }
            .section-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:6px; }
            table { width:100%; border-collapse:collapse; }
            th { padding:8px 12px; background:#f9fafb; border-bottom:2px solid #e5e7eb; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#6b7280; }
            td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
            tr.subtotal td { padding:6px 12px; border-bottom:none; color:#6b7280; font-size:11px; }
            tr.total td { padding:10px 12px; border-top:2px solid #e5e7eb; font-weight:700; font-size:14px; color:#111827; }
            .center { text-align:center; }
            .right  { text-align:right; }
            .badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
            .badge-paid { background:#d1fae5; color:#065f46; }
            .badge-pending { background:#fef3c7; color:#92400e; }
            .footer { margin-top:48px; padding-top:16px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:10px; text-align:center; }
          </style>
        </head>
        <body>
          <div class="wrap">
            <div class="header">
              <div>
                <div class="company-name">{$siteName}</div>
                <div style="margin-top:12px;font-size:11px;color:#6b7280;">Bill to:</div>
                <div style="margin-top:4px;font-size:12px;">
                  <strong>{$order['first_name']} {$order['last_name']}</strong><br>
                  {$order['email']}<br>
                  {$address}
                </div>
              </div>
              <div>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                  Ref: {$orderRef}<br>
                  Date: {$invoiceDate}
                </div>
              </div>
            </div>

            <div class="section">
              <table>
                <thead>
                  <tr>
                    <th>Description</th>
                    <th class="center" style="width:60px">Qty</th>
                    <th class="right" style="width:100px">Unit Price</th>
                    <th class="right" style="width:110px">Line Total</th>
                  </tr>
                </thead>
                <tbody>
                  {$itemRows}
                  {$totals}
                </tbody>
              </table>
            </div>

            <div class="footer">
              Thank you for your order. This document serves as your official invoice.
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}
