<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\PlaceOrderCommand;
use App\Application\Orders\DTOs\PlaceOrderResult;
use App\Domain\Core\SettingsRepositoryInterface;
use App\Domain\Orders\Order;
use App\Domain\Orders\OrderItem;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Shop\PaymentGateway;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;
use App\Domain\Shared\Address;
use App\Domain\Shared\Money;

final class PlaceOrderHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface   $stock,
        private readonly OrderRepositoryInterface   $orders,
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    public function handle(PlaceOrderCommand $cmd): PlaceOrderResult
    {
        $settings = $this->settings->getMany([
            'shop_vat_enabled', 'shop_vat_rate',
            'shop_shipping_rate', 'shop_free_shipping_from',
            'shop_currency',
        ]);

        $vatEnabled       = ($settings['shop_vat_enabled']     ?? '0') === '1';
        $vatRate          = (float)($settings['shop_vat_rate']         ?? 15);
        $shippingRate     = (float)($settings['shop_shipping_rate']    ?? 0);
        $freeShippingFrom = (float)($settings['shop_free_shipping_from'] ?? 0);
        $currency         = $settings['shop_currency'] ?? 'ZAR';

        // ── Validate and resolve items ────────────────────────────────
        $resolvedItems  = [];
        $subtotalCents  = 0;
        $stockToDecrement = []; // deferred until after order is saved

        foreach ($cmd->items as $cartItem) {
            $product = $this->products->findById($cartItem->productId);
            if ($product === null || !$product->active) {
                throw new \DomainException("Product #{$cartItem->productId} is no longer available.");
            }

            $effectivePrice = $product->price;
            $trackStock     = $product->trackStock;
            $stockQty       = $product->stockQty;
            $variantName    = null;
            $variant        = null;

            if ($cartItem->variantId !== null) {
                $variant = $this->products->findVariantById($cartItem->variantId, $cartItem->productId);
                if ($variant === null) {
                    throw new \DomainException("Variant #{$cartItem->variantId} not found.");
                }
                $effectivePrice += $variant->priceAdjustment;
                $variantName     = $variant->name;
                $trackStock      = $variant->trackStock;
                $stockQty        = $variant->stockQty;
            }

            if ($trackStock) {
                if ($stockQty <= 0) {
                    throw new \DomainException("{$product->name} is out of stock.");
                }
                if ($cartItem->qty > $stockQty) {
                    throw new \DomainException("Only {$stockQty} units of {$product->name} available.");
                }
            }

            $unitPriceCents = (int)round($effectivePrice * 100);
            $lineCents      = $unitPriceCents * $cartItem->qty;
            $subtotalCents += $lineCents;

            $resolvedItems[] = [
                'item'        => new OrderItem(
                    id:           0,
                    orderId:      0,
                    productId:    $cartItem->productId,
                    variantId:    $cartItem->variantId,
                    productName:  $product->name,
                    variantName:  $variantName,
                    qty:          $cartItem->qty,
                    unitPrice:    Money::fromCents($unitPriceCents, $currency),
                    lineTotal:    Money::fromCents($lineCents, $currency),
                    sku:          $product->slug,
                    productSlug:  $product->slug,
                    coverImage:   null,
                ),
                'trackStock'  => $trackStock,
                'qtyBefore'   => $stockQty,
            ];
        }

        // ── Compute totals ─────────────────────────────────────────────
        $subtotalRand  = $subtotalCents / 100;
        $isFreeShip    = $freeShippingFrom > 0 && $subtotalRand >= $freeShippingFrom;
        $shippingCents = $isFreeShip ? 0 : (int)round($shippingRate * 100);
        $vatCents      = $vatEnabled ? (int)round(($subtotalCents + $shippingCents) * $vatRate / 100) : 0;
        $totalCents    = $subtotalCents + $shippingCents + $vatCents;

        // ── Build and save order ───────────────────────────────────────
        $token = bin2hex(random_bytes(24));

        $order = new Order(
            id:               0,
            token:            $token,
            customerId:       $cmd->customerId,
            firstName:        trim($cmd->firstName),
            lastName:         trim($cmd->lastName),
            email:            strtolower(trim($cmd->email)),
            phone:            $cmd->phone ? trim($cmd->phone) : null,
            address:          Address::fromArray([
                'address_line1' => trim($cmd->addressLine1),
                'address_line2' => trim($cmd->addressLine2),
                'city'          => trim($cmd->city),
                'province'      => trim($cmd->province),
                'postal_code'   => trim($cmd->postalCode),
                'country'       => strtoupper(trim($cmd->country)),
            ]),
            subtotal:         Money::fromCents($subtotalCents, $currency),
            vat:              Money::fromCents($vatCents, $currency),
            shipping:         Money::fromCents($shippingCents, $currency),
            total:            Money::fromCents($totalCents, $currency),
            currency:         $currency,
            status:           OrderStatus::Pending,
            paymentGateway:   PaymentGateway::tryFrom($cmd->gateway),
            paymentReference: null,
            paidAt:           null,
            notes:            $cmd->notes,
            trackingCarrier:  null,
            trackingNumber:   null,
            createdAt:        new \DateTimeImmutable(),
            updatedAt:        null,
        );

        $order->items = array_column($resolvedItems, 'item');

        $savedOrder = $this->orders->save($order);

        // ── Decrement stock ────────────────────────────────────────────
        foreach ($resolvedItems as $resolved) {
            /** @var OrderItem $item */
            $item = $resolved['item'];
            if (!$resolved['trackStock']) continue;

            $qtyBefore = $resolved['qtyBefore'];
            $qtyAfter  = $qtyBefore - $item->qty;

            if ($item->variantId !== null) {
                $this->stock->decrementVariant($item->variantId, $item->qty);
                $this->stock->logAdjustment(
                    $item->productId, $item->variantId, -$item->qty,
                    'order', $savedOrder->id, '', $qtyBefore, $qtyAfter,
                );
            } else {
                $this->stock->decrementProduct($item->productId, $item->qty);
                $this->stock->logAdjustment(
                    $item->productId, null, -$item->qty,
                    'order', $savedOrder->id, '', $qtyBefore, $qtyAfter,
                );
            }
        }

        return new PlaceOrderResult(
            order:          $savedOrder,
            paymentGateway: $cmd->gateway,
        );
    }
}
