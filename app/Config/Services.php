<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Application service locator.
 *
 * Each method returns a shared singleton by default.
 * Implementations are bound here once the relevant Infrastructure class exists.
 * During migration, stubs throw a RuntimeException so any premature call is
 * caught immediately rather than producing a silent wrong result.
 *
 * Binding lifecycle:
 *   M1 → interfaces declared (no binding yet)
 *   M2 → Persistence implementations bound (repositories)
 *   M3 → Service / Gateway implementations bound
 *   M4+ → Handler factories added story-by-story
 */
class Services extends BaseService
{
    // -------------------------------------------------------------------------
    // Repositories  (implementations added in M2)
    // -------------------------------------------------------------------------

    public static function settingsRepository(bool $getShared = true): \App\Domain\Core\SettingsRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('settingsRepository');
        throw new \RuntimeException('settingsRepository not yet bound — complete M2 Story 2.2 first.');
    }

    public static function pageRepository(bool $getShared = true): \App\Domain\Core\PageRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('pageRepository');
        throw new \RuntimeException('pageRepository not yet bound — complete M2 Story 2.3 first.');
    }

    public static function adminSessionRepository(bool $getShared = true): \App\Domain\Core\AdminSessionRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('adminSessionRepository');
        throw new \RuntimeException('adminSessionRepository not yet bound — complete M2 Story 2.4 first.');
    }

    public static function categoryRepository(bool $getShared = true): \App\Domain\Shop\CategoryRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('categoryRepository');
        throw new \RuntimeException('categoryRepository not yet bound — complete M2 Story 2.5 first.');
    }

    public static function productRepository(bool $getShared = true): \App\Domain\Shop\ProductRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('productRepository');
        throw new \RuntimeException('productRepository not yet bound — complete M2 Story 2.6 first.');
    }

    public static function stockRepository(bool $getShared = true): \App\Domain\Shop\StockRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('stockRepository');
        throw new \RuntimeException('stockRepository not yet bound — complete M2 Story 2.7 first.');
    }

    public static function orderRepository(bool $getShared = true): \App\Domain\Orders\OrderRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('orderRepository');
        throw new \RuntimeException('orderRepository not yet bound — complete M2 Story 2.8 first.');
    }

    public static function customerRepository(bool $getShared = true): \App\Domain\Orders\CustomerRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('customerRepository');
        throw new \RuntimeException('customerRepository not yet bound — complete M2 Story 2.9 first.');
    }

    // -------------------------------------------------------------------------
    // External service ports  (implementations added in M3)
    // -------------------------------------------------------------------------

    public static function mailer(bool $getShared = true): \App\Application\Ports\MailerInterface
    {
        if ($getShared) return static::getSharedInstance('mailer');
        throw new \RuntimeException('mailer not yet bound — complete M3 Story 3.1 first.');
    }

    public static function lowStockNotifier(bool $getShared = true): \App\Application\Ports\LowStockNotifierInterface
    {
        if ($getShared) return static::getSharedInstance('lowStockNotifier');
        throw new \RuntimeException('lowStockNotifier not yet bound — complete M3 Story 3.2 first.');
    }

    public static function invoicePdf(bool $getShared = true): \App\Application\Ports\InvoicePdfInterface
    {
        if ($getShared) return static::getSharedInstance('invoicePdf');
        throw new \RuntimeException('invoicePdf not yet bound — complete M3 Story 3.3 first.');
    }

    public static function imageUploader(bool $getShared = true): \App\Application\Ports\ImageUploaderInterface
    {
        if ($getShared) return static::getSharedInstance('imageUploader');
        throw new \RuntimeException('imageUploader not yet bound — complete M3 Story 3.4 first.');
    }

    public static function payfastGateway(bool $getShared = true): \App\Application\Ports\PaymentGatewayInterface
    {
        if ($getShared) return static::getSharedInstance('payfastGateway');
        throw new \RuntimeException('payfastGateway not yet bound — complete M3 Story 3.5 first.');
    }

    public static function ozowGateway(bool $getShared = true): \App\Application\Ports\PaymentGatewayInterface
    {
        if ($getShared) return static::getSharedInstance('ozowGateway');
        throw new \RuntimeException('ozowGateway not yet bound — complete M3 Story 3.6 first.');
    }
}
