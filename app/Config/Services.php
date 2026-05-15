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
        return new \App\Infrastructure\Persistence\MySqlSettingsRepository();
    }

    public static function pageRepository(bool $getShared = true): \App\Domain\Core\PageRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('pageRepository');
        return new \App\Infrastructure\Persistence\MySqlPageRepository();
    }

    public static function adminSessionRepository(bool $getShared = true): \App\Domain\Core\AdminSessionRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('adminSessionRepository');
        return new \App\Infrastructure\Persistence\MySqlAdminSessionRepository();
    }

    public static function categoryRepository(bool $getShared = true): \App\Domain\Shop\CategoryRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('categoryRepository');
        return new \App\Infrastructure\Persistence\MySqlCategoryRepository();
    }

    public static function productRepository(bool $getShared = true): \App\Domain\Shop\ProductRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('productRepository');
        return new \App\Infrastructure\Persistence\MySqlProductRepository();
    }

    public static function stockRepository(bool $getShared = true): \App\Domain\Shop\StockRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('stockRepository');
        return new \App\Infrastructure\Persistence\MySqlStockRepository();
    }

    public static function orderRepository(bool $getShared = true): \App\Domain\Orders\OrderRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('orderRepository');
        return new \App\Infrastructure\Persistence\MySqlOrderRepository();
    }

    public static function customerRepository(bool $getShared = true): \App\Domain\Orders\CustomerRepositoryInterface
    {
        if ($getShared) return static::getSharedInstance('customerRepository');
        return new \App\Infrastructure\Persistence\MySqlCustomerRepository();
    }

    // -------------------------------------------------------------------------
    // External service ports  (implementations added in M3)
    // -------------------------------------------------------------------------

    public static function mailer(bool $getShared = true): \App\Application\Ports\MailerInterface
    {
        if ($getShared) return static::getSharedInstance('mailer');
        return new \App\Infrastructure\Services\ResendMailer();
    }

    public static function lowStockNotifier(bool $getShared = true): \App\Application\Ports\LowStockNotifierInterface
    {
        if ($getShared) return static::getSharedInstance('lowStockNotifier');
        return new \App\Infrastructure\Services\LowStockNotifier(
            static::mailer(),
            static::productRepository(),
            static::settingsRepository(),
        );
    }

    public static function invoicePdf(bool $getShared = true): \App\Application\Ports\InvoicePdfInterface
    {
        if ($getShared) return static::getSharedInstance('invoicePdf');
        return new \App\Infrastructure\Services\DompdfInvoicePdf();
    }

    public static function imageUploader(bool $getShared = true): \App\Application\Ports\ImageUploaderInterface
    {
        if ($getShared) return static::getSharedInstance('imageUploader');
        return new \App\Infrastructure\Services\CloudinaryUploader();
    }

    public static function payfastGateway(bool $getShared = true): \App\Application\Ports\PaymentGatewayInterface
    {
        if ($getShared) return static::getSharedInstance('payfastGateway');
        return new \App\Infrastructure\Gateways\PayFastGateway();
    }

    public static function ozowGateway(bool $getShared = true): \App\Application\Ports\PaymentGatewayInterface
    {
        if ($getShared) return static::getSharedInstance('ozowGateway');
        return new \App\Infrastructure\Gateways\OzowGateway();
    }

    // -------------------------------------------------------------------------
    // Command Handlers  (M4)
    // -------------------------------------------------------------------------

    public static function adminLoginHandler(bool $getShared = true): \App\Application\Core\Handlers\AdminLoginHandler
    {
        if ($getShared) return static::getSharedInstance('adminLoginHandler');
        return new \App\Application\Core\Handlers\AdminLoginHandler(
            static::settingsRepository(),
            static::adminSessionRepository(),
        );
    }

    public static function updateSettingsHandler(bool $getShared = true): \App\Application\Core\Handlers\UpdateSettingsHandler
    {
        if ($getShared) return static::getSharedInstance('updateSettingsHandler');
        return new \App\Application\Core\Handlers\UpdateSettingsHandler(static::settingsRepository());
    }

    public static function savePageHandler(bool $getShared = true): \App\Application\Core\Handlers\SavePageHandler
    {
        if ($getShared) return static::getSharedInstance('savePageHandler');
        return new \App\Application\Core\Handlers\SavePageHandler(static::pageRepository());
    }

    public static function deletePageHandler(bool $getShared = true): \App\Application\Core\Handlers\DeletePageHandler
    {
        if ($getShared) return static::getSharedInstance('deletePageHandler');
        return new \App\Application\Core\Handlers\DeletePageHandler(static::pageRepository());
    }

    public static function createCategoryHandler(bool $getShared = true): \App\Application\Shop\Handlers\CreateCategoryHandler
    {
        if ($getShared) return static::getSharedInstance('createCategoryHandler');
        return new \App\Application\Shop\Handlers\CreateCategoryHandler(static::categoryRepository());
    }

    public static function updateCategoryHandler(bool $getShared = true): \App\Application\Shop\Handlers\UpdateCategoryHandler
    {
        if ($getShared) return static::getSharedInstance('updateCategoryHandler');
        return new \App\Application\Shop\Handlers\UpdateCategoryHandler(static::categoryRepository());
    }

    public static function deleteCategoryHandler(bool $getShared = true): \App\Application\Shop\Handlers\DeleteCategoryHandler
    {
        if ($getShared) return static::getSharedInstance('deleteCategoryHandler');
        return new \App\Application\Shop\Handlers\DeleteCategoryHandler(static::categoryRepository());
    }

    public static function reorderCategoriesHandler(bool $getShared = true): \App\Application\Shop\Handlers\ReorderCategoriesHandler
    {
        if ($getShared) return static::getSharedInstance('reorderCategoriesHandler');
        return new \App\Application\Shop\Handlers\ReorderCategoriesHandler(static::categoryRepository());
    }

    public static function createProductHandler(bool $getShared = true): \App\Application\Shop\Handlers\CreateProductHandler
    {
        if ($getShared) return static::getSharedInstance('createProductHandler');
        return new \App\Application\Shop\Handlers\CreateProductHandler(static::productRepository());
    }

    public static function updateProductHandler(bool $getShared = true): \App\Application\Shop\Handlers\UpdateProductHandler
    {
        if ($getShared) return static::getSharedInstance('updateProductHandler');
        return new \App\Application\Shop\Handlers\UpdateProductHandler(static::productRepository());
    }

    public static function deleteProductHandler(bool $getShared = true): \App\Application\Shop\Handlers\DeleteProductHandler
    {
        if ($getShared) return static::getSharedInstance('deleteProductHandler');
        return new \App\Application\Shop\Handlers\DeleteProductHandler(static::productRepository());
    }

    public static function addProductImageHandler(bool $getShared = true): \App\Application\Shop\Handlers\AddProductImageHandler
    {
        if ($getShared) return static::getSharedInstance('addProductImageHandler');
        return new \App\Application\Shop\Handlers\AddProductImageHandler(static::productRepository());
    }

    public static function deleteProductImageHandler(bool $getShared = true): \App\Application\Shop\Handlers\DeleteProductImageHandler
    {
        if ($getShared) return static::getSharedInstance('deleteProductImageHandler');
        return new \App\Application\Shop\Handlers\DeleteProductImageHandler(static::productRepository());
    }

    public static function reorderProductImagesHandler(bool $getShared = true): \App\Application\Shop\Handlers\ReorderProductImagesHandler
    {
        if ($getShared) return static::getSharedInstance('reorderProductImagesHandler');
        return new \App\Application\Shop\Handlers\ReorderProductImagesHandler(static::productRepository());
    }

    public static function adjustStockHandler(bool $getShared = true): \App\Application\Shop\Handlers\AdjustStockHandler
    {
        if ($getShared) return static::getSharedInstance('adjustStockHandler');
        return new \App\Application\Shop\Handlers\AdjustStockHandler(
            static::productRepository(),
            static::stockRepository(),
            static::lowStockNotifier(),
        );
    }

    public static function updateOrderStatusHandler(bool $getShared = true): \App\Application\Orders\Handlers\UpdateOrderStatusHandler
    {
        if ($getShared) return static::getSharedInstance('updateOrderStatusHandler');
        return new \App\Application\Orders\Handlers\UpdateOrderStatusHandler(static::orderRepository());
    }

    public static function refundOrderHandler(bool $getShared = true): \App\Application\Orders\Handlers\RefundOrderHandler
    {
        if ($getShared) return static::getSharedInstance('refundOrderHandler');
        return new \App\Application\Orders\Handlers\RefundOrderHandler(
            static::orderRepository(),
            static::productRepository(),
            static::stockRepository(),
        );
    }

    public static function recordPaymentHandler(bool $getShared = true): \App\Application\Orders\Handlers\RecordPaymentHandler
    {
        if ($getShared) return static::getSharedInstance('recordPaymentHandler');
        return new \App\Application\Orders\Handlers\RecordPaymentHandler(
            static::orderRepository(),
            static::settingsRepository(),
            static::invoicePdf(),
            static::mailer(),
        );
    }

    public static function cancelOrderHandler(bool $getShared = true): \App\Application\Orders\Handlers\CancelOrderHandler
    {
        if ($getShared) return static::getSharedInstance('cancelOrderHandler');
        return new \App\Application\Orders\Handlers\CancelOrderHandler(
            static::orderRepository(),
            static::productRepository(),
            static::stockRepository(),
        );
    }

    public static function placeOrderHandler(bool $getShared = true): \App\Application\Orders\Handlers\PlaceOrderHandler
    {
        if ($getShared) return static::getSharedInstance('placeOrderHandler');
        return new \App\Application\Orders\Handlers\PlaceOrderHandler(
            static::productRepository(),
            static::stockRepository(),
            static::orderRepository(),
            static::settingsRepository(),
        );
    }

    public static function registerCustomerHandler(bool $getShared = true): \App\Application\Orders\Handlers\RegisterCustomerHandler
    {
        if ($getShared) return static::getSharedInstance('registerCustomerHandler');
        return new \App\Application\Orders\Handlers\RegisterCustomerHandler(static::customerRepository());
    }

    public static function loginCustomerHandler(bool $getShared = true): \App\Application\Orders\Handlers\LoginCustomerHandler
    {
        if ($getShared) return static::getSharedInstance('loginCustomerHandler');
        return new \App\Application\Orders\Handlers\LoginCustomerHandler(static::customerRepository());
    }

    public static function logoutCustomerHandler(bool $getShared = true): \App\Application\Orders\Handlers\LogoutCustomerHandler
    {
        if ($getShared) return static::getSharedInstance('logoutCustomerHandler');
        return new \App\Application\Orders\Handlers\LogoutCustomerHandler(static::customerRepository());
    }

    public static function updateCustomerHandler(bool $getShared = true): \App\Application\Orders\Handlers\UpdateCustomerHandler
    {
        if ($getShared) return static::getSharedInstance('updateCustomerHandler');
        return new \App\Application\Orders\Handlers\UpdateCustomerHandler(static::customerRepository());
    }

    public static function uploadImageHandler(bool $getShared = true): \App\Application\Core\Handlers\UploadImageHandler
    {
        if ($getShared) return static::getSharedInstance('uploadImageHandler');
        return new \App\Application\Core\Handlers\UploadImageHandler(static::imageUploader());
    }

    public static function uploadPdfHandler(bool $getShared = true): \App\Application\Core\Handlers\UploadPdfHandler
    {
        if ($getShared) return static::getSharedInstance('uploadPdfHandler');
        return new \App\Application\Core\Handlers\UploadPdfHandler(static::imageUploader());
    }

    public static function sendContactEnquiryHandler(bool $getShared = true): \App\Application\Core\Handlers\SendContactEnquiryHandler
    {
        if ($getShared) return static::getSharedInstance('sendContactEnquiryHandler');
        return new \App\Application\Core\Handlers\SendContactEnquiryHandler(
            static::mailer(),
            static::settingsRepository(),
        );
    }

    // -------------------------------------------------------------------------
    // Query Handlers  (M5)
    // -------------------------------------------------------------------------

    public static function getSettingsHandler(bool $getShared = true): \App\Application\Core\Handlers\GetSettingsHandler
    {
        if ($getShared) return static::getSharedInstance('getSettingsHandler');
        return new \App\Application\Core\Handlers\GetSettingsHandler(static::settingsRepository());
    }

    public static function listPagesHandler(bool $getShared = true): \App\Application\Core\Handlers\ListPagesHandler
    {
        if ($getShared) return static::getSharedInstance('listPagesHandler');
        return new \App\Application\Core\Handlers\ListPagesHandler(static::pageRepository());
    }

    public static function getPageHandler(bool $getShared = true): \App\Application\Core\Handlers\GetPageHandler
    {
        if ($getShared) return static::getSharedInstance('getPageHandler');
        return new \App\Application\Core\Handlers\GetPageHandler(static::pageRepository());
    }

    public static function listCategoriesHandler(bool $getShared = true): \App\Application\Shop\Handlers\ListCategoriesHandler
    {
        if ($getShared) return static::getSharedInstance('listCategoriesHandler');
        return new \App\Application\Shop\Handlers\ListCategoriesHandler(static::categoryRepository());
    }

    public static function listProductsHandler(bool $getShared = true): \App\Application\Shop\Handlers\ListProductsHandler
    {
        if ($getShared) return static::getSharedInstance('listProductsHandler');
        return new \App\Application\Shop\Handlers\ListProductsHandler(static::productRepository());
    }

    public static function getProductHandler(bool $getShared = true): \App\Application\Shop\Handlers\GetProductHandler
    {
        if ($getShared) return static::getSharedInstance('getProductHandler');
        return new \App\Application\Shop\Handlers\GetProductHandler(static::productRepository());
    }

    public static function listOrdersHandler(bool $getShared = true): \App\Application\Orders\Handlers\ListOrdersHandler
    {
        if ($getShared) return static::getSharedInstance('listOrdersHandler');
        return new \App\Application\Orders\Handlers\ListOrdersHandler(static::orderRepository());
    }

    public static function getOrderHandler(bool $getShared = true): \App\Application\Orders\Handlers\GetOrderHandler
    {
        if ($getShared) return static::getSharedInstance('getOrderHandler');
        return new \App\Application\Orders\Handlers\GetOrderHandler(static::orderRepository());
    }

    public static function getStockHistoryHandler(bool $getShared = true): \App\Application\Shop\Handlers\GetStockHistoryHandler
    {
        if ($getShared) return static::getSharedInstance('getStockHistoryHandler');
        return new \App\Application\Shop\Handlers\GetStockHistoryHandler(
            static::productRepository(),
            static::stockRepository(),
        );
    }

    public static function getCustomerOrdersHandler(bool $getShared = true): \App\Application\Orders\Handlers\GetCustomerOrdersHandler
    {
        if ($getShared) return static::getSharedInstance('getCustomerOrdersHandler');
        return new \App\Application\Orders\Handlers\GetCustomerOrdersHandler(static::orderRepository());
    }

    public static function getOrderInvoiceHandler(bool $getShared = true): \App\Application\Orders\Handlers\GetOrderInvoiceHandler
    {
        if ($getShared) return static::getSharedInstance('getOrderInvoiceHandler');
        return new \App\Application\Orders\Handlers\GetOrderInvoiceHandler(
            static::orderRepository(),
            static::settingsRepository(),
            static::invoicePdf(),
        );
    }
}
