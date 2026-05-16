<?php

namespace Config;

use App\Infrastructure\Http\Filters\AdminAuth;
use App\Infrastructure\Http\Filters\AdminOnlyAuth;
use App\Infrastructure\Http\Filters\CustomerAuth;
use App\Infrastructure\Http\Filters\Cors;
use App\Infrastructure\Http\Filters\SecurityHeaders;
use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    // Aliases make filters easy to reference by short name
    public array $aliases = [
        'csrf'           => \CodeIgniter\Filters\CSRF::class,
        'toolbar'        => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'       => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars'   => \CodeIgniter\Filters\InvalidChars::class,
        'adminauth'      => AdminAuth::class,
        'adminonlyauth'  => AdminOnlyAuth::class,
        'customerauth'   => CustomerAuth::class,
        'cors'           => Cors::class,
        'securityheaders'=> SecurityHeaders::class,
    ];

    // Always-on filters
    public array $globals = [
        'before' => [
            'cors',            // handle CORS on every request
        ],
        'after' => [
            'securityheaders', // harden every response
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}
