# Layered Architecture Migration

Tracking the full refactor of the CodeIgniter 4 API from flat controllers + static services
into a strict **Domain → Application → Infrastructure** layered architecture.

> **Rule:** every story must leave the API runnable before moving to the next.  
> **Rule:** tick the checkbox only after the story is code-complete and `php spark serve` starts without errors.

---

## Architecture Target

```
app/
├── Domain/          ← pure PHP, zero framework deps (entities, value objects, repository interfaces)
├── Application/     ← orchestrates domain, no HTTP/DB knowledge (commands, queries, handlers, ports)
├── Infrastructure/  ← concrete implementations (MySql repos, Resend mailer, gateways, HTTP controllers)
│   ├── Persistence/
│   ├── Services/
│   ├── Gateways/
│   └── Http/
│       ├── Controllers/
│       └── Filters/
├── Controllers/     ← emptied during M7, then deleted
├── Filters/         ← emptied during M7, then deleted
└── Services/        ← retired in Story 6.16
```

**Layer import rules:**
| Layer | May use | May NOT use |
|-------|---------|-------------|
| Domain | stdlib only | CodeIgniter, Cloudinary, Resend, Dompdf |
| Application | Domain interfaces | CodeIgniter, DB, HTTP |
| Infrastructure | Everything | — |

---

## Progress

| Milestone | Stories | Done | Status |
|-----------|---------|------|--------|
| M0 Foundation | 4 | 4 | ✅ |
| M1 Domain Layer | 6 | 6 | ✅ |
| M2 Infrastructure: Persistence | 9 | 9 | ✅ |
| M3 Infrastructure: Services & Gateways | 6 | 6 | ✅ |
| M4 Application: Commands | 13 | 13 | ✅ |
| M5 Application: Queries | 7 | 7 | ✅ |
| M6 Thin Controllers | 16 | 16 | ✅ |
| M7 Move Http Layer | 6 | 6 | ✅ |
| **Total** | **66** | **67** | ✅ |

---

## M0 — Foundation

> Goal: skeleton exists, namespaces resolve, CLAUDE.md updated, Services.php stubs in place.

- [x] **0.1** — Create folder skeleton (`app/Domain/`, `app/Application/`, `app/Infrastructure/` + sub-dirs with `.gitkeep`)
- [x] **0.2** — Register new namespaces in `app/Config/Autoload.php`
- [x] **0.3** — Update `CLAUDE.md` with layered architecture conventions and AI dev rules
- [x] **0.4** — Add interface stub factory methods to `app/Config/Services.php`

---

## M1 — Domain Layer

> Goal: all entities, value objects, and interfaces exist as pure PHP classes.  
> Constraint: zero `use CodeIgniter\*` in any file in this milestone.

- [x] **1.1** — Value Objects
  - `app/Domain/Shared/Money.php`
  - `app/Domain/Shared/Address.php`
  - `app/Domain/Shared/PaginatedResult.php`
  - `app/Domain/Orders/OrderStatus.php` (backed enum with `canTransitionTo()`)
  - `app/Domain/Shop/PaymentGateway.php` (backed enum)

- [x] **1.2** — Core domain entities
  - `app/Domain/Core/Setting.php`
  - `app/Domain/Core/Page.php`

- [x] **1.3** — Shop domain entities
  - `app/Domain/Shop/Category.php`
  - `app/Domain/Shop/ProductImage.php`
  - `app/Domain/Shop/ProductVariant.php`
  - `app/Domain/Shop/Product.php` (with `isLowStock()` + `needsLowStockAlert()` domain rules)

- [x] **1.4** — Orders domain entities
  - `app/Domain/Orders/Customer.php`
  - `app/Domain/Orders/OrderItem.php`
  - `app/Domain/Orders/OrderStatusLogEntry.php`
  - `app/Domain/Orders/Order.php`

- [x] **1.5** — Repository interfaces + query filter objects
  - `app/Domain/Core/SettingsRepositoryInterface.php`
  - `app/Domain/Core/PageRepositoryInterface.php`
  - `app/Domain/Core/AdminSessionRepositoryInterface.php`
  - `app/Domain/Shop/CategoryRepositoryInterface.php`
  - `app/Domain/Shop/ProductRepositoryInterface.php`
  - `app/Domain/Shop/StockRepositoryInterface.php`
  - `app/Domain/Shop/ProductFilter.php`
  - `app/Domain/Orders/OrderRepositoryInterface.php`
  - `app/Domain/Orders/CustomerRepositoryInterface.php`
  - `app/Domain/Orders/OrderFilter.php`

- [x] **1.6** — Application ports (external service interfaces)
  - `app/Application/Ports/MailerInterface.php`
  - `app/Application/Ports/InvoicePdfInterface.php`
  - `app/Application/Ports/ImageUploaderInterface.php`
  - `app/Application/Ports/PaymentGatewayInterface.php`
  - `app/Application/Ports/LowStockNotifierInterface.php`

---

## M2 — Infrastructure: Persistence

> Goal: every repository interface has a concrete MySql implementation, bound in Services.php.  
> May use CI4 query builder. No HTTP, no mailer, no Cloudinary.

- [x] **2.1** — `app/Infrastructure/Persistence/AbstractMysqlRepository.php`  
  _(base class: `db()` helper, `paginate()`, `now()`, `slugify()`, `uniqueSlug()`)_

- [x] **2.2** — `app/Infrastructure/Persistence/MySqlSettingsRepository.php`  
  + bind `settingsRepository` in `Services.php`

- [x] **2.3** — `app/Infrastructure/Persistence/MySqlPageRepository.php`  
  + bind `pageRepository` in `Services.php`

- [x] **2.4** — `app/Infrastructure/Persistence/MySqlAdminSessionRepository.php`  
  + bind `adminSessionRepository` in `Services.php`

- [x] **2.5** — `app/Infrastructure/Persistence/MySqlCategoryRepository.php`  
  + bind `categoryRepository` in `Services.php`

- [x] **2.6** — `app/Infrastructure/Persistence/MySqlProductRepository.php`  
  _(covers products + images + variants sub-aggregates)_  
  + bind `productRepository` in `Services.php`

- [x] **2.7** — `app/Infrastructure/Persistence/MySqlStockRepository.php`  
  _(replaces static `Stock::logAdjustment()`)_  
  + bind `stockRepository` in `Services.php`

- [x] **2.8** — `app/Infrastructure/Persistence/MySqlOrderRepository.php`  
  _(covers orders + items + status log hydration)_  
  + bind `orderRepository` in `Services.php`

- [x] **2.9** — `app/Infrastructure/Persistence/MySqlCustomerRepository.php`  
  _(covers customers + customer sessions)_  
  + bind `customerRepository` in `Services.php`

---

## M3 — Infrastructure: Services & Gateways

> Goal: all external integrations behind interfaces. Old static `Services/` classes stay alive until Story 6.16.

- [x] **3.1** — `app/Infrastructure/Services/ResendMailer.php` implements `MailerInterface`  
  _(moves OrderMailer + LowStockMailer + Contact email logic)_  
  + bind `mailer` in `Services.php`

- [x] **3.2** — `app/Infrastructure/Services/LowStockNotifier.php` implements `LowStockNotifierInterface`  
  _(wraps ResendMailer + StockRepository; adds `stampLowStockAlert()` to ProductRepositoryInterface + MySqlProductRepository)_  
  + bind `lowStockNotifier` in `Services.php`

- [x] **3.3** — `app/Infrastructure/Services/DompdfInvoicePdf.php` implements `InvoicePdfInterface`  
  _(moves InvoicePdf::generate() + buildHtml())_  
  + bind `invoicePdf` in `Services.php`

- [x] **3.4** — `app/Infrastructure/Services/CloudinaryUploader.php` implements `ImageUploaderInterface`  
  _(moves Upload + UploadPdf cloudinary calls)_  
  + bind `imageUploader` in `Services.php`

- [x] **3.5** — `app/Infrastructure/Gateways/PayFastGateway.php` implements `PaymentGatewayInterface`  
  _(moves `payfastUrl()` + `verifyPayfastSignature()` from Checkout + PaymentNotify)_  
  + bind `payfastGateway` in `Services.php`

- [x] **3.6** — `app/Infrastructure/Gateways/OzowGateway.php` implements `PaymentGatewayInterface`  
  _(moves `ozowUrl()` + `verifyOzowHash()`)_  
  + bind `ozowGateway` in `Services.php`

---

## M4 — Application: Commands (Mutations)

> Goal: every mutation is a Command + Handler pair. Handlers inject interfaces only — no `\Config\Database::connect()`.

- [x] **4.1** — Admin Auth
  - `app/Application/Core/Commands/AdminLoginCommand.php`
  - `app/Application/Core/Handlers/AdminLoginHandler.php`

- [x] **4.2** — Settings
  - `app/Application/Core/Commands/UpdateSettingsCommand.php`
  - `app/Application/Core/Handlers/UpdateSettingsHandler.php`

- [x] **4.3** — Pages
  - `app/Application/Core/Commands/SavePageCommand.php`
  - `app/Application/Core/Commands/DeletePageCommand.php`
  - `app/Application/Core/Handlers/SavePageHandler.php`
  - `app/Application/Core/Handlers/DeletePageHandler.php`

- [x] **4.4** — Categories
  - `app/Application/Shop/Commands/CreateCategoryCommand.php`
  - `app/Application/Shop/Commands/UpdateCategoryCommand.php`
  - `app/Application/Shop/Commands/DeleteCategoryCommand.php`
  - `app/Application/Shop/Commands/ReorderCategoriesCommand.php`
  - `app/Application/Shop/Handlers/CreateCategoryHandler.php`
  - `app/Application/Shop/Handlers/UpdateCategoryHandler.php`
  - `app/Application/Shop/Handlers/DeleteCategoryHandler.php`
  - `app/Application/Shop/Handlers/ReorderCategoriesHandler.php`

- [x] **4.5** — Products
  - `app/Application/Shop/Commands/CreateProductCommand.php`
  - `app/Application/Shop/Commands/UpdateProductCommand.php`
  - `app/Application/Shop/Commands/DeleteProductCommand.php`
  - `app/Application/Shop/Handlers/CreateProductHandler.php`
  - `app/Application/Shop/Handlers/UpdateProductHandler.php`
  - `app/Application/Shop/Handlers/DeleteProductHandler.php`

- [x] **4.6** — Product Images
  - `app/Application/Shop/Commands/AddProductImageCommand.php`
  - `app/Application/Shop/Commands/DeleteProductImageCommand.php`
  - `app/Application/Shop/Commands/ReorderProductImagesCommand.php`
  - `app/Application/Shop/Handlers/AddProductImageHandler.php`
  - `app/Application/Shop/Handlers/DeleteProductImageHandler.php`
  - `app/Application/Shop/Handlers/ReorderProductImagesHandler.php`

- [x] **4.7** — Stock Adjustment
  - `app/Application/Shop/Commands/AdjustStockCommand.php`
  - `app/Application/Shop/Handlers/AdjustStockHandler.php`

- [x] **4.8** — Order Status & Refund
  - `app/Application/Orders/Commands/UpdateOrderStatusCommand.php`
  - `app/Application/Orders/Commands/RefundOrderCommand.php`
  - `app/Application/Orders/Handlers/UpdateOrderStatusHandler.php` _(uses `OrderStatus::canTransitionTo()`)_
  - `app/Application/Orders/Handlers/RefundOrderHandler.php` _(restores stock)_

- [x] **4.9** — Payment Notifications
  - `app/Application/Orders/Commands/RecordPaymentCommand.php`
  - `app/Application/Orders/Commands/CancelOrderCommand.php`
  - `app/Application/Orders/Handlers/RecordPaymentHandler.php` _(marks paid, sends confirmation email)_
  - `app/Application/Orders/Handlers/CancelOrderHandler.php` _(marks cancelled, restores stock)_

- [x] **4.10** — Checkout / Place Order
  - `app/Application/Orders/Commands/PlaceOrderCommand.php`
  - `app/Application/Orders/DTOs/CartItemDTO.php`
  - `app/Application/Orders/DTOs/PlaceOrderResult.php`
  - `app/Application/Orders/Handlers/PlaceOrderHandler.php` _(validate, compute totals, create order, decrement stock)_

- [x] **4.11** — Customer Auth
  - `app/Application/Orders/Commands/RegisterCustomerCommand.php`
  - `app/Application/Orders/Commands/LoginCustomerCommand.php`
  - `app/Application/Orders/Commands/LogoutCustomerCommand.php`
  - `app/Application/Orders/Commands/UpdateCustomerCommand.php`
  - `app/Application/Orders/Handlers/RegisterCustomerHandler.php`
  - `app/Application/Orders/Handlers/LoginCustomerHandler.php`
  - `app/Application/Orders/Handlers/LogoutCustomerHandler.php`
  - `app/Application/Orders/Handlers/UpdateCustomerHandler.php`

- [x] **4.12** — Uploads
  - `app/Application/Core/Commands/UploadImageCommand.php`
  - `app/Application/Core/Commands/UploadPdfCommand.php`
  - `app/Application/Core/Handlers/UploadImageHandler.php`
  - `app/Application/Core/Handlers/UploadPdfHandler.php`

- [x] **4.13** — Contact
  - `app/Application/Core/Commands/SendContactEnquiryCommand.php`
  - `app/Application/Core/Handlers/SendContactEnquiryHandler.php`

---

## M5 — Application: Queries (Reads)

- [x] **5.1** — Core queries
  - `app/Application/Core/Queries/GetSettingsQuery.php`
  - `app/Application/Core/Queries/ListPagesQuery.php`
  - `app/Application/Core/Queries/GetPageQuery.php`
  - `app/Application/Core/Handlers/GetSettingsHandler.php`
  - `app/Application/Core/Handlers/ListPagesHandler.php`
  - `app/Application/Core/Handlers/GetPageHandler.php`

- [x] **5.2** — Category queries
  - `app/Application/Shop/Queries/ListCategoriesQuery.php`
  - `app/Application/Shop/Handlers/ListCategoriesHandler.php`

- [x] **5.3** — Product queries
  - `app/Application/Shop/Queries/ListProductsQuery.php`
  - `app/Application/Shop/Queries/GetProductQuery.php`
  - `app/Application/Shop/Handlers/ListProductsHandler.php`
  - `app/Application/Shop/Handlers/GetProductHandler.php`

- [x] **5.4** — Order queries
  - `app/Application/Orders/Queries/ListOrdersQuery.php`
  - `app/Application/Orders/Queries/GetOrderQuery.php`
  - `app/Application/Orders/Handlers/ListOrdersHandler.php`
  - `app/Application/Orders/Handlers/GetOrderHandler.php`

- [x] **5.5** — Stock history
  - `app/Application/Shop/Queries/GetStockHistoryQuery.php`
  - `app/Application/Shop/Handlers/GetStockHistoryHandler.php`

- [x] **5.6** — Customer queries
  - `app/Application/Orders/Queries/GetCustomerOrdersQuery.php`
  - `app/Application/Orders/Handlers/GetCustomerOrdersHandler.php`

- [x] **5.7** — Invoice
  - `app/Application/Orders/Queries/GetOrderInvoiceQuery.php`
  - `app/Application/Orders/Handlers/GetOrderInvoiceHandler.php`

---

## M6 — Thin Controllers

> Goal: every controller is an HTTP adapter only. No `\Config\Database::connect()`, no business logic.  
> Pattern per controller: parse input → build Command/Query → call handler → map result → return response.

- [x] **6.1** — `app/Controllers/Admin/Auth.php`
- [x] **6.2** — `app/Controllers/Admin/Settings.php`
- [x] **6.3** — `app/Controllers/Admin/Pages.php` + `Content/Pages.php` + `Content/Settings.php`
- [x] **6.4** — `app/Controllers/Admin/Upload.php` + `Admin/UploadPdf.php`
- [x] **6.5** — `app/Controllers/Admin/Shop/Categories.php`
- [x] **6.6** — `app/Controllers/Admin/Shop/Products.php`
- [x] **6.7** — `app/Controllers/Admin/Shop/Images.php`
- [x] **6.8** — `app/Controllers/Admin/Shop/Stock.php` _(static `logAdjustment()` marked `@deprecated`)_
- [x] **6.9** — `app/Controllers/Admin/Shop/Orders.php`
- [x] **6.10** — `app/Controllers/Shop/Categories.php` + `Shop/Products.php`
- [x] **6.11** — `app/Controllers/Shop/CustomerAuth.php`
- [x] **6.12** — `app/Controllers/Shop/Checkout.php` _(delegates gateway URL building to gateway ports)_
- [x] **6.13** — `app/Controllers/Shop/PaymentNotify.php`
- [x] **6.14** — `app/Controllers/Shop/Orders.php`
- [x] **6.15** — `app/Controllers/Contact.php`
- [x] **6.16** — Retire static service classes
  - Deleted `app/Services/InvoicePdf.php`
  - Deleted `app/Services/OrderMailer.php`
  - Deleted `app/Services/LowStockMailer.php`

---

## M7 — Move Http Layer to Infrastructure/Http

> Goal: directory structure enforces layer boundaries. Controllers live in Infrastructure, not alongside domain code.

- [x] **7.1** — Move Filters
  - Created `app/Infrastructure/Http/Filters/AdminAuth.php` (uses `adminSessionRepository->find()`)
  - Created `app/Infrastructure/Http/Filters/Cors.php`
  - Updated `app/Config/Filters.php` alias registrations
  - Deleted `app/Filters/`

- [x] **7.2** — Move BaseController
  - Created `app/Infrastructure/Http/Controllers/BaseController.php`
  - `shopOffline()` now calls `service('settingsRepository')->get('shop_enabled')`
  - Deleted `app/Controllers/BaseController.php`

- [x] **7.3** — Move Admin controllers
  - All 10 admin controller files → `app/Infrastructure/Http/Controllers/Admin/`
  - Namespaces + `use` statements updated
  - `app/Config/Routes.php` updated to FQCN format

- [x] **7.4** — Move Shop controllers
  - All 7 shop controller files → `app/Infrastructure/Http/Controllers/Shop/`
  - `app/Config/Routes.php` shop routes updated

- [x] **7.5** — Move Content + Contact controllers
  - `app/Infrastructure/Http/Controllers/Content/Pages.php` + `Settings.php`
  - `app/Infrastructure/Http/Controllers/Contact.php`
  - `app/Config/Routes.php` updated

- [x] **7.6** — Final cleanup
  - Deleted `app/Controllers/`
  - Deleted `app/Filters/`
  - `php spark routes` — zero errors, all routes resolve to Infrastructure namespace

---

## Decisions Log

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-05-15 | Full migration (option C) chosen | Enforces boundaries for all future development; no partial patterns allowed |
| 2026-05-15 | CI4 `Config\Services` used as service locator | CI4 has no DI container; `service()` helper resolves singletons; acceptable for this framework |
| 2026-05-15 | Old static `Services/` classes kept alive through M6 | Allows controllers to be thinned one at a time without big-bang cutover |
| 2026-05-15 | Controllers not renamed in M6 | Renaming deferred to M7 to keep `Routes.php` stable during the dangerous thinning phase |
| 2026-05-15 | `OrderStatus` as PHP 8.1 backed enum | Encodes domain transitions in domain layer; avoids string literals scattered across handlers |
