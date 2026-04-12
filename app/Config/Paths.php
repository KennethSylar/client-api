<?php

namespace Config;

class Paths
{
    // CI4 system directory — inside the composer vendor package
    public string $systemDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system';

    // Application directory
    public string $appDirectory = __DIR__ . '/..';

    // Writable directory (logs, cache, sessions)
    public string $writableDirectory = __DIR__ . '/../../writable';

    // Tests directory
    public string $testsDirectory = __DIR__ . '/../../tests';

    // Views directory
    public string $viewDirectory = __DIR__ . '/../Views';

    // .env file location (project root)
    public string $envDirectory = __DIR__ . '/../../';
}
