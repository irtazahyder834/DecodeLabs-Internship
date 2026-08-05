# 🛎️ Savoria — Project 3: Database Integration

A **PHP + MySQL (PDO)** REST API layer for Savoria, replacing Project 2's JSON files with a proper relational schema — categories, menu items, orders, and order line items — all accessed through **prepared statements** and a **transactional order-creation flow**.

## 📁 Structure

```
project-3-database-integration/
├── index.php                          # Front controller / router
├── database/
│   └── savoria_project3_schema.sql      # Schema + seed data
├── config/
│   ├── db.php                            # PDO connection factory
│   └── env.php                            # .env loader
├── lib/
│   ├── helpers.php                        # Response + request helpers
│   └── validate.php                        # Payload validation
├── .env.example
└── README.md
```

## 🗄️ Schema

| Table | Purpose |
|---|---|
| `categories` | Menu sections (Starters, Mains, Desserts, Beverages) |
| `menu_items` | Dishes, priced, linked to a category via `FOREIGN KEY` |
| `orders` | Customer orders with type, status, and computed total |
| `order_items` | Line items per order, with a price **snapshot** at order time |

Full DDL, constraints, indexes, and seed data live in [`database/savoria_project3_schema.sql`](database/savoria_project3_schema.sql).

## ⚙️ Setup

1. **Create the database:**
   ```bash
   mysql -u root -p < database/savoria_project3_schema.sql
   ```
2. **Configure environment:**
   ```bash
   cp .env.example .env
   # edit .env with your DB credentials
   ```
3. **Run the server:**
   ```bash
   php -S localhost:8000 index.php
   ```

## 📡 API Reference

| Method | Route | Description |
|---|---|---|
| GET | `/categories` | List all categories |
| GET | `/menu-items` | List menu items — `?category_id=`, `?available_only=true` |
| GET | `/menu-items/{id}` | Get a single menu item |
| POST | `/menu-items` | Create a menu item |
| PUT | `/menu-items/{id}` | Update a menu item (partial updates supported) |
| DELETE | `/menu-items/{id}` | Delete a menu item |
| GET | `/orders` | List all orders |
| GET | `/orders/{id}` | Get an order with its line items |
| POST | `/orders` | Create an order (transactional) |
| PATCH | `/orders/{id}` | Update order status |

## 🔐 Engineering Highlights

- **100% prepared statements** — no raw string interpolation into SQL anywhere in the codebase, preventing SQL injection.
- **Transactional order creation** — `POST /orders` wraps the order + all order_items inserts in `beginTransaction()` / `commit()` / `rollBack()`, so a failure partway through never leaves an incomplete order.
- **Row locking on order creation** — menu items are read with `SELECT ... FOR UPDATE` inside the transaction to avoid selling an item that just became unavailable.
- **Price integrity** — order totals are always computed from the current database price, never from client input.
- **Foreign key protection** — deleting a menu item that's referenced by existing orders is blocked (`409 Conflict`) rather than silently corrupting order history.
- **`.env`-based configuration** — no credentials committed to source control.

## 🔗 Where This Fits

This project has no authentication — anyone can call any endpoint. For login, JWT sessions, and role-based access control, see `project-4-auth-authorization/`. For everything combined into a single deployable app with a matching frontend, see `full-stack-complete-app/`.
