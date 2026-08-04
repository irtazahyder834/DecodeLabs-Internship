/* =========================================================
   Savoria — Full-Stack Complete App
   js/app.js — menu rendering, cart, checkout, reservations,
               auth-aware navigation. Depends on js/api.js.
   ========================================================= */

(function () {
  'use strict';

  let MENU_ITEMS = [];
  let CART = []; // [{ menu_item_id, name, price, image_url, quantity }]
  let APPLIED_PROMO = null; // { code, type, value }

  const formatPKR = (amount) => 'Rs. ' + Number(amount).toLocaleString('en-PK');

  /* Dishes highlighted on the menu — a real restaurant usually calls out
     its best-sellers and chef picks rather than showing everything flat. */
  const FEATURED_DISHES = {
    'Slow-Braised Lamb Shank': { label: "Chef's Special", cls: 'badge-chef' },
    'Charcoal Grilled Salmon': { label: 'Popular', cls: 'badge-popular' },
    'Truffle Mushroom Arancini': { label: "Chef's Special", cls: 'badge-chef' },
    'Dark Chocolate Fondant': { label: 'Popular', cls: 'badge-popular' },
  };

  /* Promo codes — mirrors backend/index.php PROMO_CODES so the cart can
     show a live preview; the server always recalculates authoritatively. */
  const PROMO_CODES = {
    SAVORIA10: { type: 'percent', value: 10, label: '10% off' },
    WELCOME100: { type: 'flat', value: 100, label: 'Rs. 100 off' },
  };

  const WHATSAPP_NUMBER = '923001112233'; // restaurant order line

  /* ---------- Menu loading (API, with graceful fallback) ---------- */
  async function loadMenu() {
    const grid = document.getElementById('menuGrid');
    if (!grid) return;

    try {
      MENU_ITEMS = await SavoriaAPI.getMenuItems();
    } catch (err) {
      grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:var(--color-text-muted);">
        Couldn't load the live menu (${err.message}). Make sure the backend is running — see backend/README.md.
      </p>`;
      return;
    }
    renderMenu('all');
  }

  function spiceDots(level) {
    let html = '';
    for (let i = 0; i < 3; i++) html += `<span class="${i < level ? 'filled' : ''}"></span>`;
    return html;
  }

  function renderMenu(filter) {
    const grid = document.getElementById('menuGrid');
    if (!grid) return;
    const items = filter === 'all' ? MENU_ITEMS : MENU_ITEMS.filter(i => i.category_name?.toLowerCase() === filter);

    if (!items.length) {
      grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:var(--color-text-muted);">No dishes in this category right now.</p>`;
      return;
    }

    grid.innerHTML = items.map(item => {
      const featured = FEATURED_DISHES[item.name];
      const badge = !item.is_available
        ? '<span class="menu-card-badge" style="background:var(--color-text-muted);">Sold Out</span>'
        : featured
          ? `<span class="menu-card-badge ${featured.cls}">${featured.label}</span>`
          : '';
      return `
      <article class="menu-card is-visible" data-category="${(item.category_name || '').toLowerCase()}">
        <div class="menu-card-media">
          <img src="${item.image_url}" alt="${item.name}" loading="lazy">
          ${badge}
        </div>
        <div class="menu-card-body">
          <div class="menu-card-top">
            <h3>${item.name}</h3>
            <span class="menu-card-price">${formatPKR(item.price)}</span>
          </div>
          <p>${item.description}</p>
          <div class="menu-card-meta">
            <div class="spice-level" title="Spice level">${spiceDots(item.spice_level)}</div>
            <span style="font-size:0.78rem;color:var(--color-text-muted);">${item.category_name || ''}</span>
          </div>
        </div>
        <div class="menu-card-footer">
          <button class="add-to-cart-btn" data-id="${item.id}" ${item.is_available ? '' : 'disabled'}>
            ${item.is_available ? 'Add to Cart' : 'Currently Unavailable'}
          </button>
        </div>
      </article>
    `;
    }).join('');

    grid.querySelectorAll('.add-to-cart-btn').forEach(btn => {
      btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id, 10)));
    });
  }

  function initMenuFilters() {
    const buttons = document.querySelectorAll('.menu-filter-btn');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderMenu(btn.dataset.filter);
      });
    });
  }

  /* ---------- Cart ---------- */
  function addToCart(menuItemId) {
    const item = MENU_ITEMS.find(i => i.id === menuItemId);
    if (!item) return;

    const existing = CART.find(c => c.menu_item_id === menuItemId);
    if (existing) {
      existing.quantity += 1;
    } else {
      CART.push({
        menu_item_id: item.id, name: item.name, price: parseFloat(item.price),
        image_url: item.image_url, quantity: 1,
      });
    }
    renderCart();
    openCart();
  }

  function updateQty(menuItemId, delta) {
    const line = CART.find(c => c.menu_item_id === menuItemId);
    if (!line) return;
    line.quantity += delta;
    if (line.quantity <= 0) CART = CART.filter(c => c.menu_item_id !== menuItemId);
    renderCart();
  }

  function removeLine(menuItemId) {
    CART = CART.filter(c => c.menu_item_id !== menuItemId);
    renderCart();
  }

  function cartTotal() {
    return CART.reduce((sum, l) => sum + l.price * l.quantity, 0);
  }

  function discountAmount() {
    if (!APPLIED_PROMO) return 0;
    const subtotal = cartTotal();
    const raw = APPLIED_PROMO.type === 'percent' ? subtotal * (APPLIED_PROMO.value / 100) : APPLIED_PROMO.value;
    return Math.min(Math.round(raw * 100) / 100, subtotal);
  }

  function buildWhatsAppLink() {
    if (!CART.length) return `https://wa.me/${WHATSAPP_NUMBER}`;
    const lines = CART.map(l => `- ${l.name} x${l.quantity} (${formatPKR(l.price * l.quantity)})`).join('\n');
    const total = formatPKR(cartTotal() - discountAmount());
    const msg = `Hi Savoria! I'd like to order:\n${lines}\n\nTotal: ${total}${APPLIED_PROMO ? ` (promo ${APPLIED_PROMO.code} applied)` : ''}`;
    return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(msg)}`;
  }

  /* Estimated ready time — based on the slowest dish in the cart plus a
     small buffer per extra item, like a real kitchen queue. */
  function renderEstimate() {
    const el = document.getElementById('cartEstimate');
    if (!el) return;
    if (!CART.length) { el.style.display = 'none'; return; }

    const prepTimes = CART.map(l => {
      const menuItem = MENU_ITEMS.find(i => i.id === l.menu_item_id);
      return menuItem ? Number(menuItem.prep_time_minutes) || 15 : 15;
    });
    const slowest = Math.max(...prepTimes, 15);
    const extraItems = CART.reduce((sum, l) => sum + l.quantity, 0) - 1;
    const low = slowest + Math.max(extraItems, 0) * 2;
    const high = low + 10;

    el.style.display = 'flex';
    el.innerHTML = `⏱️ Estimated ready in <strong>${low}–${high} min</strong>`;
  }

  function renderCart() {
    const countEl = document.getElementById('cartCount');
    const bodyEl = document.getElementById('cartBody');
    const totalEl = document.getElementById('cartTotal');
    if (!bodyEl) return;

    const count = CART.reduce((sum, l) => sum + l.quantity, 0);
    if (countEl) countEl.textContent = count;

    if (!CART.length) {
      bodyEl.innerHTML = `<div class="cart-empty">Your cart is empty.<br>Browse the menu to add something delicious.</div>`;
      APPLIED_PROMO = null;
    } else {
      bodyEl.innerHTML = CART.map(l => `
        <div class="cart-line">
          <img src="${l.image_url}" alt="${l.name}">
          <div class="cart-line-info">
            <h4>${l.name}</h4>
            <div class="cart-line-price">${formatPKR(l.price * l.quantity)}</div>
            <div class="cart-line-qty">
              <button data-action="dec" data-id="${l.menu_item_id}">−</button>
              <span>${l.quantity}</span>
              <button data-action="inc" data-id="${l.menu_item_id}">+</button>
            </div>
            <div class="cart-line-remove" data-action="remove" data-id="${l.menu_item_id}">Remove</div>
          </div>
        </div>
      `).join('');

      bodyEl.querySelectorAll('[data-action="inc"]').forEach(b => b.addEventListener('click', () => updateQty(parseInt(b.dataset.id, 10), 1)));
      bodyEl.querySelectorAll('[data-action="dec"]').forEach(b => b.addEventListener('click', () => updateQty(parseInt(b.dataset.id, 10), -1)));
      bodyEl.querySelectorAll('[data-action="remove"]').forEach(b => b.addEventListener('click', () => removeLine(parseInt(b.dataset.id, 10))));
    }

    const subtotal = cartTotal();
    const discount = discountAmount();
    const subtotalRow = document.getElementById('cartSubtotalRow');
    const discountRow = document.getElementById('cartDiscountRow');
    if (discount > 0) {
      subtotalRow.style.display = 'flex';
      discountRow.style.display = 'flex';
      document.getElementById('cartSubtotal').textContent = formatPKR(subtotal);
      document.getElementById('cartDiscount').textContent = '− ' + formatPKR(discount);
    } else {
      subtotalRow.style.display = 'none';
      discountRow.style.display = 'none';
    }

    if (totalEl) totalEl.textContent = formatPKR(subtotal - discount);
    renderEstimate();

    const waBtn = document.getElementById('whatsappOrderBtn');
    if (waBtn) waBtn.href = buildWhatsAppLink();
  }

  /* ---------- Promo code ---------- */
  function initPromo() {
    const input = document.getElementById('promoInput');
    const applyBtn = document.getElementById('promoApplyBtn');
    const msgEl = document.getElementById('promoMsg');
    if (!input || !applyBtn) return;

    applyBtn.addEventListener('click', () => {
      const code = input.value.trim().toUpperCase();
      if (!code) return;
      const promo = PROMO_CODES[code];
      if (!promo) {
        APPLIED_PROMO = null;
        msgEl.className = 'promo-msg error';
        msgEl.textContent = 'That promo code is not valid.';
        renderCart();
        return;
      }
      APPLIED_PROMO = { code, ...promo };
      msgEl.className = 'promo-msg success';
      msgEl.textContent = `Promo "${code}" applied — ${promo.label}.`;
      renderCart();
    });
  }

  function openCart() {
    document.getElementById('cartDrawer')?.classList.add('open');
    document.getElementById('cartOverlay')?.classList.add('open');
  }
  function closeCart() {
    document.getElementById('cartDrawer')?.classList.remove('open');
    document.getElementById('cartOverlay')?.classList.remove('open');
  }

  function initCart() {
    document.getElementById('cartTrigger')?.addEventListener('click', openCart);
    document.getElementById('cartClose')?.addEventListener('click', closeCart);
    document.getElementById('cartOverlay')?.addEventListener('click', closeCart);
    renderCart();
    initPromo();
  }

  /* ---------- Checkout ---------- */
  function initCheckout() {
    const form = document.getElementById('checkoutForm');
    if (!form) return;

    const orderTypeSelect = document.getElementById('checkoutOrderType');
    const addressGroup = document.getElementById('checkoutAddressGroup');
    const tableGroup = document.getElementById('checkoutTableGroup');
    const addressInput = document.getElementById('checkoutAddress');

    function syncOrderTypeFields() {
      const type = orderTypeSelect.value;
      addressGroup.style.display = type === 'delivery' ? 'block' : 'none';
      tableGroup.style.display = type === 'dine_in' ? 'block' : 'none';
      addressInput.required = type === 'delivery';
    }
    orderTypeSelect?.addEventListener('change', syncOrderTypeFields);
    syncOrderTypeFields();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!CART.length) return;

      const statusEl = document.getElementById('checkoutStatus');
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Placing order...';

      const notes = document.getElementById('checkoutNotes').value.trim();
      const tableValue = document.getElementById('checkoutTable').value;

      const payload = {
        customer_name: document.getElementById('checkoutName').value.trim(),
        phone: document.getElementById('checkoutPhone').value.trim(),
        order_type: orderTypeSelect.value,
        delivery_address: document.getElementById('checkoutAddress').value.trim(),
        table_number: orderTypeSelect.value === 'dine_in' && tableValue ? parseInt(tableValue, 10) : null,
        promo_code: APPLIED_PROMO ? APPLIED_PROMO.code : undefined,
        items: CART.map(l => ({
          menu_item_id: l.menu_item_id, quantity: l.quantity,
          special_instructions: notes || undefined,
        })),
      };

      try {
        const order = await SavoriaAPI.createOrder(payload);
        statusEl.className = 'auth-alert success show';
        statusEl.textContent = `Order #${order.id} placed — total ${formatPKR(order.total_amount)}. We'll be in touch shortly.`;
        CART = [];
        APPLIED_PROMO = null;
        const promoMsg = document.getElementById('promoMsg');
        if (promoMsg) { promoMsg.textContent = ''; promoMsg.className = 'promo-msg'; }
        const promoInput = document.getElementById('promoInput');
        if (promoInput) promoInput.value = '';
        renderCart();
        form.reset();
        syncOrderTypeFields();
      } catch (err) {
        statusEl.className = 'auth-alert error show';
        statusEl.textContent = err.message;
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Place Order';
      }
    });
  }

  /* ---------- Reservation form (wired to API) ---------- */
  function initReservationForm() {
    const form = document.getElementById('reservationForm');
    const success = document.getElementById('reservationSuccess');
    if (!form) return;

    const dateInput = document.getElementById('resDate');
    if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      const payload = {
        full_name: document.getElementById('resName').value.trim(),
        phone: document.getElementById('resPhone').value.trim(),
        reservation_date: document.getElementById('resDate').value,
        reservation_time: document.getElementById('resTime').value,
        party_size: document.getElementById('resGuests').value,
        notes: document.getElementById('resNotes').value.trim(),
      };

      try {
        await SavoriaAPI.createReservation(payload);
        success.textContent = 'Thank you! Your reservation request has been received.';
        success.classList.add('show');
        form.reset();
      } catch (err) {
        success.className = 'form-success-msg show';
        success.style.background = 'rgba(179,38,30,0.1)';
        success.style.color = 'var(--color-error)';
        success.textContent = err.message;
      } finally {
        submitBtn.disabled = false;
        setTimeout(() => success.classList.remove('show'), 6000);
      }
    });
  }

  /* ---------- Auth-aware navigation ---------- */
  function initAccountNav() {
    const guestLinks = document.getElementById('navGuestLinks');
    const accountArea = document.getElementById('navAccount');
    const accountTrigger = document.getElementById('navAccountTrigger');
    const accountMenu = document.getElementById('navAccountMenu');
    const accountName = document.getElementById('navAccountName');
    const roleBadge = document.getElementById('navRoleBadge');
    const logoutBtn = document.getElementById('navLogoutBtn');

    const user = SavoriaAPI.getCurrentUser();
    const adminLink = document.getElementById('navAdminLink');

    if (SavoriaAPI.isLoggedIn() && user) {
      guestLinks?.classList.add('visually-hidden');
      accountArea?.classList.remove('visually-hidden');
      if (accountName) accountName.textContent = user.full_name.split(' ')[0];
      if (roleBadge) roleBadge.textContent = user.role;
      if (adminLink) adminLink.style.display = ['staff', 'admin'].includes(user.role) ? 'block' : 'none';
    } else {
      guestLinks?.classList.remove('visually-hidden');
      accountArea?.classList.add('visually-hidden');
    }

    accountTrigger?.addEventListener('click', () => accountMenu?.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (accountArea && !accountArea.contains(e.target)) accountMenu?.classList.remove('open');
    });

    logoutBtn?.addEventListener('click', async (e) => {
      e.preventDefault();
      await SavoriaAPI.logout();
      window.location.href = 'index.html';
    });
  }

  /* ---------- Shared UI (nav scroll, mobile toggle, reveal) ---------- */
  function initNavbar() {
    const navbar = document.getElementById('navbar');
    const toggle = document.getElementById('navToggle');
    if (!navbar) return;
    window.addEventListener('scroll', () => navbar.classList.toggle('scrolled', window.scrollY > 40));
    toggle?.addEventListener('click', () => navbar.classList.toggle('nav-open'));
  }

  function initScrollReveal() {
    const targets = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) {
      targets.forEach(t => t.classList.add('is-visible'));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
      });
    }, { threshold: 0.15 });
    targets.forEach(t => observer.observe(t));
  }

  function initTestimonials() {
    const slides = document.querySelectorAll('.testimonial-slide');
    const dotsWrap = document.getElementById('testimonialDots');
    if (!slides.length || !dotsWrap) return;
    slides.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.className = i === 0 ? 'active' : '';
      dot.addEventListener('click', () => goTo(i));
      dotsWrap.appendChild(dot);
    });
    let current = 0;
    const dots = dotsWrap.querySelectorAll('button');
    function goTo(i) {
      slides[current].classList.remove('active'); dots[current].classList.remove('active');
      current = i;
      slides[current].classList.add('active'); dots[current].classList.add('active');
    }
    setInterval(() => goTo((current + 1) % slides.length), 6000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    initNavbar();
    initAccountNav();
    initMenuFilters();
    initCart();
    initCheckout();
    initReservationForm();
    initTestimonials();
    initScrollReveal();
    loadMenu();
  });
})();
