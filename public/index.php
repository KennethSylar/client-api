<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 * ---------------------------------------------------------------
 * JNV API — CodeIgniter 4 Entry Point
 * ---------------------------------------------------------------
 *
 * DEPLOYMENT NOTE — Shared hosting layout:
 *
 *   /home/user/
 *   ├── public_html/
 *   │   ├── (Nuxt static files)
 *   │   └── api/              ← contents of this `public/` folder
 *   │       ├── index.php     ← this file
 *   │       └── .htaccess
 *   └── jnv-api/              ← CI4 project root (outside web root)
 *       ├── app/
 *       ├── vendor/           ← installed by `composer install`
 *       └── writable/
 *
 *   If you move the public/ contents, update Paths.php accordingly.
 */

// Minimum PHP version check
$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf('PHP %s or higher is required. Current: %s', $minPhpVersion, PHP_VERSION);
    exit(1);
}

// Path to this file's directory
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// Load Composer's autoloader first — makes all vendor classes available
require FCPATH . '../vendor/autoload.php';

// Load the Paths config (one level up: public/ → jnv-api/app/Config/Paths.php)
require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

// Boot the framework
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
