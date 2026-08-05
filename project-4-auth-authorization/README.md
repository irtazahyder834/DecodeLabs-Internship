# 🛎️ Savoria — Project 4: Authentication & Authorization

A **JWT-based authentication system with role-based access control (RBAC)** for Savoria, supporting three roles — `customer`, `staff`, and `admin` — built with plain PHP and a hand-rolled, dependency-free JWT (HS256) implementation.

## 📁 Structure

```
project-4-auth-authorization/
├── index.php                          # Front controller / router
├── database/
│   └── savoria_project4_schema.sql      # users + refresh_tokens schema, seed accounts
├── config/
│   ├── db.php                            # PDO connection factory
│   └── env.php                            # .env loader
├── lib/
│   ├── helpers.php                        # Response + request helpers
│   ├── validate.php                        # Registration/login validation
│   ├── auth.php                            # Bearer token extraction, require_auth(), require_role()
│   └── jwt.php                             # Minimal HS256 JWT encode/decode
├── .env.example
└── README.md
```

## ⚙️ Setup

```bash
mysql -u root -p < database/savoria_project4_schema.sql
cp .env.example .env
# edit .env — set a long, random JWT_SECRET before using this beyond local dev
php -S localhost:8000 index.php
```

Seed accounts (password for all three: `Password123!`):

| Role | Email |
|---|---|
| admin | admin@savoria.example |
| staff | staff@savoria.example |
| customer | ayesha@savoria.example |

## 📡 API Reference

| Method | Route | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | — | Create a customer account |
| POST | `/auth/login` | — | Authenticate, receive a JWT |
| GET | `/auth/me` | any role | Get the current authenticated user |
| POST | `/auth/logout` | any role | Client-side token discard instruction |
| GET | `/staff/dashboard` | staff, admin | Staff-only demo route |
| GET | `/admin/users` | admin | List all users |
| PATCH | `/admin/users/{id}/role` | admin | Change a user's role |

### Example — Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@savoria.example","password":"Password123!"}'
```

### Example — Calling a Protected Route

```bash
curl http://localhost:8000/admin/users \
  -H "Authorization: Bearer <token from login>"
```

## 🔐 Security Design

- **Passwords** are hashed with `password_hash()` (bcrypt) — never stored or logged in plaintext.
- **JWTs are signed with HMAC-SHA256** and verified with `hash_equals()` to prevent timing attacks on signature comparison.
- **Generic login errors** — "Invalid email or password" is returned identically whether the email doesn't exist or the password is wrong, to avoid user enumeration.
- **Role escalation is admin-gated** — public registration always creates a `customer`; only an authenticated admin can promote a user via `PATCH /admin/users/{id}/role`.
- **Self-demotion guard** — an admin cannot accidentally strip their own admin role through the API.
- **`.env`-based secrets** — `JWT_SECRET` and DB credentials are never committed to source control.

### Note on the hand-rolled JWT

This project implements JWT encode/decode manually to demonstrate the underlying mechanics (header/payload/signature, base64url encoding, HMAC verification, expiry checks). For production systems, prefer a maintained, audited library such as `firebase/php-jwt`.

## 🔗 Where This Fits

This project demonstrates auth in isolation, without the menu/order domain from Projects 2–3. Everything — menu, orders, **and** auth/RBAC — is combined into one deployable application in `full-stack-complete-app/`.
