<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ----------------------------------------------------------------
// Client API Routes
// ----------------------------------------------------------------

// Disable auto-routing — everything is explicit
$routes->setAutoRoute(false);
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);

// Catch-all OPTIONS handler for CORS preflight (auto-routing is disabled)
$routes->options('(:any)', static function () {
    return service('response')->setStatusCode(200)->setBody('');
});

// ----------------------------------------------------------------
// Public content routes (no authentication required)
// ----------------------------------------------------------------
$routes->get('content/settings',           '\App\Infrastructure\Http\Controllers\Content\Settings::index');
$routes->get('content/pages',              '\App\Infrastructure\Http\Controllers\Content\Pages::index');
$routes->get('content/page/(:segment)',    '\App\Infrastructure\Http\Controllers\Content\Pages::show/$1');

// Contact form submission
$routes->post('contact', '\App\Infrastructure\Http\Controllers\Contact::send');

// ----------------------------------------------------------------
// Admin auth (login has NO auth filter — it IS the auth mechanism)
// ----------------------------------------------------------------
$routes->post('admin/login',  '\App\Infrastructure\Http\Controllers\Admin\Auth::login');
$routes->post('admin/logout', '\App\Infrastructure\Http\Controllers\Admin\Auth::logout',  ['filter' => 'adminauth']);
$routes->get('admin/me',      '\App\Infrastructure\Http\Controllers\Admin\Auth::me',       ['filter' => 'adminauth']);

// ----------------------------------------------------------------
// Protected admin routes
// ----------------------------------------------------------------
$routes->put('admin/settings', '\App\Infrastructure\Http\Controllers\Admin\Settings::update', ['filter' => 'adminauth']);

// ----------------------------------------------------------------
// Shop — public
// ----------------------------------------------------------------
$routes->get( 'shop/categories',          '\App\Infrastructure\Http\Controllers\Shop\Categories::index');
$routes->get( 'shop/products',            '\App\Infrastructure\Http\Controllers\Shop\Products::index');
$routes->get( 'shop/products/(:segment)', '\App\Infrastructure\Http\Controllers\Shop\Products::show/$1');
$routes->post('shop/cart/validate',       '\App\Infrastructure\Http\Controllers\Shop\CartValidation::check');
$routes->post('shop/checkout',            '\App\Infrastructure\Http\Controllers\Shop\Checkout::place');
$routes->post('shop/payment/payfast/notify', '\App\Infrastructure\Http\Controllers\Shop\PaymentNotify::payfast');
$routes->post('shop/payment/ozow/notify',    '\App\Infrastructure\Http\Controllers\Shop\PaymentNotify::ozow');
$routes->get( 'shop/orders/(:alphanum)',      '\App\Infrastructure\Http\Controllers\Shop\Orders::show/$1');

// Customer account
$routes->post('shop/account/register',   '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::register');
$routes->post('shop/account/login',      '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::login');
$routes->post('shop/account/logout',     '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::logout');
$routes->get( 'shop/account/me',         '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::me');
$routes->put( 'shop/account/me',         '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::update');
$routes->get( 'shop/account/orders',     '\App\Infrastructure\Http\Controllers\Shop\CustomerAuth::orders');

// ----------------------------------------------------------------
// Shop — admin (protected)
// ----------------------------------------------------------------
$routes->get(   'admin/shop/products',               '\App\Infrastructure\Http\Controllers\Admin\Shop\Products::index',       ['filter' => 'adminauth']);
$routes->post(  'admin/shop/products',               '\App\Infrastructure\Http\Controllers\Admin\Shop\Products::create',      ['filter' => 'adminauth']);
$routes->put(   'admin/shop/products/(:num)',         '\App\Infrastructure\Http\Controllers\Admin\Shop\Products::update/$1',   ['filter' => 'adminauth']);
$routes->delete('admin/shop/products/(:num)',         '\App\Infrastructure\Http\Controllers\Admin\Shop\Products::delete/$1',   ['filter' => 'adminauth']);

$routes->post('admin/shop/products/(:num)/stock-adjustment', '\App\Infrastructure\Http\Controllers\Admin\Shop\Stock::adjust/$1',  ['filter' => 'adminauth']);
$routes->get( 'admin/shop/products/(:num)/stock-history',   '\App\Infrastructure\Http\Controllers\Admin\Shop\Stock::history/$1', ['filter' => 'adminauth']);

$routes->post(  'admin/shop/products/(:num)/images',          '\App\Infrastructure\Http\Controllers\Admin\Shop\Images::store/$1',       ['filter' => 'adminauth']);
$routes->patch( 'admin/shop/products/(:num)/images/reorder',  '\App\Infrastructure\Http\Controllers\Admin\Shop\Images::reorder/$1',     ['filter' => 'adminauth']);
$routes->delete('admin/shop/products/(:num)/images/(:num)',   '\App\Infrastructure\Http\Controllers\Admin\Shop\Images::delete/$1/$2',   ['filter' => 'adminauth']);

$routes->post(  'admin/shop/categories',              '\App\Infrastructure\Http\Controllers\Admin\Shop\Categories::create',   ['filter' => 'adminauth']);
$routes->put(   'admin/shop/categories/(:num)',        '\App\Infrastructure\Http\Controllers\Admin\Shop\Categories::update/$1',['filter' => 'adminauth']);
$routes->delete('admin/shop/categories/(:num)',        '\App\Infrastructure\Http\Controllers\Admin\Shop\Categories::delete/$1',['filter' => 'adminauth']);
$routes->patch( 'admin/shop/categories/reorder',       '\App\Infrastructure\Http\Controllers\Admin\Shop\Categories::reorder',  ['filter' => 'adminauth']);

$routes->get(   'admin/shop/orders',                           '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::index',         ['filter' => 'adminauth']);
$routes->get(   'admin/shop/orders/(:num)',                    '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::show/$1',       ['filter' => 'adminauth']);
$routes->patch( 'admin/shop/orders/(:num)/status',             '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::updateStatus/$1', ['filter' => 'adminauth']);
$routes->post(  'admin/shop/orders/(:num)/refund',             '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::refund/$1',     ['filter' => 'adminauth']);
$routes->get(   'admin/shop/orders/(:num)/invoice',            '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::invoice/$1',    ['filter' => 'adminauth']);

// Product reviews — public
$routes->get( 'shop/products/(:num)/reviews', '\App\Infrastructure\Http\Controllers\Shop\Reviews::index/$1');
$routes->post('shop/products/(:num)/reviews', '\App\Infrastructure\Http\Controllers\Shop\Reviews::store/$1');

// Product reviews — admin
$routes->get(   'admin/shop/reviews',         '\App\Infrastructure\Http\Controllers\Admin\Shop\Reviews::index',        ['filter' => 'adminauth']);
$routes->patch( 'admin/shop/reviews/(:num)',  '\App\Infrastructure\Http\Controllers\Admin\Shop\Reviews::moderate/$1',  ['filter' => 'adminauth']);
$routes->delete('admin/shop/reviews/(:num)',  '\App\Infrastructure\Http\Controllers\Admin\Shop\Reviews::destroy/$1',   ['filter' => 'adminauth']);

// Partial refund
$routes->post('admin/shop/orders/(:num)/partial-refund', '\App\Infrastructure\Http\Controllers\Admin\Shop\Orders::partialRefund/$1', ['filter' => 'adminauth']);

$routes->post('admin/upload',         '\App\Infrastructure\Http\Controllers\Admin\Upload::store',    ['filter' => 'adminauth']);
$routes->post('admin/upload-pdf',     '\App\Infrastructure\Http\Controllers\Admin\UploadPdf::store', ['filter' => 'adminauth']);
$routes->post('admin/pages',          '\App\Infrastructure\Http\Controllers\Admin\Pages::create',    ['filter' => 'adminauth']);
$routes->put('admin/pages/(:segment)','\App\Infrastructure\Http\Controllers\Admin\Pages::update/$1', ['filter' => 'adminauth']);
$routes->delete('admin/pages/(:segment)', '\App\Infrastructure\Http\Controllers\Admin\Pages::delete/$1', ['filter' => 'adminauth']);
