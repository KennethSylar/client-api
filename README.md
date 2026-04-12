# JNV API

CodeIgniter 4 REST API powering the [JNV Training and Development](https://jnv.co.za) website.

Serves content and admin endpoints consumed by the static Nuxt front-end at `jnv-site/`.

---

## Stack

| | |
|---|---|
| **Framework** | CodeIgniter 4 |
| **PHP** | 8.2+ |
| **Database** | MySQL 8.0+ |
| **Email** | PHPMailer (SMTP) |
| **Auth** | Stateless token in `admin_sessions` table, delivered via HttpOnly cookie |

---

## Project structure

```
app/
  Config/
    Routes.php            # All routes (explicit, auto-routing disabled)
    Filters.php           # adminauth + cors filters registered here
  Controllers/
    BaseController.php    # json(), error(), jsonBody() helpers
    Contact.php           # POST /contact — sends email via PHPMailer
    Admin/
      Auth.php            # POST /admin/login, /logout, GET /admin/me
      Settings.php        # PUT  /admin/settings
      Newsletters.php     # CRUD /admin/newsletters
      Documents.php       # CRUD /admin/documents
      Pages.php           # CRUD /admin/pages/:slug
      Upload.php          # POST /admin/upload  (images → Cloudinary or local)
      UploadPdf.php       # POST /admin/upload-pdf (PDFs → local)
    Content/
      Settings.php        # GET /content/settings
      Newsletters.php     # GET /content/newsletters
      Documents.php       # GET /content/documents
      Pages.php           # GET /content/pages, /content/page/:slug
  Filters/
    AdminAuth.php         # Validates jnv_admin_session cookie
    Cors.php              # Sets CORS headers
  Database/
    Migrations/
      ..._CreateCoreTables.php   # Creates all 5 tables
    Seeds/
      MainSeeder.php             # Default settings + 8 built-in pages
      AdminPasswordSeeder.php    # Interactive password updater
public/
  index.php               # CI4 front controller
  uploads/                # Local fallback for uploaded files (PDFs, images)
```

---

## API reference

### Public (no auth)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/content/settings` | Site settings as key-value object |
| `GET` | `/content/newsletters` | Published newsletters list |
| `GET` | `/content/documents` | Published documents list |
| `GET` | `/content/pages` | All page slugs + titles |
| `GET` | `/content/page/:slug` | Full page data JSON |
| `POST` | `/contact` | Contact form — sends email via SMTP |

### Admin (requires `jnv_admin_session` cookie)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/admin/login` | Authenticate and set session cookie |
| `POST` | `/admin/logout` | Clear session |
| `GET` | `/admin/me` | Check auth status |
| `PUT` | `/admin/settings` | Update site settings |
| `POST` | `/admin/newsletters` | Create newsletter |
| `PUT` | `/admin/newsletters/:id` | Update newsletter |
| `DELETE` | `/admin/newsletters/:id` | Delete newsletter |
| `POST` | `/admin/documents` | Create document |
| `PUT` | `/admin/documents/:id` | Update document |
| `DELETE` | `/admin/documents/:id` | Delete document |
| `POST` | `/admin/pages` | Create custom page |
| `PUT` | `/admin/pages/:slug` | Update page content |
| `DELETE` | `/admin/pages/:slug` | Delete custom page |
| `POST` | `/admin/upload` | Upload image (Cloudinary → local fallback) |
| `POST` | `/admin/upload-pdf` | Upload PDF (local storage) |

---

## Local setup

### Requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `intl`
- MySQL 8.0+
- Composer

### Install

```bash
composer install
cp .env.example .env
# Edit .env with your database credentials and SMTP settings
```

### Database

```bash
# Create tables
php spark migrate

# Seed initial data (settings + 8 built-in pages, default password: changeme)
php spark db:seed MainSeeder

# Set the real admin password
php spark db:seed AdminPasswordSeeder
```

### Dev server

CI4's built-in server (useful for local development):

```bash
php spark serve
# Listening on http://localhost:8080
```

Point the Nuxt front-end at it:

```bash
# jnv-site/.env
NUXT_PUBLIC_API_BASE=http://localhost:8080
```

---

## Deployment

This API is deployed at `jnv.co.za/api/` alongside the static Nuxt output. Nginx routes `/api/*` to PHP-FPM, stripping the `/api` prefix before CI4's router sees the request.

See [`../docs/DEPLOY.md`](../docs/DEPLOY.md) for the full deployment guide.

### Quick deploy

```bash
rsync -az --exclude='.env' --exclude='writable/' --exclude='vendor/' \
  ./ user@jnv.co.za:/var/www/jnv/api/

ssh user@jnv.co.za "cd /var/www/jnv/api && composer install --no-dev --optimize-autoloader"
```

---

## Environment variables

Copy `.env.example` to `.env` and fill in:

| Key | Description |
|-----|-------------|
| `CI_ENVIRONMENT` | `development` or `production` |
| `app.baseURL` | Full URL where the API is served, e.g. `https://jnv.co.za/api/` |
| `app.allowedOrigins` | CORS origin, e.g. `https://jnv.co.za` |
| `database.default.hostname` | MySQL host |
| `database.default.database` | Database name |
| `database.default.username` | MySQL user |
| `database.default.password` | MySQL password |
| `email.SMTPHost` | SMTP server |
| `email.SMTPPort` | SMTP port (`587` for TLS, `465` for SSL) |
| `email.SMTPCrypto` | `tls` or `ssl` |
| `email.SMTPUser` | SMTP username |
| `email.SMTPPass` | SMTP password |
| `email.fromEmail` | From address for outgoing emails |
| `email.fromName` | From name |
| `jnv.contactToEmail` | Recipient for contact form submissions |

---

## Admin password

The password hash is stored in the `settings` table under the key `admin_password_hash`. Change it after first deploy:

```bash
php spark db:seed AdminPasswordSeeder
# Prompts interactively

# Or non-interactively:
JNV_ADMIN_PASSWORD=yourpassword php spark db:seed AdminPasswordSeeder
```
