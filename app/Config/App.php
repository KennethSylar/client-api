<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    // Base URL — override via .env: app.baseURL
    public string $baseURL = 'http://localhost:8080/';

    /** @var list<string> */
    public array $allowedHostnames = [];

    // Empty = mod_rewrite removes index.php from URLs
    public string $indexPage = '';

    public string $uriProtocol = 'REQUEST_URI';

    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    public string $defaultLocale = 'en';

    public bool $negotiateLocale = false;

    /** @var list<string> */
    public array $supportedLocales = ['en'];

    public string $appTimezone = 'Africa/Johannesburg';

    public string $charset = 'UTF-8';

    public bool $forceGlobalSecureRequests = false;

    /** @var array<string, string> */
    public array $proxyIPs = [];

    public bool $CSPEnabled = false;

    // ---- JNV custom ----------------------------------------

    // Allowed CORS origin(s) — override via .env: app.allowedOrigins
    // Use * for all, or comma-separated list: 'https://jnv.co.za,https://www.jnv.co.za'
    public string $allowedOrigins = '*';
}
