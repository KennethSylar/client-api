<?php

namespace App\Domain\Shop;

enum PaymentGateway: string
{
    case PayFast = 'payfast';
    case Ozow    = 'ozow';
    case None    = 'none';
}
