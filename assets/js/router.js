// assets/js/router.js
(() => {
const ROUTES = {
    '#/dashboard':                      'views/dashboard.html',
    
    '#/clientes':                       'views/clientes.html',
    '#/clientes/clientes':              'views/clientes/clientes.html',
    '#/clientes/dominios':              'views/clientes/dominios.html',
    '#/clientes/presupuestos':          'views/clientes/presupuestos.html',
    
    '#/trabajos/trabajos':              'views/trabajos/trabajos.html',
    
    '#/parametros':                     'views/parametros.html',
    '#/parametros/planes':              'views/parametros/planes.html',
    '#/parametros/servicios':           'views/parametros/servicios.html',
    '#/parametros/conceptos':           'views/parametros/conceptos.html',
    '#/parametros/conceptos-categorias':'views/parametros/conceptos-categorias.html',
    '#/parametros/medios-pago':         'views/parametros/medios_pago.html',
    
    '#/contable':                       'views/contable.html',
    '#/contable/cobros':                'views/contable/cobros.html',
    '#/contable/trabajos':              'views/contable/trabajos.html',
    '#/contable/servicios':             'views/contable/servicios.html',
  };
    
  function normalizeHash(h) {
    if (!h || !h.startsWith('#/')) return '#/login';
    // quitar trailing slash y query, por si aparecen
    const [base] = h.replace(/\/+$/,'').split('?');
    return base;
  }

  async function render(hash) {
    const viewPath = ROUTES[hash];
    if (!viewPath) {
      await App.loadView('views/dashboard.html'); // fallback
    } else {
      await App.loadView(viewPath);
    }
    // avisar a los módulos que ya está la vista en el DOM
    document.dispatchEvent(new CustomEvent('orion:navigate', { detail: { hash } }));
  }

  async function nav() {
    const h = normalizeHash(location.hash);

    if (h === '#/login') {
      await App.loadView('views/login.html');
      if (typeof App.bindLoginView === 'function') App.bindLoginView();
      document.dispatchEvent(new CustomEvent('orion:navigate', { detail: { hash: h } }));
      return;
    }

    // chequeo de sesión
    let ok = false;
    try { ok = await App.bootstrap(); } catch { ok = false; }
    if (!ok) { location.hash = '#/login'; return; }

    await render(h);
  }

  // --- Interceptor global de <a> para mantener la SPA ---
  document.addEventListener('click', (ev) => {
    const a = ev.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href') || '';

    // 1) Navegación por hash → SPA
    if (href.startsWith('#/')) {
      ev.preventDefault();
      if (location.hash !== href) location.hash = href;
      else document.dispatchEvent(new CustomEvent('orion:navigate', { detail: { hash: href } }));
      return;
    }

    // 2) Bloquear navegación directa a endpoints de API (retornan JSON)
    if (/^\/?api\//i.test(href)) {
      ev.preventDefault();
      console.warn('Bloqueada navegación directa a API:', href);
      return;
    }
  });

  window.addEventListener('hashchange', nav);
  window.addEventListener('DOMContentLoaded', nav);
})();
