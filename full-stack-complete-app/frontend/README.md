# 🍽️ Savoria — Full-Stack Complete App: Frontend

The complete customer-facing site, wired live to the backend API: dynamic menu, shopping cart, checkout, table reservations, and full authentication (login/register/logout) with role-aware navigation.

## 📁 Structure

```
frontend/
├── index.html          # Main site — live menu, cart, checkout, reservations
├── login.html            # Login page
├── register.html          # Registration page
├── forgot-password.html    # Request a password reset link
├── reset-password.html      # Set a new password (consumes ?token= from the email/dev link)
├── my-orders.html             # Logged-in customer's order history
├── track-order.html            # Guest/customer order tracking by order # + phone
├── admin.html                    # Staff/admin panel — manage orders & reservations
├── privacy.html            # Privacy policy
├── terms.html                # Terms of service
├── cookies.html                # Cookie policy
├── css/
│   └── styles.css                # "Ember" design system (light + dark) + cart/auth additions
├── js/
│   ├── api.js                      # Fetch wrapper + token/session management
│   ├── app.js                       # Menu rendering, cart, checkout, reservations, auth nav
│   └── theme.js                      # Dark/light toggle + scroll-to-top
├── assets/
│   ├── logo.svg                    # Monogram + wordmark
│   ├── favicon.svg
│   └── banner.svg
└── requirements.txt
```

## 🌗 Light & Dark Mode

Every page ships with a theme toggle (sun/moon icon in the navbar). The chosen theme is saved to `localStorage` under `savoria_theme`, defaults to the visitor's OS preference on first visit, and is applied via an inline script in `<head>` before first paint — no flash of the wrong theme on load. Brand-dark sections (hero, footer, reservation banner, auth pages) stay charcoal in both themes by design; only page surfaces, cards, and text invert.

## ▶️ Running Locally

1. **Start the backend first** (see `../backend/README.md`):
   ```bash
   cd ../backend
   php -S localhost:8000 index.php
   ```
2. **Serve this frontend:**
   ```bash
   python3 -m http.server 8080
   ```
3. Visit `http://localhost:8080`.

If you run the backend on a different host/port, update `BASE_URL` at the top of `js/api.js`.

## ✨ What's Wired to the Backend

| Feature | API Calls |
|---|---|
| Menu grid | `GET /categories`, `GET /menu-items` |
| Add to cart / checkout | `POST /orders` |
| Reservation form | `POST /reservations` |
| Login | `POST /auth/login` |
| Register | `POST /auth/register` |
| Account dropdown | `GET /auth/me` (via stored token), `POST /auth/logout` |

Session state (JWT + user info) is kept in `localStorage` under `savoria_token` / `savoria_user`, read and written entirely by `js/api.js`.

## 🛒 Cart & Checkout Flow

1. Items are added client-side into an in-memory cart (`js/app.js`).
2. The cart drawer shows running quantities and a live total.
3. On **Place Order**, the cart is sent to `POST /orders` — the backend recalculates every price server-side and returns the authoritative total.
4. If the visitor is logged in, their JWT is automatically attached so the order is linked to their account (visible later via `GET /my/orders`); guests can still check out without an account.

## 🔗 Where This Fits

This is the frontend half of the full-stack build. See `../backend/README.md` for the API it depends on, and the root [README](../../README.md) for how this fits with Projects 1–4.
