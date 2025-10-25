// assets/js/modules/clientes.clientes.js
(() => {
  const API = {
    list: '/api/clientes/clientes/list.php',
    get: id => `/api/clientes/clientes/get.php?id=${id}`,
    save: '/api/clientes/clientes/save.php',
    del: id => `/api/clientes/clientes/delete.php?id=${id}`,
    serviciosSelect: '/api/parameters/servicios/select.php'
  };

  let serviciosData = [];

  function here() {
    return location.hash.replace(/\/+$/,'') === '#/clientes/clientes';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    
    const text = await r.text();
    let data = null;
    
    try {
      data = JSON.parse(text);
    } catch (parseError) {
      console.error('❌ Error parsing JSON from:', url);
      console.error('Response status:', r.status);
      console.error('Response text:', text);
      console.error('Parse error:', parseError);
      
      // Intentar mostrar error más amigable
      if (text.includes('Fatal error') || text.includes('Parse error')) {
        alert('Error en el servidor. Revisa la consola para más detalles.');
      }
    }
    
    if (!r.ok) throw { status: r.status, data, text };
    return data;
  }

  function money(v) {
    const n = Number(v || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  const ivaBadge = {
    'RI': '<span class="badge bg-success">RI</span>',
    'RNI': '<span class="badge bg-warning text-dark">RNI</span>',
    'MT': '<span class="badge bg-info">MT</span>',
    'EX': '<span class="badge bg-secondary">EX</span>',
    'CF': '<span class="badge bg-primary">CF</span>',
    'NC': '<span class="badge bg-dark">NC</span>'
  };

  const condVentaBadge = {
    'CONTADO': '<span class="badge bg-success">Contado</span>',
    'CTA_CTE': '<span class="badge bg-warning text-dark">Cta. Cte.</span>',
    'MIXTA': '<span class="badge bg-info">Mixta</span>'
  };

  function rowTpl(c) {
    return `<tr data-id="${c.id}">
      <td>${c.id}</td>
      <td class="fw-semibold">${c.codigo}</td>
      <td>${c.razon_social}</td>
      <td>${c.contacto_nombre || '-'}</td>
      <td>${c.tipo_doc} ${c.nro_doc}</td>
      <td>${ivaBadge[c.iva_cond] || c.iva_cond}</td>
      <td>${c.email || '-'}</td>
      <td>${c.servicio_nombre ? '<span class="badge bg-info">' + c.servicio_nombre + '</span>' : '-'}</td>
      <td>${condVentaBadge[c.condicion_venta] || c.condicion_venta}</td>
      <td>${c.estado == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#clientes-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="11" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value !== '') {
        params.set('estado', filterEstado.value);
      }

      const filterCondicion = document.querySelector('#filter-condicion');
      if (filterCondicion && filterCondicion.value) {
        params.set('condicion_venta', filterCondicion.value);
      }

      const q = document.querySelector('#clientes-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="11" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Clientes list error', e);
      tbody.innerHTML = `<tr><td colspan="11" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function loadServicios() {
    try {
      serviciosData = await fetchJSON(API.serviciosSelect);
      const select = document.querySelector('#cliente-servicio');
      if (!select) return;

      select.innerHTML = '<option value="">-- Sin servicio --</option>';
      serviciosData.forEach(s => {
        select.innerHTML += `<option value="${s.id}" 
          data-nombre="${s.nombre}" 
          data-precio="${s.precio_usd}">${s.nombre} (${s.codigo})</option>`;
      });
    } catch (e) {
      console.error('Error cargando servicios', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    
    set('#cliente-id', '0');
    set('#cliente-codigo', '');
    set('#cliente-razon-social', '');
    set('#cliente-nombre-fantasia', '');
    set('#cliente-tipo-doc', 'CUIT');
    set('#cliente-nro-doc', '');
    set('#cliente-iva-cond', 'CF');
    set('#cliente-iibb-cond', '');
    set('#cliente-iibb-nro', '');
    set('#cliente-inicio-act', '');
    set('#cliente-email', '');
    set('#cliente-telefono', '');
    set('#cliente-celular', '');
    set('#cliente-web', '');
    set('#cliente-contacto-nombre', '');
    set('#cliente-contacto-email', '');
    set('#cliente-contacto-tel', '');
    set('#cliente-direccion', '');
    set('#cliente-direccion2', '');
    set('#cliente-localidad', '');
    set('#cliente-provincia', '');
    set('#cliente-pais', 'Argentina');
    set('#cliente-cp', '');
    set('#cliente-moneda', 'ARG');
    set('#cliente-servicio', '');
    set('#cliente-condicion-venta', 'CONTADO');
    set('#cliente-plazo-pago', '0');
    set('#cliente-tope-credito', '');
    set('#cliente-estado', '1');
    set('#cliente-obs', '');

    const servicioInfo = document.querySelector('#servicio-info');
    if (servicioInfo) servicioInfo.innerHTML = '';
  }

  function updateServicioInfo() {
    const select = document.querySelector('#cliente-servicio');
    const opt = select?.options[select.selectedIndex];
    const servicioInfo = document.querySelector('#servicio-info');
    if (!servicioInfo) return;
    
    if (!opt || !opt.value) {
      servicioInfo.innerHTML = '';
      return;
    }

    const precio = money(opt.dataset.precio);
    servicioInfo.innerHTML = `<strong>Precio:</strong> USD ${precio}`;
  }

  async function openForm(id = 0) {
    if (!here()) return;
    clearForm();
    await loadServicios();

    if (id === 0) {
      const codigoEl = document.querySelector('#cliente-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const c = await fetchJSON(API.get(id));
        document.querySelector('#cliente-id').value = c.id;
        document.querySelector('#cliente-codigo').value = c.codigo || '';
        document.querySelector('#cliente-razon-social').value = c.razon_social || '';
        document.querySelector('#cliente-nombre-fantasia').value = c.nombre_fantasia || '';
        document.querySelector('#cliente-tipo-doc').value = c.tipo_doc || 'CUIT';
        document.querySelector('#cliente-nro-doc').value = c.nro_doc || '';
        document.querySelector('#cliente-iva-cond').value = c.iva_cond || 'CF';
        document.querySelector('#cliente-iibb-cond').value = c.iibb_cond || '';
        document.querySelector('#cliente-iibb-nro').value = c.iibb_nro || '';
        document.querySelector('#cliente-inicio-act').value = c.inicio_act || '';
        document.querySelector('#cliente-email').value = c.email || '';
        document.querySelector('#cliente-telefono').value = c.telefono || '';
        document.querySelector('#cliente-celular').value = c.celular || '';
        document.querySelector('#cliente-web').value = c.web || '';
        document.querySelector('#cliente-contacto-nombre').value = c.contacto_nombre || '';
        document.querySelector('#cliente-contacto-email').value = c.contacto_email || '';
        document.querySelector('#cliente-contacto-tel').value = c.contacto_tel || '';
        document.querySelector('#cliente-direccion').value = c.direccion || '';
        document.querySelector('#cliente-direccion2').value = c.direccion2 || '';
        document.querySelector('#cliente-localidad').value = c.localidad || '';
        document.querySelector('#cliente-provincia').value = c.provincia || '';
        document.querySelector('#cliente-pais').value = c.pais || 'Argentina';
        document.querySelector('#cliente-cp').value = c.cp || '';
        document.querySelector('#cliente-moneda').value = c.moneda_preferida || 'ARG';
        document.querySelector('#cliente-servicio').value = c.servicio || '';
        document.querySelector('#cliente-condicion-venta').value = c.condicion_venta || 'CONTADO';
        document.querySelector('#cliente-plazo-pago').value = c.plazo_pago_dias || 0;
        document.querySelector('#cliente-tope-credito').value = c.tope_credito || '';
        document.querySelector('#cliente-estado').value = c.estado ?? 1;
        document.querySelector('#cliente-obs').value = c.obs || '';
        
        updateServicioInfo();
      } catch (e) {
        console.error('get cliente error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-cliente');
      m.show();
    }
  }

  let saving = false;
  async function saveCliente() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-cliente');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    // Construir payload limpio
    const getId = () => {
      const val = document.querySelector('#cliente-id')?.value;
      return val ? Number(val) : 0;
    };

    const getStr = (sel) => {
      const val = document.querySelector(sel)?.value;
      return val ? val.trim() : '';
    };

    const getNum = (sel) => {
      const val = document.querySelector(sel)?.value;
      return val ? Number(val) : 0;
    };

    const payload = {
      id: getId(),
      codigo: getStr('#cliente-codigo'),
      razon_social: getStr('#cliente-razon-social'),
      nombre_fantasia: getStr('#cliente-nombre-fantasia') || null,
      tipo_doc: document.querySelector('#cliente-tipo-doc')?.value || 'CUIT',
      nro_doc: getStr('#cliente-nro-doc') || null,
      iva_cond: document.querySelector('#cliente-iva-cond')?.value || 'CF',
      iibb_cond: document.querySelector('#cliente-iibb-cond')?.value || null,
      iibb_nro: getStr('#cliente-iibb-nro') || null,
      inicio_act: document.querySelector('#cliente-inicio-act')?.value || null,
      email: getStr('#cliente-email') || null,
      telefono: getStr('#cliente-telefono') || null,
      celular: getStr('#cliente-celular') || null,
      web: getStr('#cliente-web') || null,
      contacto_nombre: getStr('#cliente-contacto-nombre') || null,
      contacto_email: getStr('#cliente-contacto-email') || null,
      contacto_tel: getStr('#cliente-contacto-tel') || null,
      direccion: getStr('#cliente-direccion') || null,
      direccion2: getStr('#cliente-direccion2') || null,
      localidad: getStr('#cliente-localidad') || null,
      provincia: getStr('#cliente-provincia') || null,
      pais: getStr('#cliente-pais') || 'Argentina',
      cp: getStr('#cliente-cp') || null,
      moneda_preferida: document.querySelector('#cliente-moneda')?.value || 'ARG',
      servicio: (() => {
        const val = getNum('#cliente-servicio');
        return val > 0 ? val : null;
      })(),
      condicion_venta: document.querySelector('#cliente-condicion-venta')?.value || 'CONTADO',
      plazo_pago_dias: getNum('#cliente-plazo-pago'),
      tope_credito: (() => {
        const val = getNum('#cliente-tope-credito');
        return val > 0 ? val : null;
      })(),
      obs: getStr('#cliente-obs') || null,
      estado: Number(document.querySelector('#cliente-estado')?.value ?? 1)
    };

    // Validaciones básicas
    if (!payload.razon_social || !payload.contacto_nombre) {
      alert('Razón Social y Nombre de Contacto son obligatorios');
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

      const modalEl = document.querySelector('#modal-cliente');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código o documento ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el cliente');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delCliente(id) {
    if (!confirm('¿Eliminar el cliente? Esta acción no se puede deshacer.')) return;
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

    if (ev.target.closest('#btn-new-cliente')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-cliente')) {
      ev.preventDefault();
      saveCliente();
      return;
    }

    const tr = ev.target.closest('#clientes-tbody tr');
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
        delCliente(id);
        return;
      }
    }
  });

  // Evento de cambio de servicio (actualizar info)
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'cliente-servicio') {
      updateServicioInfo();
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'clientes-search') {
      loadList();
    }
  });

  // Filtros
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado' || ev.target.id === 'filter-condicion') {
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