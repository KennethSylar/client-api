<?php

namespace App\Infrastructure\Http\Filters;

use App\Domain\Orders\Customer;

/**
 * Request-scoped store for the authenticated customer.
 * Set by CustomerAuth filter; read by shop account controllers.
 * Static properties are reset per PHP request lifecycle.
 */
class CustomerAuthContext
{
    private static ?Customer $customer = null;

    public static function set(Customer $customer): void
    {
        self::$customer = $customer;
    }

    public static function get(): ?Customer
    {
        return self::$customer;
    }
}
