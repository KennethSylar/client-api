<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    public string $defaultGroup = 'default';

    // All values can be overridden via .env:
    //   database.default.hostname = ...
    //   database.default.database = ...
    //   database.default.username = ...
    //   database.default.password = ...
    public array $default = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'client_db',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => false,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_unicode_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3307,
        'numberNative' => true,
    ];

    /**
     * Test database — used when CI_ENVIRONMENT=testing.
     * Override via phpunit.xml.dist <env> or .env.testing:
     *   database.tests.hostname / .username / .password / .database
     */
    public array $tests = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'client_cms_test',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_unicode_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3307,
        'numberNative' => true,
    ];
}
