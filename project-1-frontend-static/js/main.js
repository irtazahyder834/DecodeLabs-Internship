/* =========================================================
   Savoria — Fine Dining Restaurant & Food Ordering Platform
   js/main.js — static frontend behavior (no backend calls)
   ========================================================= */

(function () {
  'use strict';

  /* ---------- Menu data (client-side demo dataset) ---------- */
  const MENU_ITEMS = [
    {
      id: 'st-01', category: 'starters', name: 'Charred Corn & Burrata',
      description: 'Grilled sweet corn, torn burrata, chili oil, micro basil.',
      price: 950, spice: 1,
      image: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&q=80',
      badge: 'Chef\'s Pick'
    },
    {
      id: 'st-02', category: 'starters', name: 'Smoked Prawn Toast',
      description: 'Brioche, smoked prawn butter, pickled shallot, lime zest.',
      price: 1150, spice: 2,
      image: 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&q=80'
    },
    {
      id: 'st-03', category: 'starters', name: 'Roasted Beet Salad',
      description: 'Heirloom beets, whipped goat cheese, candied walnut, sherry vinaigrette.',
      price: 850, spice: 0,
      image: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&q=80'
    },
    {
      id: 'mn-01', category: 'mains', name: 'Slow-Braised Lamb Shank',
      description: 'Red wine jus, saffron mash, roasted root vegetables.',
      price: 2450, spice: 1,
      image: 'https://images.unsplash.com/photo-1544025162-d76694265947?w=500&q=80',
      badge: 'Signature'
    },
    {
      id: 'mn-02', category: 'mains', name: 'Charcoal Grilled Salmon',
      description: 'Miso glaze, sesame greens, ginger-scallion oil.',
      price: 2150, spice: 1,
      image: 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=500&q=80'
    },
    {
      id: 'mn-03', category: 'mains', name: 'Wild Mushroom Risotto',
      description: 'Arborio rice, truffle oil, aged parmesan, crisped sage.',
      price: 1850, spice: 0,
      image: 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=500&q=80'
    },
    {
      id: 'ds-01', category: 'desserts', name: 'Dark Chocolate Fondant',
      description: 'Molten center, pistachio crumble, vanilla bean ice cream.',
      price: 850, spice: 0,
      image: 'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?w=500&q=80',
      badge: 'Guest Favorite'
    },
    {
      id: 'ds-02', category: 'desserts', name: 'Saffron Kunafa',
      description: 'Crisp shredded pastry, cream cheese filling, rose-saffron syrup.',
      price: 780, spice: 0,
      image: 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=500&q=80'
    },
    {
      id: 'bv-01', category: 'beverages', name: 'Rose & Cardamom Cooler',
      description: 'House-made rose syrup, cardamom, soda, fresh mint.',
      price: 550, spice: 0,
      image: 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=500&q=80'
    },
    {
      id: 'bv-02', category: 'beverages', name: 'Cold Brew Espresso Tonic',
      description: 'Slow-steeped cold brew, tonic water, orange peel.',
      price: 620, spice: 0,
      image: 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=500&q=80'
    }
  ];

  const spiceDots = (level) => {
    let html = '';
    for (let i = 0; i < 3; i++) html += `<span class="${i < level ? 'filled' : ''}"></span>`;
    return html;
  };

  const formatPKR = (amount) => 'Rs. ' + amount.toLocaleString('en-PK');

  function renderMenu(filter) {
    const grid = document.getElementById('menuGrid');
    if (!grid) return;
    const items = filter === 'all' ? MENU_ITEMS : MENU_ITEMS.filter(i => i.category === filter);
    grid.innerHTML = items.map(item => `
      <article class="menu-card" data-category="${item.category}">
        <div class="menu-card-media">
          <img src="${item.image}" alt="${item.name}" loading="lazy">
          ${item.badge ? `<span class="menu-card-badge">${item.badge}</span>` : ''}
        </div>
        <div class="menu-card-body">
          <div class="menu-card-top">
            <h3>${item.name}</h3>
            <span class="menu-card-price">${formatPKR(item.price)}</span>
          </div>
          <p>${item.description}</p>
          <div class="menu-card-meta">
            <div class="spice-level" title="Spice level">${spiceDots(item.spice)}</div>
            <span style="font-size:0.78rem;color:var(--color-text-muted);text-transform:capitalize;">${item.category}</span>
          </div>
        </div>
      </article>
    `).join('');
    // trigger reveal animation on newly injected cards
    requestAnimationFrame(() => {
      grid.querySelectorAll('.menu-card').forEach((card, i) => {
        setTimeout(() => card.classList.add('is-visible'), i * 60);
      });
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
    renderMenu('all');
  }

  /* ---------- Navbar scroll + mobile toggle ---------- */
  function initNavbar() {
    const navbar = document.getElementById('navbar');
    const toggle = document.getElementById('navToggle');
    if (!navbar) return;

    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    });

    if (toggle) {
      toggle.addEventListener('click', () => navbar.classList.toggle('nav-open'));
    }

    // Close mobile menu + set active link on nav click
    document.querySelectorAll('.nav-links a').forEach(link => {
      link.addEventListener('click', () => {
        navbar.classList.remove('nav-open');
        document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
        link.classList.add('active');
      });
    });
  }

  /* ---------- Testimonial carousel ---------- */
  function initTestimonials() {
    const slides = document.querySelectorAll('.testimonial-slide');
    const dotsWrap = document.getElementById('testimonialDots');
    if (!slides.length || !dotsWrap) return;

    slides.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.className = i === 0 ? 'active' : '';
      dot.setAttribute('aria-label', `Show review ${i + 1}`);
      dot.addEventListener('click', () => goToSlide(i));
      dotsWrap.appendChild(dot);
    });

    let current = 0;
    const dots = dotsWrap.querySelectorAll('button');

    function goToSlide(index) {
      slides[current].classList.remove('active');
      dots[current].classList.remove('active');
      current = index;
      slides[current].classList.add('active');
      dots[current].classList.add('active');
    }

    setInterval(() => goToSlide((current + 1) % slides.length), 6000);
  }

  /* ---------- Scroll reveal ---------- */
  function initScrollReveal() {
    const targets = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) {
      targets.forEach(t => t.classList.add('is-visible'));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    targets.forEach(t => observer.observe(t));
  }

  /* ---------- Form validation helpers ---------- */
  function setError(fieldId, message) {
    const el = document.querySelector(`[data-error-for="${fieldId}"]`);
    if (el) el.textContent = message || '';
  }

  function validateField(field) {
    const value = field.value.trim();
    if (field.hasAttribute('required') && !value) {
      setError(field.id, 'This field is required.');
      return false;
    }
    if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      setError(field.id, 'Enter a valid email address.');
      return false;
    }
    if (field.type === 'tel' && value && value.replace(/\D/g, '').length < 10) {
      setError(field.id, 'Enter a valid phone number.');
      return false;
    }
    setError(field.id, '');
    return true;
  }

  function initFormValidation(formId, successId) {
    const form = document.getElementById(formId);
    const success = document.getElementById(successId);
    if (!form) return;

    // Live validation on blur
    form.querySelectorAll('input, select, textarea').forEach(field => {
      field.addEventListener('blur', () => validateField(field));
    });

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let isValid = true;
      form.querySelectorAll('input, select, textarea').forEach(field => {
        if (!validateField(field)) isValid = false;
      });

      if (isValid) {
        // Demo-only: this static project has no backend wired up.
        // See project-3/project-4/full-stack-complete-app for live persistence.
        if (success) success.classList.add('show');
        form.reset();
        setTimeout(() => success && success.classList.remove('show'), 5000);
      }
    });
  }

  function initNewsletter() {
    const form = document.getElementById('newsletterForm');
    if (!form) return;
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = form.querySelector('input');
      input.value = '';
      input.placeholder = 'Subscribed! 🎉';
    });
  }

  /* ---------- Init ---------- */
  document.addEventListener('DOMContentLoaded', () => {
    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    initNavbar();
    initMenuFilters();
    initTestimonials();
    initScrollReveal();
    initFormValidation('reservationForm', 'reservationSuccess');
    initFormValidation('contactForm', 'contactSuccess');
    initNewsletter();

    // Minimum reservation date = today
    const dateInput = document.getElementById('resDate');
    if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];
  });
})();
