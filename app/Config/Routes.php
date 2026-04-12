<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ----------------------------------------------------------------
// JNV API Routes
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
$routes->get('content/newsletters',        'Content\Newsletters::index');
$routes->get('content/documents',          'Content\Documents::index');
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

$routes->post('admin/newsletters',               'Admin\Newsletters::create',        ['filter' => 'adminauth']);
$routes->put('admin/newsletters/(:num)',          'Admin\Newsletters::update/$1',     ['filter' => 'adminauth']);
$routes->delete('admin/newsletters/(:num)',       'Admin\Newsletters::delete/$1',     ['filter' => 'adminauth']);

$routes->post('admin/documents',                 'Admin\Documents::create',          ['filter' => 'adminauth']);
$routes->put('admin/documents/(:num)',            'Admin\Documents::update/$1',       ['filter' => 'adminauth']);
$routes->delete('admin/documents/(:num)',         'Admin\Documents::delete/$1',       ['filter' => 'adminauth']);

$routes->post('admin/upload',                     'Admin\Upload::store',              ['filter' => 'adminauth']);
$routes->post('admin/upload-pdf',                 'Admin\UploadPdf::store',            ['filter' => 'adminauth']);
$routes->post('admin/pages',                      'Admin\Pages::create',              ['filter' => 'adminauth']);
$routes->put('admin/pages/(:segment)',            'Admin\Pages::update/$1',           ['filter' => 'adminauth']);
$routes->delete('admin/pages/(:segment)',         'Admin\Pages::delete/$1',           ['filter' => 'adminauth']);
