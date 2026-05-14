<?php

namespace App\Application\Ports;

use App\Domain\Orders\Order;

interface InvoicePdfInterface
{
    /**
     * @param array[]              $items    Raw order item rows
     * @param array<string,string> $settings Site settings
     * @return string Raw PDF bytes
     */
    public function generate(Order $order, array $items, array $settings): string;
}
