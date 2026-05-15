# Client API — CodeIgniter 4

## Stack
CodeIgniter 4 PHP REST API. Layered architecture: Domain → Application → Infrastructure. Explicit routes only.

## Dev Commands
```bash
cd client-api
php spark serve     # local dev on :8080
php spark migrate   # run DB migrations
```

---

## AI Agent Rules

You are a senior developer. Follow these rules to produce reliable, maintainable code.

### Before Writing Code (MANDATORY)
1. **Confirm understanding** — restate the requirement in your own words
2. **State approach** — 1–2 sentences on what you'll do
3. **Identify assumptions** — list what you're assuming
4. **Ask for clarification** — if ANY aspect is unclear, STOP and ask
5. **Propose exact file path** — wait for approval before creating new files

### Communication
- Lead with the answer, not the reasoning
- Reference by function/line — don't repeat existing code
- Show only changed sections
- No docs unless requested; no trailing summaries

### Core Principles
1. **Clarify first** — ask before deciding; never assume
2. **Keep it simple** — functions < 20 lines; files < 200 lines; max 3 params (use objects for more)
3. **Abstract first** — interfaces before implementations; use the ports defined in Application/Ports
4. **Protect boundaries** — domain entities never cross layer boundaries; use DTOs at all ports

---

## Layered Architecture

> **See `MIGRATION.md` for the full migration plan and story checklist.**

### Layer Rules

| Layer | Location | May use | May NOT use |
|-------|----------|---------|-------------|
| Domain | `app/Domain/` | stdlib only | CodeIgniter, DB, Cloudinary, Resend, Dompdf |
| Application | `app/Application/` | Domain interfaces | CodeIgniter, DB, HTTP |
| Infrastructure | `app/Infrastructure/` | Everything | — |

### Adding a New Feature — Checklist

Before implementing any new endpoint:

1. ✅ Does a Domain entity exist for this data? If not, create one in `app/Domain/`
2. ✅ Does a repository interface exist? If not, add it to `app/Domain/`
3. ✅ Is the implementation a mutation? → Create Command + Handler in `app/Application/`
4. ✅ Is the implementation a read? → Create Query + Handler in `app/Application/`
5. ✅ Controller accepts HTTP input and builds a Command/Query DTO?
6. ✅ Controller calls handler, maps result to JSON response?
7. ✅ Domain knows nothing about HTTP/JSON?
8. ✅ No `\Config\Database::connect()` in Domain or Application layers?

### Request Flow

```
HTTP Request
  → Infrastructure\Http\Controllers\*  (parse input → build Command/Query)
  → Application\Handler                (orchestrate domain, call ports)
  → Domain\Entity / Repository         (pure business logic)
  → Infrastructure\Persistence         (DB query, returns entity)
  → Application\Handler                (build response DTO)
  → Infrastructure\Http\Controllers\*  (JSON response)
```

### Wiring a New Command

```php
// 1. app/Application/Shop/Commands/MyCommand.php
final class MyCommand {
    public function __construct(
        public readonly int    $productId,
        public readonly string $name,
    ) {}
}

// 2. app/Application/Shop/Handlers/MyHandler.php
final class MyHandler {
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(MyCommand $cmd): Product {
        // domain logic only — no DB, no HTTP
    }
}

// 3. app/Config/Services.php — add factory method
public static function myHandler(bool $getShared = true): MyHandler {
    if ($getShared) return static::getSharedInstance('myHandler');
    return new MyHandler(static::productRepository());
}

// 4. app/Infrastructure/Http/Controllers/Admin/Shop/Products.php
$cmd    = new MyCommand($id, $body['name']);
$result = service('myHandler')->handle($cmd);
return $this->ok(['product' => $result->toArray()]);
```

### Wiring a New Repository

```php
// 1. app/Domain/Shop/ThingRepositoryInterface.php
interface ThingRepositoryInterface {
    public function findById(int $id): ?Thing;
    public function save(Thing $thing): Thing;
}

// 2. app/Infrastructure/Persistence/MySqlThingRepository.php
class MySqlThingRepository extends AbstractMysqlRepository implements ThingRepositoryInterface {
    public function findById(int $id): ?Thing { ... }
    public function save(Thing $thing): Thing { ... }
}

// 3. app/Config/Services.php
public static function thingRepository(bool $getShared = true): ThingRepositoryInterface {
    if ($getShared) return static::getSharedInstance('thingRepository');
    return new MySqlThingRepository(\Config\Database::connect());
}
```

---

## Key Patterns

### All responses use BaseController helpers
```php
return $this->ok();                        // 200 { ok: true }
return $this->ok(['data' => $rows]);       // 200 with payload
return $this->error('Bad input', 400);     // error response
return $this->notFound();                  // 404
return $this->unauthorized();             // 401
$body = $this->jsonBody();                // parse JSON or form-encoded body
```

### Admin routes are protected by AdminAuth filter
```php
$routes->group('admin', ['filter' => 'adminauth'], function ($routes) {
    // add routes here
});
```

### Financials — always store in cents (integers), never floats
```php
$cents = (int)round($amount * 100);   // convert
$rand  = $cents / 100;                // display
```

### Shop guard — add to every public shop endpoint
```php
if ($off = $this->shopOffline()) return $off;  // 503 when shop_enabled != '1'
```

### Stock adjustment — use StockRepository (NOT the old static helper)
```php
// ✅ CORRECT (post-migration)
service('stockRepository')->logAdjustment($productId, $variantId, $delta, 'order', $referenceId, $note, $before, $after);

// ❌ DEPRECATED — do not use in new code
\App\Controllers\Admin\Shop\Stock::logAdjustment($db, ...);
```

### Low-stock alert — use LowStockNotifier port (NOT the old static helper)
```php
// ✅ CORRECT (post-migration)
service('lowStockNotifier')->notifyIfNeeded($productEntity);

// ❌ DEPRECATED — do not use in new code
\App\Services\LowStockMailer::checkAndSend($db, $productId);
```

### Order confirmation email — use RecordPaymentHandler (NOT the old static helper)
```php
// ✅ CORRECT (post-migration) — triggered inside RecordPaymentHandler automatically

// ❌ DEPRECATED — do not use in new code
\App\Services\OrderMailer::sendConfirmation($db, $orderId);
```

### Cart validation endpoint
POST /shop/cart/validate — accepts [{product_id, variant_id?, qty, price}]
Returns per-item: effective_price, qty_adjusted, in_stock, stock_changed, price_changed, removed

### Payment gateways
- PayFast: `service('payfastGateway')->buildPaymentUrl(...)` / `verifyNotification(...)`
- Ozow: `service('ozowGateway')->buildPaymentUrl(...)` / `verifyNotification(...)`
- PAYFAST_TEST / OZOW_TEST env vars switch between sandbox and production

### Customer auth — Bearer token (not cookies)
```php
$token = substr($this->request->getHeaderLine('Authorization'), 7);
```

### Test database
Feature tests use `$DBGroup = 'tests'` → `client_cms_test` MySQL database.
Create once: `mysql -u root -e "CREATE DATABASE IF NOT EXISTS client_cms_test;"`

---

## Conventions
- All routes must be explicit in `app/Config/Routes.php` — no auto-routing
- `.env` is never committed (gitignored)
- `vendor/` is gitignored — run `composer install --no-dev` on server after git pull
- Log errors: `log_message('error', 'Context: ' . $e->getMessage())`
- No new dependencies without explicit approval — justify need, alternatives, maintenance status

## Skills
Use `/backend-architect` for adding controllers, routes, and DB queries.
Use `/deployment` when deploying to cPanel shared hosting.
