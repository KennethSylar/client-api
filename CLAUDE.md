# Client API — CodeIgniter 4

## Stack
CodeIgniter 4 PHP REST API. No Models — direct query builder. Explicit routes only.

## Dev Commands
```bash
cd client-api
php spark serve     # local dev on :8080
php spark migrate   # run DB migrations
```

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
Add new admin routes inside the group in Routes.php:
```php
$routes->group('admin', ['filter' => 'adminauth'], function ($routes) {
    // add routes here
});
```

### Direct query builder (no Models)
```php
$db   = \Config\Database::connect();
$rows = $db->table('pages')->get()->getResultArray();
$db->table('pages')->insert(['slug' => $slug, 'title' => $title]);
$db->table('pages')->where('id', $id)->update(['title' => $title]);
$db->table('pages')->where('id', $id)->delete();
```

## Conventions
- All routes must be explicit in app/Config/Routes.php — no auto-routing
- .env is never committed (gitignored)
- vendor/ is gitignored — run `composer install --no-dev` on server after git pull
- Log errors: `log_message('error', 'Context: ' . $e->getMessage())`

## Shop / E-commerce Patterns

### Shop guard — add to every public shop endpoint
```php
if ($off = $this->shopOffline()) return $off;  // 503 when shop_enabled != '1'
```

### Financials — always store in cents (integers), never floats
```php
$cents = (int)round($amount * 100);   // convert
$rand  = $cents / 100;                // display
```

### Stock adjustment logging — reuse the static helper
```php
// $source: 'manual' | 'order' | 'refund' | 'import'
\App\Controllers\Admin\Shop\Stock::logAdjustment($db, $productId, $variantId, $delta, $source, $referenceId);
```

### Low-stock alert — call after any negative stock adjustment
```php
\App\Services\LowStockMailer::checkAndSend($db, $productId);
// - No-op if RESEND_API_KEY not set or stock is healthy
// - 24-hour debounce via low_stock_alerted_at column
```

### Order confirmation email (with PDF invoice)
```php
\App\Services\OrderMailer::sendConfirmation($db, $orderId);
// - No-op if RESEND_API_KEY not set
// - Attaches InvoicePdf::generate() output as base64 attachment
```

### Cart validation endpoint
POST /shop/cart/validate — accepts [{product_id, variant_id?, qty, price}]
Returns per-item: effective_price, qty_adjusted, in_stock, stock_changed, price_changed, removed

### Payment gateways
- PayFast: signature = md5(sorted params + passphrase), redirect to payfast.co.za/eng/process
- Ozow: hash = sha512(lowercase concatenation), redirect to pay.ozow.com
- ITN/notify endpoints handle server-to-server callbacks — verify signatures before marking paid
- PAYFAST_TEST / OZOW_TEST env vars switch between sandbox and production

### Customer auth — Bearer token (not cookies)
```php
// In controller: get token from Authorization header
$token = substr($this->request->getHeaderLine('Authorization'), 7);
// In CustomerAuth, call requireCustomer() to get the customer array or return 401
```

### Test database
Feature tests use `$DBGroup = 'tests'` → `client_cms_test` MySQL database.
Create once: `mysql -u root -e "CREATE DATABASE IF NOT EXISTS client_cms_test;"`

## Skills
Use `/backend-architect` for adding controllers, routes, and DB queries.
Use `/deployment` when deploying to cPanel shared hosting.
