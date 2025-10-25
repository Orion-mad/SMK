(() => {
  // Helpers
  const Q  = (s, r = document) => r.querySelector(s);
  const QA = (s, r = document) => Array.from(r.querySelectorAll(s));
  window.__LCARS_ROUTER__ = { Q, QA };

  // Estado global mínimo
  const state = { user: null, menu: [] };

  // Carga de vistas en el contenedor principal
  async function loadView(url) {
    try {
      const r = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const html = await r.text();
      const mount = Q('#app-main');
      if (!mount) {
        console.warn('#app-main no encontrado');
        return;
      }

      // 🔹 1. Si estamos saliendo del dashboard, limpiamos antes
      if (window.__dashboardModule && typeof window.__dashboardModule.destroyDashboard === 'function') {
        window.__dashboardModule.destroyDashboard();
        window.__dashboardModule = null;
      }

      // 🔹 2. Cargamos la nueva vista
      mount.innerHTML = html;

      // 🔹 3. Si es el dashboard, inicializamos su módulo
      if (url.includes('dashboard.html')) {
        const module = await import('/assets/js/dsm.js');
        window.__dashboardModule = module;
        if (typeof module.initDashboard === 'function') {
          module.initDashboard();
        }
      }

    } catch (err) {
      console.error('loadView error', err);
      const mount = Q('#app-main');
      if (mount) {
        mount.innerHTML = `
          <div class="container py-5">
            <div class="alert alert-danger">
              No se pudo cargar la vista <code>${url}</code>.
            </div>
          </div>`;
      }
    }
  }

  function paintTopbar() {
    const t = Q('#app-topbar-user');
    if (!t) return;
    t.textContent = state.user ? `${state.user.nombre} ${state.user.apellido}` : 'No autenticado';
  }

  function renderSidebar(menu) {
    const wrap = Q('#app-sidebar');
    if (!wrap) return;
    if (!state.user) { wrap.innerHTML = ''; return; }

    let out = '';
    menu.forEach(m => {
      out += `<div class="lcars-module">
                <div class="lcars-module-title">${m.module_name}</div>`;
      m.items.forEach(it => {
        out += `<a href="${it.route}" class="lcars-item d-block py-2 px-3">${it.item_name}</a>`;
      });
      out += `</div>`;
      out += `<div class="lcars-module"><div class="lcars-module-title">Configuración</div>
              <a href="#/parametros" class="lcars-item d-block py-2 px-3">Parámetros</a>
            </div>`;
    });
    wrap.innerHTML = out;
  }

  function updateStardate() {
    const base = new Date('2323-01-01T00:00:00Z');
    const now  = new Date();
    const diff = now - base;
    const star = (diff / (1000 * 60 * 60 * 24)).toFixed(2);
    const el = Q('#stardate');
    if (el) el.textContent = star;
  }
  setInterval(updateStardate, 5000);
  updateStardate();

  async function bootstrap() {
    try {
      const r = await fetch('api/auth/bootstrap.php', { cache: 'no-store', credentials: 'same-origin' });
      const j = await r.json().catch(() => ({}));
      if (!j || j.ok !== true) {
        state.user = null;
        state.menu = [];
        paintTopbar();
        renderSidebar([]);
        return false;
      }
      state.user = j.user;
      state.menu = j.menu || [];
      paintTopbar();
      renderSidebar(state.menu);
      return true;
    } catch (e) {
      console.error('bootstrap error', e);
      state.user = null;
      state.menu = [];
      paintTopbar();
      renderSidebar([]);
      return false;
    }
  }

  async function doLogout() {
    try {
      await fetch('api/auth/logout.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store' });
    } catch {}
    location.hash = '#/login';
  }
  window.doLogout = doLogout;

  function bindLoginView() {
    const f = Q('#frmLogin') || Q('#form-login');
    if (!f) return;
    const alertBox = Q('#loginAlert');

    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      f.classList.add('was-validated');
      if (!f.checkValidity()) return;

      const body = new URLSearchParams(new FormData(f));
      try {
        const res = await fetch('api/auth/login.php', { method: 'POST', body, credentials: 'same-origin' });
        if (res.status === 204) {
          const ok = await bootstrap();
          if (ok) {
            location.hash = '#/dashboard';
          } else {
            if (alertBox) {
              alertBox.textContent = 'No se pudo establecer la sesión.';
              alertBox.classList.remove('d-none');
            }
          }
        } else {
          const msg = await res.text().catch(() => '');
          if (alertBox) {
            alertBox.textContent = msg || 'Error de login';
            alertBox.classList.remove('d-none');
          }
        }
      } catch (err) {
        if (alertBox) {
          alertBox.textContent = 'Error de red';
          alertBox.classList.remove('d-none');
        }
      }
    });
  }

  window.App = { loadView, bootstrap, bindLoginView, state, paintTopbar, renderSidebar };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#btnLogout,[data-action="logout"]');
    if (!btn) return;
    e.preventDefault();
    doLogout();
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#togglePass, #toggle-pass');
    if (!btn) return;
    const pass = document.querySelector('input[name="password"], #login-pass');
    if (!pass) return;
    const showing = pass.type !== 'password';
    pass.type = showing ? 'password' : 'text';
    btn.innerHTML = `<i class="bi ${showing ? 'bi-eye' : 'bi-eye-slash'}"></i>`;
  });

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#/"]');
    if (!a) return;
    e.preventDefault();
    const target = a.getAttribute('href');
    if (location.hash === target) {
      window.dispatchEvent(new HashChangeEvent('hashchange'));
    } else {
      location.hash = target;
    }
  });
})();
