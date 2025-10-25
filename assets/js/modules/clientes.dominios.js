// assets/js/modules/clientes.dominios.js
(() => {
  const API = {
    list: '/api/clientes/dominios/list.php',
    get: id => `/api/clientes/dominios/get.php?id=${id}`,
    save: '/api/clientes/dominios/save.php',
    del: id => `/api/clientes/dominios/delete.php?id=${id}`,
    clientesSelect: '/api/clientes/select.php',
    planesSelect: '/api/parameters/planes/select.php'
  };

  let clientesData = [];
  let planesData = [];

  function here() {
    return location.hash.replace(/\/+$/,'') === '#/clientes/dominios';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('es-AR');
  }

  function getDaysUntil(dateStr) {
    if (!dateStr) return null;
    const target = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = Math.ceil((target - today) / (1000 * 60 * 60 * 24));
    return diff;
  }

  const estadoBadge = {
    'activo': '<span class="badge bg-success">Activo</span>',
    'suspendido': '<span class="badge bg-warning text-dark">Suspendido</span>',
    'vencido': '<span class="badge bg-danger">Vencido</span>',
    'transferencia': '<span class="badge bg-info">En Transferencia</span>',
    'cancelado': '<span class="badge bg-secondary">Cancelado</span>'
  };

  const tipoBadge = {
    'principal': '<span class="badge bg-primary">Principal</span>',
    'adicional': '<span class="badge bg-info">Adicional</span>',
    'subdominio': '<span class="badge bg-secondary">Subdominio</span>',
    'redireccion': '<span class="badge bg-warning text-dark">Redirección</span>'
  };

  function rowTpl(d) {
    const cliente = d.cliente_nombre_fantasia || d.cliente_razon_social || '-';
    const daysUntil = getDaysUntil(d.fecha_vencimiento);
    let vencimientoHtml = formatDate(d.fecha_vencimiento);
    
    if (daysUntil !== null) {
      if (daysUntil < 0) {
        vencimientoHtml += ' <span class="badge bg-danger ms-1">Vencido</span>';
      } else if (daysUntil <= 30) {
        vencimientoHtml += ` <span class="badge bg-warning text-dark ms-1">${daysUntil}d</span>`;
      }
    }

    const sslIcon = d.ssl_activo === 1 
      ? '<i class="bi bi-shield-check text-success" title="SSL Activo"></i>'
      : '<i class="bi bi-shield-x text-muted" title="Sin SSL"></i>';

    return `<tr data-id="${d.id}">
      <td>${d.id}</td>
      <td class="fw-semibold">${d.codigo}</td>
      <td>
        <div class="fw-bold">${d.dominio}</div>
        ${d.proveedor_hosting ? `<small class="text-muted">${d.proveedor_hosting}</small>` : ''}
      </td>
      <td>
        <div>${cliente}</div>
        ${d.plan_nombre ? `<small class="text-muted">${d.plan_nombre}</small>` : ''}
      </td>
      <td>${tipoBadge[d.tipo_dominio] || d.tipo_dominio}</td>
      <td>${d.proveedor_hosting || '-'}</td>
      <td>${vencimientoHtml}</td>
      <td class="text-center">${sslIcon}</td>
      <td>${estadoBadge[d.estado] || d.estado}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit" title="Editar">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger btn-del" title="Eliminar">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#dominios-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="10" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) {
        params.set('estado', filterEstado.value);
      }

      const filterSSL = document.querySelector('#filter-ssl');
      if (filterSSL && filterSSL.value) {
        params.set('ssl_activo', filterSSL.value);
      }

      const q = document.querySelector('#dominios-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="10" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Dominios list error', e);
      tbody.innerHTML = `<tr><td colspan="10" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function loadClientes() {
    try {
      clientesData = await fetchJSON(API.clientesSelect);
      const select = document.querySelector('#dominio-cliente-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccionar Cliente --</option>';
      clientesData.forEach(c => {
        const display = c.razon_social || c.contacto_nombre;
        select.innerHTML += `<option value="${c.id}">${display} (${c.codigo})</option>`;
      });
    } catch (e) {
      console.error('Error cargando clientes', e);
    }
  }

  async function loadPlanes() {
    try {
      planesData = await fetchJSON(API.planesSelect);
      const select = document.querySelector('#dominio-plan-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Sin Plan --</option>';
      planesData.forEach(p => {
        select.innerHTML += `<option value="${p.id}">${p.nombre} (${p.codigo})</option>`;
      });
    } catch (e) {
      console.error('Error cargando planes', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { 
      const el = document.querySelector(sel); 
      if (el) el.value = val; 
    };
    
    set('#dominio-id', '0');
    set('#dominio-codigo', '');
    set('#dominio-nombre', '');
    set('#dominio-cliente-id', '');
    set('#dominio-plan-id', '');
    set('#dominio-tipo', 'principal');
    set('#dominio-proveedor', '');
    set('#dominio-servidor', '');
    set('#dominio-panel', '');
    set('#dominio-url-panel', '');
    set('#dominio-usuario', '');
    set('#dominio-password', '');
    set('#dominio-registrador', '');
    set('#dominio-fecha-registro', '');
    set('#dominio-fecha-vencimiento', '');
    set('#dominio-ns1', '');
    set('#dominio-ns2', '');
    set('#dominio-ns3', '');
    set('#dominio-ns4', '');
    set('#dominio-ip', '');
    set('#dominio-ssl-activo', '0');
    set('#dominio-ssl-tipo', '');
    set('#dominio-ssl-vencimiento', '');
    set('#dominio-estado', 'activo');
    set('#dominio-renovacion', '0');
    set('#dominio-orden', '0');
    set('#dominio-observaciones', '');
    set('#dominio-detalles', '');
    
    const chk = document.querySelector('#dominio-mostrar-info');
    if (chk) chk.checked = false;
  }

  async function openForm(id = 0) {
    if (!here()) return;
    
    clearForm();
    await loadClientes();
    await loadPlanes();

    if (id === 0) {
      const codigoEl = document.querySelector('#dominio-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const d = await fetchJSON(API.get(id));
        
        document.querySelector('#dominio-id').value = d.id;
        document.querySelector('#dominio-codigo').value = d.codigo || '';
        document.querySelector('#dominio-nombre').value = d.dominio || '';
        document.querySelector('#dominio-cliente-id').value = d.cliente_id || '';
        document.querySelector('#dominio-plan-id').value = d.plan_id || '';
        document.querySelector('#dominio-tipo').value = d.tipo_dominio || 'principal';
        document.querySelector('#dominio-proveedor').value = d.proveedor_hosting || '';
        document.querySelector('#dominio-servidor').value = d.servidor || '';
        document.querySelector('#dominio-panel').value = d.panel_control || '';
        document.querySelector('#dominio-url-panel').value = d.url_panel || '';
        document.querySelector('#dominio-usuario').value = d.usuario_hosting || '';
        document.querySelector('#dominio-password').value = d.password_hosting || '';
        document.querySelector('#dominio-registrador').value = d.registrador || '';
        document.querySelector('#dominio-fecha-registro').value = d.fecha_registro || '';
        document.querySelector('#dominio-fecha-vencimiento').value = d.fecha_vencimiento || '';
        document.querySelector('#dominio-ns1').value = d.ns1 || '';
        document.querySelector('#dominio-ns2').value = d.ns2 || '';
        document.querySelector('#dominio-ns3').value = d.ns3 || '';
        document.querySelector('#dominio-ns4').value = d.ns4 || '';
        document.querySelector('#dominio-ip').value = d.ip_principal || '';
        document.querySelector('#dominio-ssl-activo').value = d.ssl_activo || '0';
        document.querySelector('#dominio-ssl-tipo').value = d.ssl_tipo || '';
        document.querySelector('#dominio-ssl-vencimiento').value = d.ssl_vencimiento || '';
        document.querySelector('#dominio-estado').value = d.estado || 'activo';
        document.querySelector('#dominio-renovacion').value = d.renovacion_auto || '0';
        document.querySelector('#dominio-orden').value = d.orden || '0';
        document.querySelector('#dominio-observaciones').value = d.observaciones || '';
        document.querySelector('#dominio-detalles').value = d.detalles || '';
        
      } catch (e) {
        console.error('get dominio error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-dominio');
      m.show();
    }
  }

  let saving = false;
  async function saveDominio() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-dominio');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const payload = {
      id: Number(document.querySelector('#dominio-id')?.value || 0),
      codigo: (document.querySelector('#dominio-codigo')?.value || '').trim(),
      dominio: (document.querySelector('#dominio-nombre')?.value || '').trim(),
      cliente_id: Number(document.querySelector('#dominio-cliente-id')?.value || 0),
      plan_id: Number(document.querySelector('#dominio-plan-id')?.value || 0) || null,
      tipo_dominio: document.querySelector('#dominio-tipo')?.value || 'principal',
      proveedor_hosting: (document.querySelector('#dominio-proveedor')?.value || '').trim(),
      servidor: (document.querySelector('#dominio-servidor')?.value || '').trim(),
      panel_control: (document.querySelector('#dominio-panel')?.value || '').trim(),
      url_panel: (document.querySelector('#dominio-url-panel')?.value || '').trim(),
      usuario_hosting: (document.querySelector('#dominio-usuario')?.value || '').trim(),
      password_hosting: (document.querySelector('#dominio-password')?.value || '').trim(),
      registrador: (document.querySelector('#dominio-registrador')?.value || '').trim(),
      fecha_registro: document.querySelector('#dominio-fecha-registro')?.value || null,
      fecha_vencimiento: document.querySelector('#dominio-fecha-vencimiento')?.value || null,
      ns1: (document.querySelector('#dominio-ns1')?.value || '').trim(),
      ns2: (document.querySelector('#dominio-ns2')?.value || '').trim(),
      ns3: (document.querySelector('#dominio-ns3')?.value || '').trim(),
      ns4: (document.querySelector('#dominio-ns4')?.value || '').trim(),
      ip_principal: (document.querySelector('#dominio-ip')?.value || '').trim(),
      ssl_activo: Number(document.querySelector('#dominio-ssl-activo')?.value || 0),
      ssl_tipo: (document.querySelector('#dominio-ssl-tipo')?.value || '').trim(),
      ssl_vencimiento: document.querySelector('#dominio-ssl-vencimiento')?.value || null,
      estado: document.querySelector('#dominio-estado')?.value || 'activo',
      renovacion_auto: Number(document.querySelector('#dominio-renovacion')?.value || 0),
      orden: Number(document.querySelector('#dominio-orden')?.value || 0),
      observaciones: (document.querySelector('#dominio-observaciones')?.value || '').trim(),
      detalles: (document.querySelector('#dominio-detalles')?.value || '').trim()
    };

    if (!payload.dominio) {
      alert('El dominio es obligatorio');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    if (!payload.cliente_id) {
      alert('Debe seleccionar un Cliente');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    try {
      const data = await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const modalEl = document.querySelector('#modal-dominio');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código o dominio ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el dominio');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delDominio(id) {
    if (!confirm('¿Eliminar el dominio? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo borrar');
      console.error('delete error', e);
    }
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-dominio')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-dominio')) {
      ev.preventDefault();
      saveDominio();
      return;
    }

    // Toggle password visibility
    if (ev.target.closest('#toggle-password')) {
      ev.preventDefault();
      const btn = ev.target.closest('#toggle-password');
      const pass = document.querySelector('#dominio-password');
      if (!pass) return;
      const showing = pass.type !== 'password';
      pass.type = showing ? 'password' : 'text';
      btn.innerHTML = `<i class="bi ${showing ? 'bi-eye' : 'bi-eye-slash'}"></i>`;
      return;
    }

    const tr = ev.target.closest('#dominios-tbody tr');
    if (tr) {
      const id = Number(tr.dataset.id || 0);
      if (!id) return;
      if (ev.target.closest('.btn-edit')) {
        ev.preventDefault();
        openForm(id);
        return;
      }
      if (ev.target.closest('.btn-del')) {
        ev.preventDefault();
        delDominio(id);
        return;
      }
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'dominios-search') {
      loadList();
    }
  });

  // Filtros
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado' || ev.target.id === 'filter-ssl') {
      loadList();
    }
  });

  // Bootstrap inicial y re-navegación
  const bootList = () => {
    if (here()) {
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();