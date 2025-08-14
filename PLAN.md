# Woo Rentals Core — Minimal MVP Task List

## 1️⃣ Setup & Bootstrapping

* [x] Create `woo-rentals-core` plugin folder with PSR-4 structure:

  ```
  woo-rentals-core/
  ├─ plugin.php
  ├─ composer.json
  ├─ src/{Plugin.php, Domain, Application, Infrastructure}
  ├─ templates/admin/{requests-list.php, request-detail.php, leases-list.php, lease-detail.php}
  └─ assets/admin/{scripts.js, styles.css}
  ```
* [x] Configure Composer autoload:

  ```json
  {
    "autoload": { "psr-4": { "WRC\\": "src/" } }
  }
  ```
* [x] `Plugin.php` boots REST, Admin, Installer.
* [x] Register activation hook → `Installer::activate()`.

---

## 2️⃣ Database & Migrations

* [x] **Table** `wrc_lease_requests`
  Columns:

  ```
  id BIGINT PK AUTO_INCREMENT
  product_id BIGINT NOT NULL
  variation_id BIGINT NULL
  requester_id BIGINT NOT NULL
  start_date DATE NOT NULL
  end_date DATE NOT NULL
  qty INT NOT NULL DEFAULT 1
  notes TEXT NULL
  status ENUM('pending','approved','declined','cancelled') DEFAULT 'pending'
  created_at DATETIME NOT NULL
  updated_at DATETIME NULL
  ```

  Indexes: `(product_id)`, `(requester_id)`, `(status)`, `(start_date, end_date)`

* [ ] **Table** `wrc_leases`
  Columns:

  ```
  id BIGINT PK AUTO_INCREMENT
  product_id BIGINT NOT NULL
  variation_id BIGINT NULL
  order_id BIGINT NULL
  order_item_id BIGINT NULL
  customer_id BIGINT NOT NULL
  request_id BIGINT NULL
  start_date DATE NOT NULL
  end_date DATE NOT NULL
  qty INT NOT NULL DEFAULT 1
  status ENUM('active','completed','cancelled') DEFAULT 'active'
  created_at DATETIME NOT NULL
  updated_at DATETIME NULL
  ```

  Indexes: `(product_id)`, `(customer_id)`, `(status)`, `(start_date, end_date)`

* [ ] Store schema version in `wrc_db_version`.

* [ ] `Installer` checks version & runs `dbDelta()`.

---

## 3️⃣ Capabilities & Roles

* [ ] Capabilities:

  * `manage_wrc_requests`
  * `manage_wrc_leases`
* [ ] On activation: grant both to `administrator` and `shop_manager`.

---

## 4️⃣ REST API — OpenAPI Specs

```yaml
openapi: 3.0.0
info:
  title: Woo Rentals Core API
  version: 1.0.0
paths:
  /wrc/v1/requests:
    post:
      summary: Create a new lease request
      security: [{ cookieAuth: [] }]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [product_id, start_date, end_date, qty]
              properties:
                product_id: { type: integer }
                variation_id: { type: integer, nullable: true }
                start_date: { type: string, format: date }
                end_date: { type: string, format: date }
                qty: { type: integer, minimum: 1 }
                notes: { type: string }
      responses:
        '201': { description: Lease request created }
    get:
      summary: List lease requests
      parameters:
        - { name: status, in: query, schema: { type: string, enum: [pending, approved, declined, cancelled] } }
        - { name: product_id, in: query, schema: { type: integer } }
        - { name: mine, in: query, schema: { type: boolean } }
        - { name: page, in: query, schema: { type: integer, default: 1 } }
        - { name: per_page, in: query, schema: { type: integer, default: 20 } }
      responses:
        '200': { description: List of lease requests }
  /wrc/v1/requests/{id}:
    get:
      summary: Get a single lease request
      parameters:
        - { name: id, in: path, required: true, schema: { type: integer } }
    post:
      summary: Update lease request status
      parameters:
        - { name: id, in: path, required: true, schema: { type: integer } }
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                action: { type: string, enum: [approve, decline, cancel] }
                note: { type: string }
  /wrc/v1/leases:
    post:
      summary: Create a lease
      security: [{ cookieAuth: [] }]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [product_id, customer_id, start_date, end_date, qty]
              properties:
                product_id: { type: integer }
                variation_id: { type: integer, nullable: true }
                customer_id: { type: integer }
                request_id: { type: integer, nullable: true }
                start_date: { type: string, format: date }
                end_date: { type: string, format: date }
                qty: { type: integer, minimum: 1 }
      responses:
        '201': { description: Lease created }
    get:
      summary: List leases
      parameters:
        - { name: status, in: query, schema: { type: string, enum: [active, completed, cancelled] } }
        - { name: product_id, in: query, schema: { type: integer } }
        - { name: customer_id, in: query, schema: { type: integer } }
        - { name: mine, in: query, schema: { type: boolean } }
  /wrc/v1/leases/{id}:
    get:
      summary: Get a single lease
      parameters:
        - { name: id, in: path, required: true, schema: { type: integer } }
    post:
      summary: Update lease status
      parameters:
        - { name: id, in: path, required: true, schema: { type: integer } }
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                action: { type: string, enum: [complete, cancel] }
```

---

## 5️⃣ Domain & Application Layer

* [ ] **Entities**: `LeaseRequest`, `Lease` (constructors enforce data validity).
* [ ] **Use-cases**:

  * `CreateLeaseRequest`, `ApproveLeaseRequest`, `DeclineLeaseRequest`, `CancelLeaseRequest`
  * `CreateLease`, `CompleteLease`, `CancelLease`
* [ ] **Repositories** for both tables:

  * `insert`, `update_status`, `find_by_id`, `list`.

---

## 6️⃣ Admin UI

* [ ] **Menu**: Top “Rentals” with submenus “Lease Requests” & “Leases”.
* [ ] **Lease Requests List**:

  * Columns: ID, Product, Requester, Period, Qty, Status, Created.
  * Filters: status, product, date range.
  * Row actions: View, Approve, Decline, Cancel.
* [ ] **Lease Request Detail**:

  * Read-only info, notes, status actions.
* [ ] **Leases List**:

  * Columns: ID, Product, Customer, Period, Qty, Status, Created.
  * Row actions: View, Complete, Cancel.
* [ ] **Lease Detail**:

  * Read-only info, status actions.
* [ ] Use `WP_List_Table` for lists, PHP templates for detail pages.
* [ ] Enqueue small JS to call REST endpoints for actions (nonce protected).

---

## 7️⃣ Security & Quality

* [ ] Sanitize all inputs (`sanitize_text_field`, `intval`, etc.).
* [ ] Verify capabilities before mutating actions.
* [ ] Use nonces for all admin JS calls.
* [ ] Structured JSON errors `{ code, message, data? }`.
* [ ] UTC timestamps for `created_at`/`updated_at`.
* [ ] Idempotent status changes.
