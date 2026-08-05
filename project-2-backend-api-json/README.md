# 🛎️ Savoria — Project 2: Backend API (JSON Storage)

A dependency-free **PHP REST API** for browsing the Savoria menu and placing orders — persisted to flat JSON files instead of a database. This project demonstrates clean routing, request validation, and server-side price integrity **before** the suite introduces MySQL in Project 3.

## 📁 Structure

```
project-2-backend-api-json/
├── index.php          # Front controller / router
├── data/
│   ├── menu.json        # Seed menu data (read-only reference)
│   └── orders.json       # Order store (read/write at runtime)
├── lib/
│   ├── helpers.php        # JSON response + file I/O helpers
│   └── validate.php        # Order payload validation
└── README.md
```

## ▶️ Running Locally

```bash
php -S localhost:8000 index.php
```

Ensure `data/orders.json` is writable by the PHP process:

```bash
chmod 664 data/orders.json
```

## 📡 API Reference

| Method | Route | Description |
|---|---|---|
| GET | `/` | API info & route list |
| GET | `/menu` | List menu items — supports `?category=mains` and `?available_only=true` |
| GET | `/menu/{id}` | Get a single menu item |
| GET | `/orders` | List all orders, newest first |
| GET | `/orders/{id}` | Get a single order |
| POST | `/orders` | Create a new order |
| PATCH | `/orders/{id}` | Update an order's status |
| DELETE | `/orders/{id}` | Cancel/delete an order |

### Example — Place an Order

```bash
curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "Ayesha Khan",
    "phone": "03001234567",
    "order_type": "delivery",
    "delivery_address": "12-C Zamzama Boulevard, Karachi",
    "items": [
      { "menu_item_id": 3, "quantity": 1 },
      { "menu_item_id": 7, "quantity": 2 }
    ]
  }'
```

Response:

```json
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "id": 1,
    "customer_name": "Ayesha Khan",
    "order_type": "delivery",
    "items": [
      { "menu_item_id": 3, "name": "Slow-Braised Lamb Shank", "unit_price": 2450, "quantity": 1, "subtotal": 2450 },
      { "menu_item_id": 7, "name": "Rose & Cardamom Cooler", "unit_price": 550, "quantity": 2, "subtotal": 1100 }
    ],
    "total_amount": 3550,
    "status": "pending",
    "created_at": "2026-07-31T10:15:00+00:00"
  }
}
```

## 🔒 Design Notes

- **Prices are never trusted from the client** — every order total is recalculated server-side from `menu.json`.
- **File writes are lock-protected** (`flock`) to reduce corruption risk under concurrent requests — a real limitation of file-based storage that Project 3 solves with a proper database.
- Every response follows a consistent envelope: `{ success, message, data | errors }`.

## 🔗 Where This Fits

This project intentionally avoids a database to demonstrate REST API design in isolation. For persistent, relational storage, see `project-3-database-integration/`. For authentication and roles, see `project-4-auth-authorization/`.
