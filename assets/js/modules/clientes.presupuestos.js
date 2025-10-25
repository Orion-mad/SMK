// assets/js/modules/clientes.presupuestos.js
(() => {
  const API = {
    list: '/api/clientes/presupuestos/list.php',
    get: id => `/api/clientes/presupuestos/get.php?id=${id}`,
    save: '/api/clientes/presupuestos/save.php',
    del: id => `/api/clientes/presupuestos/delete.php?id=${id}`,
    changeStatus: '/api/clientes/presupuestos/change_status.php',
    clone: '/api/clientes/presupuestos/clone.php',
    pdf: id => `/api/clientes/presupuestos/generar_pdf.php?id=${id}`,
    send: '/api/clientes/presupuestos/send.php',
    production: '/api/trabajos/from_presupuesto.php',
    clientesSelect: '/api/clientes/select.php'
  };

  let itemsWrap = null;
  let clientesData = [];

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/clientes/presupuestos';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function money(v) {
    const n = Number(v || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('es-AR');
  }

  const estadoBadge = {
    'borrador': '<span class="badge bg-secondary">Borrador</span>',
    'enviado': '<span class="badge bg-info">Enviado</span>',
    'aprobado': '<span class="badge bg-success">Aprobado</span>',
    'rechazado': '<span class="badge bg-danger">Rechazado</span>',
    'vencido': '<span class="badge bg-warning text-dark">Vencido</span>',
    'cancelado': '<span class="badge bg-dark">Cancelado</span>',
    'en_produccion': '<span class="badge bg-primary">Producción</span>'
  };

  function rowTpl(p) {
    const estado = p.estado_calculado || p.estado;
    return `<tr data-id="${p.id}">
      <td>${p.id}</td>
      <td class="fw-semibold">${p.codigo}</td>
      <td>
        <div class="fw-semibold">${p.cliente_nombre}</div>
        <small class="text-muted">${p.cliente_tipo_doc}: ${p.cliente_doc}</small>
      </td>
      <td>${p.titulo || '<em class="text-muted">Sin título</em>'}</td>
      <td>${formatDate(p.fecha_emision)}</td>
      <td>${formatDate(p.fecha_vencimiento)}</td>
      <td>${estadoBadge[estado] || estado}</td>
      <td class="text-end fw-bold">${p.moneda} ${money(p.total)}</td>
      <td class="text-center"><span class="badge bg-light text-dark">${p.items_count || 0}</span></td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary btn-pdf" title="PDF"><i class="bi bi-file-pdf"></i></button>
          <button class="btn btn-outline-primary btn-edit" title="Editar"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-outline-success btn-send" title="Enviar"><i class="bi bi-envelope"></i></button>
          <button class="btn btn-outline-info btn-clone" title="Clonar"><i class="bi bi-files"></i></button>
          <button class="btn btn-outline-warning btn-produccion" title="Produccion"><i class="bi bi-play-circle"></i></button>
          <button class="btn btn-outline-danger btn-del" title="Eliminar"><i class="bi bi-trash"></i></button>
        </div>
      </td>
    </tr>`;
  // En la columna de acciones, agregar:
  const botonesAccion = `
    <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
    ${p.estado === 'aprobado' ? 
      `<button class="btn btn-sm btn-outline-success me-1 btn-produccion" title="Pasar a Producción">
        <i class="bi bi-play-circle"></i>
      </button>` : ''}
    <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
  `;
      
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#presupuestos-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="10" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) {
        params.set('estado', filterEstado.value);
      }

      const q = document.querySelector('#presupuestos-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="10" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Presupuestos list error', e);
      tbody.innerHTML = `<tr><td colspan="10" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function loadClientes() {
    try {
      clientesData = await fetchJSON(API.clientesSelect);
      const select = document.querySelector('#presupuesto-cliente-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccionar Cliente --</option>';
      clientesData.forEach(c => {
        // Usar nombre_display si existe, sino razon_social, sino contacto_nombre
        const nombre = c.nombre_display || c.razon_social || c.contacto_nombre;
        const docInfo = c.nro_doc ? ` (${c.nro_doc})` : '';
        select.innerHTML += `<option value="${c.id}">${nombre}${docInfo}</option>`;
      });
    } catch (e) {
      console.error('Error cargando clientes', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    
    set('#presupuesto-id', '0');
    set('#presupuesto-codigo', '');
    set('#presupuesto-cliente-id', '');
    set('#presupuesto-titulo', '');
    set('#presupuesto-fecha-emision', new Date().toISOString().split('T')[0]);
    set('#presupuesto-dias-validez', '30');
    set('#presupuesto-fecha-vencimiento', '');
    set('#presupuesto-estado', 'borrador');
    set('#presupuesto-moneda', 'ARG');
    set('#presupuesto-tipo-cobro', 'mensual');
    set('#presupuesto-introduccion', '');
    set('#presupuesto-condiciones', '');
    set('#presupuesto-observaciones', '');
    set('#presupuesto-notas-internas', '');
    set('#presupuesto-forma-pago', '');
    set('#presupuesto-subtotal', '0');
    set('#presupuesto-descuento-porc', '0');
    set('#presupuesto-iva-porc', '21');
    set('#presupuesto-total', '0');
    set('#presupuesto-orden', '0');
    
    const chk = document.querySelector('#presupuesto-activo');
    if (chk) chk.checked = true;

    if (itemsWrap) itemsWrap.innerHTML = '';
  }

  function itemRow(item = {}, idx = 0) {
    return `<div class="card mb-2" data-item>
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-1">
            <label class="form-label small">Orden</label>
            <input type="number" class="form-control form-control-sm text-center" value="${item.orden || idx + 1}" data-i="orden">
          </div>
          <div class="col-6">
            <label class="form-label small">Descripción *</label>
            <textarea class="form-control form-control-sm" rows="2" data-i="descripcion">${item.descripcion || ''}</textarea>
          </div>
          <div class="col-2">
            <label class="form-label small">Cantidad</label>
            <input type="number" step="0.01" class="form-control form-control-sm text-end" value="${item.cantidad || 1}" data-i="cantidad">
          </div>
          <div class="col-2">
            <label class="form-label small">Precio Unit.</label>
            <input type="number" step="0.01" class="form-control form-control-sm text-end" value="${item.precio_unitario || 0}" data-i="precio_unitario">
          </div>
          <div class="col-1 text-end">
            <button class="btn btn-sm btn-outline-danger" type="button" data-action="remove-item">&times;</button>
          </div>
        </div>
      </div>
    </div>`;
  }

  function collectItems() {
    return Array.from(document.querySelectorAll('#items-list [data-item]')).map((row, i) => ({
      orden: Number(row.querySelector('[data-i="orden"]')?.value || (i + 1)),
      tipo: 'item',
      descripcion: row.querySelector('[data-i="descripcion"]')?.value.trim() || '',
      cantidad: Number(row.querySelector('[data-i="cantidad"]')?.value || 1),
      precio_unitario: Number(row.querySelector('[data-i="precio_unitario"]')?.value || 0),
      subtotal: 0, // Se calculará
      descuento_porc: 0,
      descuento_monto: 0,
      subtotal_con_desc: 0,
      iva_porc: 21,
      iva_monto: 0,
      total: 0,
      activo: 1
    })).filter(i => i.descripcion !== '');
  }

  function calcularTotales() {
    const items = collectItems();
    
    let subtotal = 0;
    items.forEach(item => {
      item.subtotal = item.cantidad * item.precio_unitario;
      subtotal += item.subtotal;
    });

    const descuentoPorc = Number(document.querySelector('#presupuesto-descuento-porc')?.value || 0);
    const descuentoMonto = subtotal * (descuentoPorc / 100);
    const subtotalConDesc = subtotal - descuentoMonto;

    const ivaPorc = Number(document.querySelector('#presupuesto-iva-porc')?.value || 21);
    const ivaMonto = subtotalConDesc * (ivaPorc / 100);

    const total = subtotalConDesc + ivaMonto;

    // Actualizar campos
    const setVal = (sel, val) => {
      const el = document.querySelector(sel);
      if (el) el.value = val.toFixed(2);
    };

    setVal('#presupuesto-subtotal', subtotal);
    setVal('#presupuesto-total', total);

    return { items, subtotal, descuentoMonto, ivaMonto, total };
  }

  async function openForm(id = 0) {
    if (!here()) return;
    itemsWrap = document.getElementById('items-list');
    clearForm();
    await loadClientes();

    if (id === 0) {
      // Código se autogenera en backend
      const codigoEl = document.querySelector('#presupuesto-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const p = await fetchJSON(API.get(id));
        
        document.querySelector('#presupuesto-id').value = p.id;
        document.querySelector('#presupuesto-codigo').value = p.codigo || '';
        document.querySelector('#presupuesto-cliente-id').value = p.cliente_id;
        document.querySelector('#presupuesto-titulo').value = p.titulo || '';
        document.querySelector('#presupuesto-fecha-emision').value = p.fecha_emision || '';
        document.querySelector('#presupuesto-dias-validez').value = p.dias_validez || 30;
        document.querySelector('#presupuesto-fecha-vencimiento').value = p.fecha_vencimiento || '';
        document.querySelector('#presupuesto-estado').value = p.estado;
        document.querySelector('#presupuesto-moneda').value = p.moneda;
        document.querySelector('#presupuesto-tipo-cobro').value = p.tipo_cobro;
        document.querySelector('#presupuesto-introduccion').value = p.introduccion || '';
        document.querySelector('#presupuesto-condiciones').value = p.condiciones || '';
        document.querySelector('#presupuesto-observaciones').value = p.observaciones || '';
        document.querySelector('#presupuesto-notas-internas').value = p.notas_internas || '';
        document.querySelector('#presupuesto-forma-pago').value = p.forma_pago || '';
        document.querySelector('#presupuesto-subtotal').value = p.subtotal;
        document.querySelector('#presupuesto-descuento-porc').value = p.descuento_porc;
        document.querySelector('#presupuesto-iva-porc').value = p.iva_porc;
        document.querySelector('#presupuesto-total').value = p.total;
        document.querySelector('#presupuesto-orden').value = p.orden;
        
        const chk = document.querySelector('#presupuesto-activo');
        if (chk) chk.checked = !!p.activo;

        // Cargar items
        (p.items || []).forEach((item, idx) => {
          itemsWrap.insertAdjacentHTML('beforeend', itemRow(item, idx));
        });

      } catch (e) {
        console.error('get presupuesto error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-presupuesto');
      m.show();
    }
  }

  let saving = false;
  async function savePresupuesto() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-presupuesto');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    // Calcular totales
    const { items, subtotal, descuentoMonto, ivaMonto, total } = calcularTotales();

    const payload = {
      id: Number(document.querySelector('#presupuesto-id')?.value || 0),
      codigo: (document.querySelector('#presupuesto-codigo')?.value || '').trim(),
      cliente_id: Number(document.querySelector('#presupuesto-cliente-id')?.value || 0),
      titulo: (document.querySelector('#presupuesto-titulo')?.value || '').trim(),
      fecha_emision: document.querySelector('#presupuesto-fecha-emision')?.value || null,
      dias_validez: Number(document.querySelector('#presupuesto-dias-validez')?.value || 30),
      fecha_vencimiento: document.querySelector('#presupuesto-fecha-vencimiento')?.value || null,
      estado: document.querySelector('#presupuesto-estado')?.value || 'borrador',
      moneda: document.querySelector('#presupuesto-moneda')?.value || 'ARG',
      tipo_cobro: document.querySelector('#presupuesto-tipo-cobro')?.value || 'mensual',
      introduccion: document.querySelector('#presupuesto-introduccion')?.value || null,
      condiciones: document.querySelector('#presupuesto-condiciones')?.value || null,
      observaciones: document.querySelector('#presupuesto-observaciones')?.value || null,
      notas_internas: document.querySelector('#presupuesto-notas-internas')?.value || null,
      forma_pago: document.querySelector('#presupuesto-forma-pago')?.value || null,
      subtotal: subtotal,
      descuento_porc: Number(document.querySelector('#presupuesto-descuento-porc')?.value || 0),
      descuento_monto: descuentoMonto,
      iva_porc: Number(document.querySelector('#presupuesto-iva-porc')?.value || 21),
      iva_monto: ivaMonto,
      total: total,
      orden: Number(document.querySelector('#presupuesto-orden')?.value || 0),
      activo: document.querySelector('#presupuesto-activo')?.checked ? 1 : 0,
      items: items
    };

    if (!payload.cliente_id) {
      alert('Debe seleccionar un cliente');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    if (items.length === 0) {
      if (!confirm('El presupuesto no tiene ítems. ¿Desea continuar?')) {
        if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
        saving = false;
        return;
      }
    }

    try {
      const data = await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const modalEl = document.querySelector('#modal-presupuesto');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el presupuesto');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delPresupuesto(id) {
    if (!confirm('¿Eliminar el presupuesto? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo borrar. ' + (e.data?.error || ''));
      console.error('delete error', e);
    }
  }

  async function clonePresupuesto(id) {
    if (!confirm('¿Clonar este presupuesto?')) return;
    try {
      const result = await fetchJSON(API.clone, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      
      alert('Presupuesto clonado con código: ' + result.codigo);
      await loadList();
    } catch (e) {
      alert('Error al clonar');
      console.error('clone error', e);
    }
  }

  function downloadPDF(id) {
    window.open(API.pdf(id), '_blank');
  }

  function openSendModal(id, clienteEmail) {
    document.querySelector('#send-presupuesto-id').value = id;
    document.querySelector('#send-email').value = clienteEmail || '';
    document.querySelector('#send-mensaje').value = '';

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-send-email');
      m.show();
    }
  }

  async function sendPresupuesto() {
    const id = Number(document.querySelector('#send-presupuesto-id')?.value || 0);
    const email = document.querySelector('#send-email')?.value.trim();
    const mensaje = document.querySelector('#send-mensaje')?.value.trim();

    if (!id || !email) {
      alert('Complete todos los campos');
      return;
    }

    try {
      const result = await fetchJSON(API.send, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, email, mensaje })
      });

      const modalEl = document.querySelector('#modal-send-email');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      const msg = result.simulado 
        ? 'Email simulado (pendiente configuración SMTP)' 
        : 'Presupuesto enviado correctamente';
      
      alert(msg);
      await loadList();
    } catch (e) {
      alert('Error enviando email: ' + (e.data?.error || ''));
      console.error('send error', e);
    }
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-presupuesto')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-presupuesto')) {
      ev.preventDefault();
      savePresupuesto();
      return;
    }

    if (ev.target.closest('#btn-add-item')) {
      ev.preventDefault();
      itemsWrap = document.getElementById('items-list');
      const idx = itemsWrap.querySelectorAll('[data-item]').length;
      itemsWrap.insertAdjacentHTML('beforeend', itemRow({}, idx));
      return;
    }

    if (ev.target.closest('[data-action="remove-item"]')) {
      ev.preventDefault();
      ev.target.closest('[data-item]')?.remove();
      calcularTotales();
      return;
    }

    if (ev.target.closest('#btn-confirm-send')) {
      ev.preventDefault();
      sendPresupuesto();
      return;
    }
    const tr = ev.target.closest('#presupuestos-tbody tr');
    if (tr) {
    const id = Number(tr.dataset.id || 0);
    if (!id) return;

    if (ev.target.closest('.btn-produccion')) {
      ev.preventDefault();
      pasarAProduccion(id);
      return;
    }

      if (ev.target.closest('.btn-edit')) {
        ev.preventDefault();
        openForm(id);
        return;
      }

      if (ev.target.closest('.btn-del')) {
        ev.preventDefault();
        delPresupuesto(id);
        return;
      }

      if (ev.target.closest('.btn-clone')) {
        ev.preventDefault();
        clonePresupuesto(id);
        return;
      }

      if (ev.target.closest('.btn-pdf')) {
        ev.preventDefault();
        downloadPDF(id);
        return;
      }

      if (ev.target.closest('.btn-send')) {
        ev.preventDefault();
        // Obtener email del cliente (si está en la fila)
        const emailCell = tr.querySelector('[data-email]');
        const email = emailCell ? emailCell.dataset.email : '';
        openSendModal(id, email);
        return;
      }
    }
  });

  // Recalcular totales al cambiar cantidades, precios o descuentos
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    
    const target = ev.target;
    
    if (target.matches('[data-i="cantidad"], [data-i="precio_unitario"]')) {
      calcularTotales();
      return;
    }

    if (target.matches('#presupuesto-descuento-porc, #presupuesto-iva-porc')) {
      calcularTotales();
      return;
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'presupuestos-search') {
      clearTimeout(window._searchTimeout);
      window._searchTimeout = setTimeout(loadList, 300);
    }
  });

  // Filtro por estado
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado') {
      loadList();
    }
  });

  // Bootstrap inicial y re-navegación
  const bootList = () => {
    if (here()) {
      itemsWrap = document.getElementById('items-list');
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
    
    
// assets/js/modules/presupuestos.js
// Agregar al módulo existente de presupuestos:

// ==================== PASAR A PRODUCCIÓN ====================

async function pasarAProduccion(presupuestoId) {
  if (!confirm('¿Pasar este presupuesto a producción? Se creará un nuevo trabajo.')) {
    return;
  }

  try {
    const result = await fetchJSON(API.production, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        presupuesto_id: presupuestoId,
        dias_estimados: 30, // Por defecto 30 días
        prioridad: 'normal'
      })
    });

    if (result.ok) {
      // Mostrar mensaje de éxito
      if (window.bootstrap?.Toast) {
        const toastEl = document.createElement('div');
        toastEl.className = 'toast position-fixed bottom-0 end-0 m-3';
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
          <div class="toast-header bg-success text-white">
            <strong class="me-auto">Éxito</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
          </div>
          <div class="toast-body">
            Trabajo creado: <strong>${result.codigo}</strong>
            <br>
            <a href="#/trabajos/trabajos" class="btn btn-sm btn-primary mt-2">Ver Trabajos</a>
          </div>
        `;
        document.body.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
      } else {
        alert(`Trabajo creado exitosamente: ${result.codigo}\n\n¿Desea ir a la vista de Trabajos?`);
        if (confirm('¿Ir a Trabajos?')) {
          location.hash = '#/trabajos/trabajos';
        }
      }

      // Recargar lista de presupuestos
      await loadList();
    }
  } catch (e) {
    if (e.status === 409) {
      alert('Ya existe un trabajo para este presupuesto.');
    } else if (e.status === 400 && e.data?.error) {
      alert('Error: ' + e.data.error);
    } else {
      alert('Error al crear el trabajo');
    }
    console.error('pasar a produccion error', e);
  }
}

})();


    