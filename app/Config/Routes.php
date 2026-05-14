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

// ----------------------------------------------------------------
// Public content routes (no authentication required)
// ----------------------------------------------------------------
$routes->get('content/settings',           'Content\Settings::index');
$routes->get('content/pages',              'Content\Pages::index');
$routes->get('content/page/(:segment)',    'Content\Pages::show/$1');

// Contact form submission
$routes->post('contact', 'Contact::send');

// ----------------------------------------------------------------
// Admin auth (login has NO auth filter — it IS the auth mechanism)
// ----------------------------------------------------------------
$routes->post('admin/login',  'Admin\Auth::login');
$routes->post('admin/logout', 'Admin\Auth::logout',  ['filter' => 'adminauth']);
$routes->get('admin/me',      'Admin\Auth::me',       ['filter' => 'adminauth']);

// ----------------------------------------------------------------
// Protected admin routes
// ----------------------------------------------------------------
$routes->put('admin/settings',                   'Admin\Settings::update',           ['filter' => 'adminauth']);

// ----------------------------------------------------------------
// Shop — public
// ----------------------------------------------------------------
$routes->get( 'shop/categories',          'Shop\Categories::index');
$routes->get( 'shop/products',            'Shop\Products::index');
$routes->get( 'shop/products/(:segment)', 'Shop\Products::show/$1');
$routes->post('shop/cart/validate',       'Shop\CartValidation::check');
$routes->post('shop/checkout',            'Shop\Checkout::place');
$routes->post('shop/payment/payfast/notify', 'Shop\PaymentNotify::payfast');
$routes->post('shop/payment/ozow/notify',    'Shop\PaymentNotify::ozow');
$routes->get( 'shop/orders/(:alphanum)',      'Shop\Orders::show/$1');

// Customer account
$routes->post('shop/account/register',   'Shop\CustomerAuth::register');
$routes->post('shop/account/login',      'Shop\CustomerAuth::login');
$routes->post('shop/account/logout',     'Shop\CustomerAuth::logout');
$routes->get( 'shop/account/me',         'Shop\CustomerAuth::me');
$routes->put( 'shop/account/me',         'Shop\CustomerAuth::update');
$routes->get( 'shop/account/orders',     'Shop\CustomerAuth::orders');

// ----------------------------------------------------------------
// Shop — admin (protected)
// ----------------------------------------------------------------
$routes->get(   'admin/shop/products',                          'Admin\Shop\Products::index',       ['filter' => 'adminauth']);
$routes->post(  'admin/shop/products',              'Admin\Shop\Products::create',      ['filter' => 'adminauth']);
$routes->put(   'admin/shop/products/(:num)',        'Admin\Shop\Products::update/$1',   ['filter' => 'adminauth']);
$routes->delete('admin/shop/products/(:num)',        'Admin\Shop\Products::delete/$1',   ['filter' => 'adminauth']);

$routes->post('admin/shop/products/(:num)/stock-adjustment', 'Admin\Shop\Stock::adjust/$1',  ['filter' => 'adminauth']);
$routes->get( 'admin/shop/products/(:num)/stock-history',   'Admin\Shop\Stock::history/$1', ['filter' => 'adminauth']);

$routes->post(  'admin/shop/products/(:num)/images',             'Admin\Shop\Images::store/$1',       ['filter' => 'adminauth']);
$routes->patch( 'admin/shop/products/(:num)/images/reorder',    'Admin\Shop\Images::reorder/$1',     ['filter' => 'adminauth']);
$routes->delete('admin/shop/products/(:num)/images/(:num)',      'Admin\Shop\Images::delete/$1/$2',   ['filter' => 'adminauth']);

$routes->post(  'admin/shop/categories',              'Admin\Shop\Categories::create',   ['filter' => 'adminauth']);
$routes->put(   'admin/shop/categories/(:num)',        'Admin\Shop\Categories::update/$1',['filter' => 'adminauth']);
$routes->delete('admin/shop/categories/(:num)',        'Admin\Shop\Categories::delete/$1',['filter' => 'adminauth']);
$routes->patch( 'admin/shop/categories/reorder',       'Admin\Shop\Categories::reorder',  ['filter' => 'adminauth']);

$routes->get(   'admin/shop/orders',                           'Admin\Shop\Orders::index',         ['filter' => 'adminauth']);
$routes->get(   'admin/shop/orders/(:num)',                    'Admin\Shop\Orders::show/$1',       ['filter' => 'adminauth']);
$routes->patch( 'admin/shop/orders/(:num)/status',             'Admin\Shop\Orders::updateStatus/$1', ['filter' => 'adminauth']);
$routes->post(  'admin/shop/orders/(:num)/refund',             'Admin\Shop\Orders::refund/$1',     ['filter' => 'adminauth']);
$routes->get(   'admin/shop/orders/(:num)/invoice',            'Admin\Shop\Orders::invoice/$1',    ['filter' => 'adminauth']);

$routes->post('admin/upload',                     'Admin\Upload::store',              ['filter' => 'adminauth']);
$routes->post('admin/upload-pdf',                 'Admin\UploadPdf::store',            ['filter' => 'adminauth']);
$routes->post('admin/pages',                      'Admin\Pages::create',              ['filter' => 'adminauth']);
$routes->put('admin/pages/(:segment)',            'Admin\Pages::update/$1',           ['filter' => 'adminauth']);
$routes->delete('admin/pages/(:segment)',         'Admin\Pages::delete/$1',           ['filter' => 'adminauth']);
