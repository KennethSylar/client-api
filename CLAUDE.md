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

## Skills
Use `/backend-architect` for adding controllers, routes, and DB queries.
Use `/deployment` when deploying to cPanel shared hosting.
