<?php

namespace App\Infrastructure\Services;

use App\Application\Ports\LowStockNotifierInterface;
use App\Application\Ports\MailerInterface;
use App\Domain\Shop\Product;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Core\SettingsRepositoryInterface;

class LowStockNotifier implements LowStockNotifierInterface
{
    public function __construct(
        private readonly MailerInterface              $mailer,
        private readonly ProductRepositoryInterface   $products,
        private readonly SettingsRepositoryInterface  $settings,
    ) {}

    public function notifyIfNeeded(Product $product): void
    {
        if (!$product->needsLowStockAlert()) return;

        // Stamp before sending — prevents double-send on concurrent requests
        $this->products->stampLowStockAlert($product->id);

        $settings = $this->settings->getMany([
            'shop_low_stock_alert_email',
            'site_name',
            'contact_email',
        ]);

        $this->mailer->sendLowStockAlert([
            'id'                 => $product->id,
            'name'               => $product->name,
            'slug'               => $product->slug,
            'stock_qty'          => $product->stockQty,
            'low_stock_threshold'=> $product->lowStockThreshold,
        ], $settings);
    }
}
