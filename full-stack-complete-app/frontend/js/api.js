/* =========================================================
   Savoria — Full-Stack Complete App
   js/api.js — thin fetch wrapper around the backend REST API
   ========================================================= */

const SavoriaAPI = (function () {
  'use strict';

  // Point this at your running backend (see backend/README.md).
  const BASE_URL = 'http://localhost:8000';
  const TOKEN_KEY = 'savoria_token';
  const USER_KEY = 'savoria_user';

  function getToken() {
    return localStorage.getItem(TOKEN_KEY);
  }

  function setSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  }

  function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  function getCurrentUser() {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  }

  function isLoggedIn() {
    return !!getToken();
  }

  /**
   * Core request helper. Always attaches the bearer token when present
   * so guest-friendly endpoints (like POST /orders) still get linked to
   * an account if the visitor happens to be logged in.
   */
  async function request(path, { method = 'GET', body = null, auth = false } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let response, json;
    try {
      response = await fetch(`${BASE_URL}${path}`, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
      });
      json = await response.json();
    } catch (networkErr) {
      throw new Error('Could not reach the Savoria server. Is the backend running on ' + BASE_URL + '?');
    }

    if (!response.ok) {
      const msg = json?.message || `Request failed (${response.status})`;
      const err = new Error(msg);
      err.errors = json?.errors || {};
      err.status = response.status;
      if (response.status === 401 && auth) clearSession();
      throw err;
    }

    return json.data;
  }

  return {
    // ---- Menu ----
    getCategories: () => request('/categories'),
    getMenuItems: (params = {}) => {
      const qs = new URLSearchParams(params).toString();
      return request(`/menu-items${qs ? '?' + qs : ''}`);
    },

    // ---- Orders ----
    createOrder: (payload) => request('/orders', { method: 'POST', body: payload }),
    getOrder: (id, phone = null) => request(`/orders/${id}${phone ? '?phone=' + encodeURIComponent(phone) : ''}`),
    getMyOrders: () => request('/my/orders', { auth: true }),

    // ---- Reservations ----
    createReservation: (payload) => request('/reservations', { method: 'POST', body: payload }),

    // ---- Auth ----
    register: (payload) => request('/auth/register', { method: 'POST', body: payload }),
    login: async (payload) => {
      const data = await request('/auth/login', { method: 'POST', body: payload });
      setSession(data.token, data.user);
      return data;
    },
    logout: async () => {
      try { await request('/auth/logout', { method: 'POST', auth: true }); }
      finally { clearSession(); }
    },
    getMe: () => request('/auth/me', { auth: true }),
    changePassword: (payload) => request('/auth/change-password', { method: 'POST', body: payload, auth: true }),
    forgotPassword: (payload) => request('/auth/forgot-password', { method: 'POST', body: payload }),
    resetPassword: (payload) => request('/auth/reset-password', { method: 'POST', body: payload }),

    // ---- Admin / staff ----
    getAdminSummary: () => request('/admin/summary', { auth: true }),
    getAllOrders: () => request('/orders', { auth: true }),
    updateOrderStatus: (id, status) => request(`/orders/${id}`, { method: 'PATCH', body: { status }, auth: true }),
    getAllReservations: () => request('/reservations', { auth: true }),
    updateReservationStatus: (id, status) => request(`/reservations/${id}`, { method: 'PATCH', body: { status }, auth: true }),

    // ---- Session helpers ----
    isLoggedIn,
    getCurrentUser,
    clearSession,
  };
})();
