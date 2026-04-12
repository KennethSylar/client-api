<?php

namespace Config;

use App\Filters\AdminAuth;
use App\Filters\Cors;
use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    // Aliases make filters easy to reference by short name
    public array $aliases = [
        'csrf'      => \CodeIgniter\Filters\CSRF::class,
        'toolbar'   => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'  => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars' => \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'adminauth' => AdminAuth::class,
        'cors'      => Cors::class,
    ];

    // Always-on filters
    public array $globals = [
        'before' => [
            'cors',       // handle CORS on every request
        ],
        'after' => [],
    ];

    public array $methods = [];

    public array $filters = [];
}
