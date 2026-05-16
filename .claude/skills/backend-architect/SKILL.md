# Backend Architect

You are working in `client-api/` — a CodeIgniter 4 REST API (PHP 8.1+, MySQL, no CI4 Models).

## Working Directory Rule
All file paths are relative to `client-api/`. Never touch `client-site/` or `client-template/` unless the task explicitly requires cross-repo changes.

## Before You Start
1. Read every file you will modify — never edit blindly
2. Check `app/Config/Routes.php` — all routes are explicit, no auto-routing
3. Check `app/Domain/` for existing repository interfaces before creating new ones
4. Check `app/Infrastructure/Persistence/` for existing MySQL implementations

## Actual Project Structure
```
app/
  Config/
    Routes.php              ← ALL routes defined here — no auto-routing
    Filters.php             ← Register AdminAuth, Cors, SecurityHeaders, CustomerAuth
  Domain/
    Core/
      AdminSessionRepositoryInterface.php
      PageRepositoryInterface.php
      SettingsRepositoryInterface.php
    Orders/
      CustomerRepositoryInterface.php
      CustomerAddressRepositoryInterface.php
      OrderRepositoryInterface.php
    Shop/
      CategoryRepositoryInterface.php
      ProductRepositoryInterface.php
      ReviewRepositoryInterface.php
      StockRepositoryInterface.php
  Application/
    Core/
      Commands/   ← AdminLoginCommand, SavePageCommand, UpdateSettingsCommand, etc.
      Handlers/   ← AdminLoginHandler, GetSettingsHandler, GetPageHandler, etc.
    Orders/
      Commands/   ← PlaceOrderCommand, RecordPaymentCommand, RegisterCustomerCommand, etc.
      Handlers/   ← PlaceOrderHandler, RecordPaymentHandler, ListOrdersHandler, etc.
    Shop/
      Commands/   ← CreateProductCommand, UpdateProductCommand, SubmitReviewCommand, etc.
      Handlers/   ← CreateProductHandler, ListProductsHandler, SubmitReviewHandler, etc.
  Infrastructure/
    Http/
      Controllers/
        BaseController.php     ← ok(), error(), json(), jsonBody() helpers
        Contact.php
        Content/
          Settings.php         ← GET /content/settings (public, allowlisted keys)
          Pages.php            ← GET /content/pages, /content/page/:slug
        Admin/
          Auth.php             ← POST /admin/login, GET /admin/me, POST /admin/logout
          Pages.php            ← Admin CRUD for pages
          Settings.php         ← GET/PUT /admin/settings (ADMIN_SETTINGS_KEYS allowlist)
          Analytics.php        ← GET /admin/analytics
          Upload.php           ← POST /admin/upload (image)
          UploadPdf.php        ← POST /admin/upload/pdf
          Shop/
            Products.php       ← Admin products CRUD + CSV export/import
            Categories.php     ← Admin categories CRUD
            Orders.php         ← Admin orders list/detail/status/refund/invoice
            Reviews.php        ← Admin reviews moderation
            Stock.php          ← Admin stock adjustments
            Images.php         ← Product image reorder/delete
        Shop/
          Products.php         ← GET /shop/products, /shop/products/:slug
          Categories.php       ← GET /shop/categories
          Checkout.php         ← POST /shop/checkout
          PaymentNotify.php    ← POST /shop/payment/payfast/notify + ozow/notify
          Orders.php           ← GET /shop/orders/:token (public, by token)
          CustomerAuth.php     ← register, login, logout, me, update, orders
          CustomerAddresses.php← CRUD /shop/account/addresses
          CustomerOrders.php   ← cancel, refund-request
          CartValidation.php   ← POST /shop/cart/validate (stock check)
          Reviews.php          ← GET /shop/products/:id/reviews, POST submit
      Filters/
        AdminAuth.php          ← Cookie jnv_admin_session → admin_sessions table
        Cors.php
        SecurityHeaders.php
    Persistence/
      AbstractMysqlRepository.php
      MySqlAdminSessionRepository.php
      MySqlPageRepository.php
      MySqlSettingsRepository.php
      MySqlProductRepository.php
      MySqlCategoryRepository.php
      MySqlOrderRepository.php
      MySqlCustomerRepository.php
      MySqlCustomerAddressRepository.php
      MySqlReviewRepository.php
      MySqlStockRepository.php
    Gateways/
      PayFastGateway.php       ← PAYFAST_TEST must be 'true' (string) to enable sandbox
      OzowGateway.php          ← OZOW_TEST must be 'true' (string) to enable sandbox
  Database/
    Migrations/
      2024-01-01-100000_CreateCoreTables.php    ← admin_sessions, settings, pages
      2024-01-02-100000_CreateShopTables.php    ← products, categories, variants, images
      2024-01-02-110000_CreateShopStockAdjustments.php
      2024-01-02-120000_AddLowStockAlertedAt.php
      2024-01-03-100000_CreateOrderTables.php   ← shop_orders, order_items, customers, customer_sessions
      2024-01-03-110000_CreateCustomerSessions.php
      2024-01-04-100000_AddTrackingToShopOrders.php
      2024-01-05-100000_CreateProductReviews.php
      2024-01-05-110000_CreateOrderRefunds.php
      2024-01-05-120000_AddPartialRefundStatus.php
      2024-01-06-100000_CreateCustomerAddresses.php
    Seeds/
      AdminPasswordSeeder.php
      MainSeeder.php
```

## Adding a New Feature — Checklist
1. **Route** in `Routes.php` with correct filter
2. **Controller method** returning `$this->ok($data)` or `$this->error('msg', 400)`
3. **Command** (value object) in `Application/{Domain}/Commands/`
4. **Handler** in `Application/{Domain}/Handlers/` — one handler per use case
5. **Repository interface** in `Domain/{Domain}/` if DB access needed
6. **MySQL implementation** in `Infrastructure/Persistence/`, injected via constructor
7. **Migration** if new table or column needed
8. **Rate limit** on all public/auth endpoints

## BaseController Helpers
```php
return $this->ok($data);                     // 200 {"ok":true, ...spread $data}
return $this->error('Not found', 404);       // {"error":"Not found"}
$body = $this->jsonBody();                   // decoded JSON body (array)
$token = $this->bearerToken();              // Authorization: Bearer <token>
return $this->tooManyRequests('msg');       // 429
```

## Rate Limiting
```php
$ip = $this->request->getIPAddress();
if ($this->rateLimited("login_ip:{$ip}", 10, 900)) {
    return $this->tooManyRequests('Too many attempts.');
}
// Key format: "action:identifier" — md5 hashed internally with rl_ prefix
// Uses CI4 cache (file-based by default)
```

## Auth Filters (Routes.php)
```php
['filter' => 'adminauth']       // any admin role (admin or shop_admin) — ALL admin routes
['filter' => 'customerauth']    // authenticated customer
// No filter = public
```

## Repository Pattern
```php
// Interface in Domain/
interface ProductRepositoryInterface {
    public function findBySlug(string $slug): ?array;
    public function list(array $filters = []): array;
    public function create(array $data): int;       // returns new ID
    public function update(int $id, array $data): void;
    public function delete(int $id): void;
}

// Implementation in Infrastructure/Persistence/
class MySqlProductRepository extends AbstractMysqlRepository implements ProductRepositoryInterface {
    public function findBySlug(string $slug): ?array {
        return $this->db->table('products')->where('slug', $slug)->get()->getRowArray();
    }
}

// Inject the interface (never the concrete class)
class MyHandler {
    public function __construct(private ProductRepositoryInterface $products) {}
}
```

## Settings Keys — BOTH must be updated for new keys
1. `Application/Core/Handlers/GetSettingsHandler.php` — default allowlist (public `/content/settings`)
2. `Infrastructure/Http/Controllers/Admin/Settings.php` — `ADMIN_SETTINGS_KEYS` const

If you forget either: key saves to DB but the endpoint never returns it.

## Security Rules
- **Sensitive settings** (`shop_payfast_merchant_key`, `shop_payfast_passphrase`, `shop_ozow_private_key`, `shop_ozow_api_key`) — masked as `••••••••` in GET responses, listed in `Admin/Settings::SENSITIVE_KEYS`
- **Payment order not found** → `400 'Invalid notification'` (not `404` — prevents order enumeration)
- **Auth errors** → same message for wrong username vs wrong password — no user enumeration
- **Payment test mode** → `env('PAYFAST_TEST', 'false') === 'true'` (explicit string — never PHP truthiness)
- **Rate limit** all auth + registration + payment notify endpoints

## Adding a Migration
```php
// Filename: app/Database/Migrations/YYYY-MM-DD-HHmmss_DescribePurpose.php
class DescribePurpose extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('table_name');
    }
    public function down(): void {
        $this->forge->dropTable('table_name');
    }
}
```
Run: `php spark migrate` | Status: `php spark migrate:status`

## Common Pitfalls
- **vendor/ is gitignored** — run `composer install --no-dev` on server after pull
- **writable/ is gitignored** — create on fresh clone: `mkdir -p writable/{cache,logs,session,uploads} && chmod -R 777 writable/`
- **No new Composer packages** without explicit approval — justify need + alternatives
- **CI4 logs** at `writable/logs/log-YYYY-MM-DD.php` — check here first on 500 errors
- **CORS** — `Authorization` header must be in `Cors.php` allowed headers (already set)
- **Payment gateways** — always `=== 'true'` string comparison for test mode env vars
- **Admin cookie name** — `jnv_admin_session` — rename per client (Critical Gotcha #16 in client-template/CLAUDE.md)
