# 🍽️ Savoria — Full-Stack Complete App: Backend

The combined PHP + MySQL (PDO) API powering the complete Savoria application — menu browsing, ordering, table reservations, and JWT authentication with role-based access control, all in one router.

## 📁 Structure

```
backend/
├── index.php              # Front controller / router (all routes)
├── test_db.php              # Quick connectivity + row-count check
├── config/
│   ├── db.php                # PDO connection factory
│   └── env.php                 # .env loader
├── lib/
│   ├── helpers.php              # Response + request helpers
│   ├── validate.php              # All payload validation
│   ├── auth.php                    # Bearer token extraction, require_auth(), require_role()
│   └── jwt.php                      # Minimal HS256 JWT encode/decode
├── .env.example
└── README.md
```

## ⚙️ Setup

```bash
mysql -u root -p < ../database/savoria_full_schema.sql
cp .env.example .env
# edit .env with your DB credentials and a strong JWT_SECRET
php -S localhost:8000 index.php
```

Verify the connection:

```bash
php test_db.php
```

Seed accounts (password for all: `Password123!`):

| Role | Email |
|---|---|
| admin | admin@savoria.example |
| staff | staff@savoria.example |
| customer | ayesha@savoria.example |

## 📡 Full API Reference

### Public

| Method | Route | Description |
|---|---|---|
| GET | `/categories` | List categories |
| GET | `/menu-items` | List menu items — `?category_id=`, `?available_only=true` |
| GET | `/menu-items/{id}` | Get a single menu item |
| POST | `/auth/register` | Create a customer account |
| POST | `/auth/login` | Authenticate, receive a JWT |
| POST | `/auth/forgot-password` | Request a reset token (returned in-response when `APP_DEBUG=true`, since no email service is wired up) |
| POST | `/auth/reset-password` | Reset password using a token |
| POST | `/reservations` | Submit a table reservation (guest or logged-in) — `409` if the slot is fully booked |
| POST | `/orders` | Place an order (guest or logged-in — token optional) |
| GET | `/orders/{id}` | Look up a single order — guests must pass `?phone=` matching the order; owners/staff/admin can use their token instead |

### Authenticated (any role)

| Method | Route | Description |
|---|---|---|
| GET | `/auth/me` | Get the current user |
| POST | `/auth/change-password` | Change password while logged in |
| POST | `/auth/logout` | Client-side token discard instruction |
| GET | `/my/orders` | The logged-in customer's own order history |

### Staff & Admin

| Method | Route | Description |
|---|---|---|
| GET | `/orders` | List all orders |
| PATCH | `/orders/{id}` | Update order status |
| GET | `/reservations` | List all reservations |
| PATCH | `/reservations/{id}` | Update reservation status |
| GET | `/admin/summary` | Today's order count/revenue + pending order/reservation counts |

### Admin only

| Method | Route | Description |
|---|---|---|
| POST | `/menu-items` | Create a menu item |
| PUT | `/menu-items/{id}` | Update a menu item |
| DELETE | `/menu-items/{id}` | Delete a menu item |
| GET | `/admin/users` | List all users |
| PATCH | `/admin/users/{id}/role` | Change a user's role |

## 🔐 Design Notes

- **Guest checkout supported** — `POST /orders` and `POST /reservations` accept an *optional* bearer token via `get_optional_user()`. If present and valid, the order/reservation is linked to that account; if absent, it's still accepted as a guest submission.
- **Prices always computed server-side** from the current `menu_items` table inside a transaction with row locking (`FOR UPDATE`), never trusted from the client.
- **RBAC enforced per-route** with `require_auth()` + `require_role()`, mirroring the pattern from Project 4.
- **Consistent response envelope**: `{ success, message, data | errors }` across every route.

## 🔗 Where This Fits

This is the backend half of the full-stack build. The matching frontend lives in `../frontend/`, wired up via `js/api.js` for authenticated fetch calls and `js/app.js` for cart/order/reservation logic.
