<?php

namespace App\Domain\Shop;

enum ReviewStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
