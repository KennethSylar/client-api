<?php

namespace App\Infrastructure\Services;

use App\Application\Ports\InvoicePdfInterface;
use App\Domain\Orders\Order;
use Dompdf\Dompdf;
use Dompdf\Options;

class DompdfInvoicePdf implements InvoicePdfInterface
{
    public function generate(Order $order, array $items, array $settings): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml($order, $items, $settings));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildHtml(Order $order, array $items, array $settings): string
    {
        $currency   = $order->currency;
        $siteName   = $settings['site_name']       ?? 'Our Shop';
        $vatRate    = (float)($settings['shop_vat_rate']     ?? 15);
        $vatEnabled = ($settings['shop_vat_enabled'] ?? '0') === '1';

        $fmt = fn(int $cents) => (new \NumberFormatter('en-ZA', \NumberFormatter::CURRENCY))
            ->formatCurrency($cents / 100, $currency);

        $invoiceDate = $order->createdAt->format('d F Y');
        $orderRef    = '#' . $order->id;

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

        $totals = '<tr class="subtotal"><td colspan="3" class="right">Subtotal</td><td class="right">' . $fmt($order->subtotal->amountCents) . '</td></tr>';
        if ($vatEnabled && $order->vat->amountCents > 0) {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">VAT (' . $vatRate . '%)</td><td class="right">' . $fmt($order->vat->amountCents) . '</td></tr>';
        }
        if ($order->shipping->amountCents > 0) {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">Shipping</td><td class="right">' . $fmt($order->shipping->amountCents) . '</td></tr>';
        } else {
            $totals .= '<tr class="subtotal"><td colspan="3" class="right">Shipping</td><td class="right">Free</td></tr>';
        }
        $totals .= '<tr class="total"><td colspan="3" class="right">Total</td><td class="right">' . $fmt($order->total->amountCents) . '</td></tr>';

        $addr = $order->address;
        $addressParts = [htmlspecialchars($addr->line1)];
        if ($addr->line2 !== '') $addressParts[] = htmlspecialchars($addr->line2);
        $addressParts[] = htmlspecialchars($addr->city);
        if ($addr->province !== '') $addressParts[] = htmlspecialchars($addr->province);
        $addressParts[] = htmlspecialchars($addr->postalCode);
        $address = implode(', ', $addressParts);

        $firstName = htmlspecialchars($order->firstName);
        $lastName  = htmlspecialchars($order->lastName);
        $email     = htmlspecialchars($order->email);

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
            table { width:100%; border-collapse:collapse; }
            th { padding:8px 12px; background:#f9fafb; border-bottom:2px solid #e5e7eb; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#6b7280; }
            td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
            tr.subtotal td { padding:6px 12px; border-bottom:none; color:#6b7280; font-size:11px; }
            tr.total td { padding:10px 12px; border-top:2px solid #e5e7eb; font-weight:700; font-size:14px; color:#111827; }
            .center { text-align:center; }
            .right  { text-align:right; }
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
                  <strong>{$firstName} {$lastName}</strong><br>
                  {$email}<br>
                  {$address}
                </div>
              </div>
              <div>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">Ref: {$orderRef}<br>Date: {$invoiceDate}</div>
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
            <div class="footer">Thank you for your order. This document serves as your official invoice.</div>
          </div>
        </body>
        </html>
        HTML;
    }
}
