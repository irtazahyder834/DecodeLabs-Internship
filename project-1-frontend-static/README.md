# 🛎️ Savoria — Project 1: Static Frontend

A fully responsive, animated restaurant landing page for **Savoria**, a fine-dining and food-ordering brand. Built with pure **HTML5, CSS3, and vanilla JavaScript** — no frameworks, no build step.

This project is the visual/UX foundation of the wider Savoria full-stack suite (see the root [README](../README.md) for how it fits with Projects 2–4 and the combined app).

## ✨ Features

- **Light & dark mode** — toggle button in the navbar, persisted in `localStorage`, respects the visitor's OS preference on first visit, and applies before first paint (no flash of the wrong theme)
- **Professional brand identity** — monogram logo (crossed fork & knife inside a ring) + wordmark, matching favicon
- **Responsive navbar** with a hover dropdown under "Menu" (categories preview), scroll-aware background, and mobile hamburger menu
- **Hero section** with layered gradients and live stats
- **About section** with an offset image collage
- **Filterable menu grid** (Starters / Mains / Desserts / Beverages) rendered dynamically from a JS dataset, with scroll-in animation
- **Reservation form** with client-side validation (required fields, date/time, party size, min-date guard)
- **Auto-advancing testimonial carousel** with clickable dot navigation
- **Contact form** with field-level validation (name, email, subject, message)
- **Newsletter signup** micro-interaction
- **Scroll-to-top button** that appears after scrolling past the hero
- **Legal pages**: Privacy Policy, Terms of Service, Cookie Policy
- **Scroll-reveal animations** via `IntersectionObserver`

## 📁 Structure

```
project-1-frontend-static/
├── index.html          # Main landing page
├── privacy.html         # Privacy policy
├── terms.html            # Terms of service
├── cookies.html          # Cookie policy
├── css/
│   └── styles.css        # Full design system ("Ember" theme, light + dark)
├── js/
│   ├── main.js            # Menu rendering, filters, forms, carousel, nav
│   └── theme.js             # Dark/light toggle + scroll-to-top
├── assets/
│   ├── logo.svg            # Monogram + wordmark
│   ├── favicon.svg
│   └── banner.svg
└── requirements.txt
```

## 🎨 Design System — "Ember"

| Token | Light mode | Dark mode | Use |
|---|---|---|---|
| `--bg-page` | `#FBF6EE` | `#15120F` | Page background |
| `--bg-surface` | `#FFFFFF` | `#211C17` | Cards, forms |
| `--text-primary` | `#2C2621` | `#F3EDE1` | Headings, body text |
| `--color-terracotta` | `#C4602E` | `#C4602E` | Primary actions, accents |
| `--color-gold` | `#D9A441` | `#E8B959` | Highlights, badges |

Brand-dark sections (hero, navbar, footer, reservation banner) stay a consistent charcoal in both themes by design — only the page surfaces, cards, and text invert. The toggle lives in `js/theme.js` and writes to `localStorage` under `savoria_theme`; an inline script in `<head>` reads it before first paint to avoid a flash of the wrong theme.

Display font: Playfair Display · Body font: Inter.

## ▶️ Running Locally

No dependencies needed. Any static server works:

```bash
python3 -m http.server 8080
# visit http://localhost:8080
```

Or simply double-click `index.html` to open it directly in a browser.

## 🔗 Where This Fits

This static build has **no backend calls** — reservation and contact forms simulate submission client-side only. For live persistence, see:

- `project-2-backend-api-json/` — JSON-file-backed REST API
- `project-3-database-integration/` — MySQL + PDO CRUD
- `project-4-auth-authorization/` — JWT auth + role-based access
- `full-stack-complete-app/` — everything combined into one working app
