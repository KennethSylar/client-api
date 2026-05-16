# Shop API (E-commerce Backend)

You are working on the e-commerce API in `client-api/`. This covers products, categories, orders, checkout, payment notify, customer auth, reviews, and stock.

## Working Directory Rule
All paths are relative to `client-api/`. Never touch `client-site/`.

## Route Map
```php
// Public shop routes (no filter)
GET  shop/categories
GET  shop/products
GET  shop/products/:slug
POST shop/cart/validate               ← Stock check before checkout
POST shop/checkout
POST shop/payment/payfast/notify
POST shop/payment/ozow/notify
GET  shop/orders/:token               ← Public order status (by token only — no ID!)

// Customer account (customerauth filter)
POST shop/account/register
POST shop/account/login
POST shop/account/logout
GET  shop/account/me
PUT  shop/account/me
GET  shop/account/orders
POST shop/account/orders/:token/cancel
POST shop/account/orders/:token/refund-request
GET  shop/account/addresses
POST shop/account/addresses
PUT  shop/account/addresses/:id
DEL  shop/account/addresses/:id

// Shop reviews (mixed)
GET  shop/products/:id/reviews        ← Public (approved only)
POST shop/products/:id/reviews        ← customerauth required

// Admin shop routes (adminauth filter)
GET    admin/shop/products
GET    admin/shop/products/export     ← CSV export
POST   admin/shop/products/import     ← CSV import
POST   admin/shop/products
GET    admin/shop/products/:id
PUT    admin/shop/products/:id
DELETE admin/shop/products/:id
POST   admin/shop/products/:id/images
DELETE admin/shop/products/:id/images/:imageId
PUT    admin/shop/products/:id/images/reorder
POST   admin/shop/products/:id/stock
GET    admin/shop/products/:id/stock  ← Stock history

GET    admin/shop/categories
POST   admin/shop/categories
PUT    admin/shop/categories/:id
DELETE admin/shop/categories/:id
PUT    admin/shop/categories/reorder

GET    admin/shop/orders
GET    admin/shop/orders/:id
PUT    admin/shop/orders/:id/status
POST   admin/shop/orders/:id/refund
POST   admin/shop/orders/:id/partial-refund
GET    admin/shop/orders/:id/invoice  ← PDF

GET    admin/shop/reviews
PUT    admin/shop/reviews/:id         ← approve/reject
```

## Application Layer — Shop Domain
```
Application/Shop/
  Commands/
    CreateProductCommand, UpdateProductCommand, DeleteProductCommand
    AddProductImageCommand, DeleteProductImageCommand, ReorderProductImagesCommand
    CreateCategoryCommand, UpdateCategoryCommand, DeleteCategoryCommand, ReorderCategoriesCommand
    AdjustStockCommand
    SubmitReviewCommand, ModerateReviewCommand
  Handlers/
    CreateProductHandler, UpdateProductHandler, DeleteProductHandler
    GetProductHandler, ListProductsHandler
    AddProductImageHandler, DeleteProductImageHandler, ReorderProductImagesHandler
    CreateCategoryHandler, UpdateCategoryHandler, DeleteCategoryHandler
    ListCategoriesHandler, ReorderCategoriesHandler
    AdjustStockHandler, GetStockHistoryHandler
    SubmitReviewHandler, ModerateReviewHandler, ListReviewsHandler

Application/Orders/
  Commands/
    PlaceOrderCommand, RecordPaymentCommand
    RegisterCustomerCommand, LoginCustomerCommand, LogoutCustomerCommand
    UpdateCustomerCommand
    CancelOrderCommand, RefundOrderCommand, PartialRefundCommand
    UpdateOrderStatusCommand
  Handlers/
    PlaceOrderHandler, RecordPaymentHandler
    RegisterCustomerHandler, LoginCustomerHandler, LogoutCustomerHandler
    GetCustomerOrdersHandler, GetOrderHandler, GetOrderInvoiceHandler, ListOrdersHandler
    UpdateCustomerHandler, CancelOrderHandler, RefundOrderHandler, PartialRefundHandler
    UpdateOrderStatusHandler
```

## Repository Interfaces — Shop Domain
```
Domain/Shop/
  ProductRepositoryInterface        ← findBySlug, findById, list, create, update, delete
  CategoryRepositoryInterface       ← list, create, update, delete, reorder
  ReviewRepositoryInterface         ← list (approved), listAdmin, submit, moderate, findByOrderItem
  StockRepositoryInterface          ← adjust, getHistory, getCurrentStock

Domain/Orders/
  OrderRepositoryInterface          ← create, findByToken, findById, list, updateStatus
  CustomerRepositoryInterface       ← create, findByEmail, findById, update
  CustomerAddressRepositoryInterface← list, create, update, delete, setDefault
```

## Checkout Flow (backend)
```
POST /shop/checkout
  1. Validate cart items against live stock (CartValidation logic)
  2. PlaceOrderCommand → PlaceOrderHandler
     - Creates shop_order + order_items rows
     - Decrements stock (if track_stock)
     - Returns order_token (alphanum, public-safe) + order_id (internal)
  3. Initialise gateway (PayFast or Ozow) with order details
  4. Returns { order_token, payment_url, gateway }

POST /shop/payment/payfast/notify (or ozow/notify)
  1. Rate limit: 30/min/IP
  2. Validate ITN/signature
  3. Find order by payment reference
  4. Idempotency: if already paid with same reference → 200 OK (no double-process)
  5. Order not found → 400 'Invalid notification' (not 404 — prevents enumeration)
  6. RecordPaymentCommand → marks paid, sends confirmation email
```

## Payment Gateways
```php
// Test mode — MUST be explicit string comparison
env('PAYFAST_TEST', 'false') === 'true'   // PayFastGateway.php
env('OZOW_TEST', 'false') === 'true'      // OzowGateway.php
// Never: env('PAYFAST_TEST', true) !== false  ← wrong, always truthy
```

## Stock Tracking
Products have `track_stock` (boolean) and `stock_qty`. Variants have their own `track_stock` and `stock_qty`.
- `CartValidation` checks live stock before checkout (call POST /shop/cart/validate)
- `PlaceOrderHandler` decrements stock atomically
- `AdjustStockHandler` records manual adjustments with reason + admin user

## Reviews — Verified Purchasers Only
```php
// SubmitReviewHandler checks:
// 1. Customer is authenticated (customerauth filter)
// 2. Customer has a completed order containing this product
// 3. Customer hasn't already reviewed this product
// Frontend hides the form if not a verified purchaser
```

## Customer Auth — Current State
Customer auth uses **Bearer token** in `Authorization` header + `customer_sessions` DB table.
```php
// CustomerAuthFilter reads:
$token = substr($request->getHeaderLine('Authorization'), 7);
```
RBAC Epic A will migrate this to httpOnly cookie. Until then, keep Bearer header approach.

## Rate Limits Applied
| Endpoint | Limit |
|----------|-------|
| `POST /shop/account/register` | 10 registrations/hr/IP |
| `POST /shop/account/login` | 20/IP/15min + 10/email/15min |
| `POST /shop/payment/*/notify` | 30/min/IP |

## Admin: CSV Import/Export
`GET /admin/shop/products/export` — returns CSV of all products.
`POST /admin/shop/products/import` — bulk create/update from CSV.
CSV columns: `name, slug, price, description, category_slug, track_stock, stock_qty, vat_exempt`

## Common Pitfalls
- **Order by token, not ID** — public `/shop/orders/:token` must never expose the numeric ID
- **Partial refund** — separate from full refund; `order_refunds` table; `partial_refund` status
- **Stock decrement is in PlaceOrderHandler** — do not duplicate in PaymentNotify
- **Review findByOrderItem** — check `order_items` join to verify purchase before allowing submit
- **Invoice PDF** — served directly from `GetOrderInvoiceHandler`, streamed as `application/pdf`
- **Sensitive shop settings** — PayFast/Ozow keys masked as `••••••••` in Settings GET response
