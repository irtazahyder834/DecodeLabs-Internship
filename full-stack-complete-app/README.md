# 🍽️ Savoria — Full-Stack Complete App

The complete, deployable Savoria application — combining the static frontend (Project 1), the ordering domain and MySQL integration (Project 3), and JWT authentication with RBAC (Project 4) into a single working restaurant platform.

## 📁 Structure

```
full-stack-complete-app/
├── database/
│   └── savoria_full_schema.sql   # Combined schema: users, menu, orders, reservations
├── backend/                       # PHP + MySQL (PDO) REST API — see backend/README.md
└── frontend/                      # Live site: menu, cart, checkout, reservations, auth — see frontend/README.md
```

## ⚙️ Quick Start

```bash
# 1. Create and seed the database
mysql -u root -p < database/savoria_full_schema.sql

# 2. Start the backend API
cd backend
cp .env.example .env   # edit with your DB credentials + a strong JWT_SECRET
php -S localhost:8000 index.php

# 3. In a new terminal, serve the frontend
cd ../frontend
python3 -m http.server 8080
```

Visit **http://localhost:8080**.

Demo accounts (password `Password123!` for all): `admin@savoria.example`, `staff@savoria.example`, `ayesha@savoria.example`.

## 🧭 What You Can Do

- Browse the live menu, filter by category, add dishes to a cart
- Check out as a guest or while logged in — orders are priced and validated server-side
- Book a table through the reservation form
- Register/log in and see an auth-aware navbar (account menu + role badge)
- As `staff`/`admin`: manage order status and reservations via the API
- As `admin`: create/edit/delete menu items and change user roles via the API

## 🔗 How This Relates to Projects 1–4

| Project | What it demonstrates | Present here as |
|---|---|---|
| 1 — Static Frontend | HTML/CSS/JS fundamentals, responsive design | The visual foundation of `frontend/` |
| 2 — JSON API | REST routing without a database | (superseded by Project 3's approach) |
| 3 — Database Integration | MySQL + PDO, transactions, prepared statements | Core of `backend/index.php` |
| 4 — Auth & Authorization | JWT, RBAC, bcrypt | Auth routes + middleware in `backend/` |

Each numbered project remains a standalone, focused submission; this folder shows how they come together in a single production-style app.
