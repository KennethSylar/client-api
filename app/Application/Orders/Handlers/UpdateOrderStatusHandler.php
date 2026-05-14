<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\UpdateOrderStatusCommand;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;

final class UpdateOrderStatusHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function handle(UpdateOrderStatusCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId);
        if ($order === null) {
            throw new \DomainException('Order not found.');
        }

        $newStatus = OrderStatus::tryFrom($cmd->status);
        if ($newStatus === null) {
            throw new \InvalidArgumentException("Invalid status: {$cmd->status}");
        }

        if (!$order->status->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Cannot transition order from {$order->status->value} to {$cmd->status}."
            );
        }

        $extra = [];
        if ($newStatus === OrderStatus::Shipped) {
            if ($cmd->trackingCarrier !== null && $cmd->trackingCarrier !== '') {
                $extra['tracking_carrier'] = $cmd->trackingCarrier;
            }
            if ($cmd->trackingNumber !== null && $cmd->trackingNumber !== '') {
                $extra['tracking_number'] = $cmd->trackingNumber;
            }
        }

        $this->orders->updateStatus($cmd->orderId, $newStatus, $extra);

        $this->orders->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $cmd->orderId,
            fromStatus: $order->status->value,
            toStatus:   $newStatus->value,
            note:       $cmd->note ?: null,
            createdAt:  new \DateTimeImmutable(),
        ));
    }
}
