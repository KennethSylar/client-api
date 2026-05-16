# Backend Architect

You are working in `client-api/` — a CodeIgniter 4 REST API (PHP 8.1+, MySQL, no CI4 Models).

## Working Directory Rule
All file paths are relative to `client-api/`. Never touch `client-site/` or `client-template/` unless the task explicitly requires cross-repo changes.

## Before You Start
1. Read all files you will modify — never edit blindly
2. Check `app/Config/Routes.php` before adding routes — keep explicit, no auto-routing
3. Check `app/Infrastructure/Http/Controllers/` for an existing controller before creating one

## Architecture
```
app/
  Config/
    Routes.php          ← ALL routes defined here explicitly
    Filters.php         ← Register filters (AdminAuth, Cors, SecurityHeaders)
  Infrastructure/
    Http/
      Controllers/
        BaseController.php   ← ok(), error(), json(), jsonBody() helpers
        Admin/               ← Admin-only controllers
        Shop/                ← Shop/storefront controllers
        Content/             ← Public content controllers
      Filters/
        AdminAuth.php        ← Cookie session auth filter
        Cors.php             ← CORS filter
        SecurityHeaders.php  ← Security headers filter
    Gateways/            ← PayFast, Ozow, Resend, Cloudinary integrations
    Repositories/        ← Repository interfaces + MySQL implementations
  Application/
    Core/
      Handlers/          ← Command handlers (one handler per use case)
      Commands/          ← Value objects passed to handlers
```

## Adding a New Feature — Checklist
1. **Route** — add explicit route to `Routes.php` with correct filter
2. **Controller method** — return `$this->ok($data)` or `$this->error('message', 400)`
3. **Handler** — one handler per use case (`DoThingHandler::handle(DoThingCommand $cmd)`)
4. **Repository interface** — define in `Repositories/` if DB access needed
5. **MySQL implementation** — implement the interface, inject via constructor
6. **Rate limit** — call `$this->rateLimited($key, $max, $window)` on public/auth endpoints

## BaseController Helpers
```php
return $this->ok($data);                      // 200 {"ok":true, ...data}
return $this->error('Not found', 404);        // 404 {"error":"Not found"}
return $this->jsonBody();                     // decoded JSON request body (array)
$token = $this->bearerToken();               // extract Bearer token from Authorization header
```

## Rate Limiting
```php
// In any controller method:
$ip = $this->request->getIPAddress();
if ($this->rateLimited("login:{$ip}", 10, 900)) {
    return $this->tooManyRequests('Too many attempts. Try again in 15 minutes.');
}
```
`rateLimited(key, maxHits, windowSeconds)` — uses CI4 cache, `rl_` prefix, md5 hash of key.

## Auth Filters
```php
// Routes.php
$routes->get('/admin/something',  [Something::class, 'index'],  ['filter' => 'adminauth']);
$routes->get('/admin/users',      [Users::class, 'index'],      ['filter' => 'adminonlyauth']);
$routes->get('/shop/protected',   [Shop::class, 'index'],       ['filter' => 'customerauth']);
```
- `adminauth` — any admin role (admin or shop_admin)
- `adminonlyauth` — admin role only
- `customerauth` — authenticated customer (cookie or Bearer header)

## Repository Pattern
```php
// Interface (in Repositories/)
interface PageRepositoryInterface {
    public function findBySlug(string $slug): ?array;
    public function list(): array;
    public function create(array $data): int;
    public function update(int $id, array $data): void;
    public function delete(int $id): void;
}

// MySQL implementation
class MySqlPageRepository implements PageRepositoryInterface {
    public function findBySlug(string $slug): ?array {
        return $this->db->table('pages')->where('slug', $slug)->get()->getRowArray();
    }
}
```
Always inject the interface, never the concrete class, in handlers and controllers.

## Security Rules
- **Never** use `$_GET`, `$_POST`, `$_REQUEST` directly — use `$this->request->getVar()`
- **Sensitive settings** (API keys, payment credentials): mask as `••••••••` in GET responses, never log
- **Payment order not found**: return `400 'Invalid notification'` not `404` (prevents enumeration)
- **Auth errors**: same message for wrong username vs wrong password ("Invalid credentials")
- **Rate limit** all public authentication and registration endpoints

## Adding a DB Migration
```php
// app/Database/Migrations/YYYY-MM-DD-HHmmss_DescriptionHere.php
class DescriptionHere extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('table_name');
    }
    public function down(): void {
        $this->forge->dropTable('table_name');
    }
}
```
Run: `php spark migrate`

## Settings Key Pattern
New settings keys must be registered in **both**:
1. `app/Application/Core/Handlers/GetSettingsHandler.php` — default allowlist
2. `app/Infrastructure/Http/Controllers/Admin/Settings.php` — `ADMIN_SETTINGS_KEYS` const

If you forget either, the key is saved to DB but never returned by the API.

## Common Pitfalls
- **vendor/ is gitignored** — always run `composer install --no-dev` on the server after pull
- **writable/ is gitignored** — create on fresh clone: `mkdir -p writable/{cache,logs,session,uploads} && chmod -R 777 writable/`
- **No new Composer packages** without explicit approval — justify need, alternatives, maintenance status
- **CI4 log location**: `writable/logs/log-YYYY-MM-DD.php` — check here first on 500 errors
- **CORS**: `Authorization` header must be in `Cors.php` allowed headers for Bearer token requests
- **Payment gateways**: use explicit `=== 'true'` string comparison for `env('PAYFAST_TEST', 'false')` — PHP truthiness will always evaluate env strings as truthy
