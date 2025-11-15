// assets/js/modules/trabajos.js
(() => {
  const API = {
    list: '/api/trabajos/list.php',
    get: id => `/api/trabajos/get.php?id=${id}`,
    save: '/api/trabajos/save.php',
    del: id => `/api/trabajos/delete.php?id=${id}`,
    clientes: '/api/clientes/select.php',
    servicios: '/api/parameters/servicios/select.php',
    pagos: {
      save: '/api/trabajos/pagos/save.php',
      del: id => `/api/trabajos/pagos/delete.php?id=${id}`,
    }
  };

  let currentTrabajoId = 0;
  let isLoadingList = false; // Control de carga múltiple

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/trabajos/trabajos';
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
    'pendiente': '<span class="badge bg-secondary">Pendiente</span>',
    'en_proceso': '<span class="badge bg-primary">En Proceso</span>',
    'homologacion': '<span class="badge bg-warning text-dark">Homologación</span>',
    'finalizado': '<span class="badge bg-info">Finalizado</span>',
    'entregado': '<span class="badge bg-success">Entregado</span>',
    'cancelado': '<span class="badge bg-danger">Cancelado</span>'
  };

  const prioridadBadge = {
    'baja': '<span class="badge bg-secondary">Baja</span>',
    'normal': '<span class="badge bg-info">Normal</span>',
    'alta': '<span class="badge bg-warning text-dark">Alta</span>',
    'urgente': '<span class="badge bg-danger">Urgente</span>'
  };

  function rowTpl(t) {
    const diasClass = t.dias_para_entrega < 0 ? 'text-danger' : 
                      t.dias_para_entrega < 7 ? 'text-warning' : '';
    const saldoClass = t.saldo > 0 ? 'text-danger fw-bold' : 'text-success';
    
    return `<tr data-id="${t.id}">
      <td>${t.id}</td>
      <td class="fw-semibold">${t.codigo}</td>
      <td>
        <div class="fw-semibold">${t.nombre}</div>
        ${t.servicio_nombre ? `<small class="text-muted">${t.servicio_nombre}</small>` : ''}
      </td>
      <td>
        <div>${t.cliente_nombre}</div>
        ${t.cliente_razon_social ? `<small class="text-muted">${t.cliente_razon_social}</small>` : ''}
      </td>
      <td class="text-nowrap">${formatDate(t.fecha_ingreso)}</td>
      <td class="text-nowrap">${formatDate(t.fecha_entrega_estimada)}</td>
      <td class="text-center ${diasClass}">
        ${t.dias_para_entrega !== null ? t.dias_para_entrega : '-'}
      </td>
      <td class="text-center">${estadoBadge[t.estado] || t.estado}</td>
      <td class="text-center">${prioridadBadge[t.prioridad] || t.prioridad}</td>
      <td class="text-end">${t.moneda} ${money(t.total)}</td>
      <td class="text-end ${saldoClass}">${t.moneda} ${money(t.saldo)}</td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-success me-1 btn-pagos" title="Pagos">
          <i class="bi bi-cash-stack"></i>
        </button>
        <button class="btn btn-sm btn-outline-info me-1 btn-pdf" title="Imprimir PDF">
          <i class="bi bi-file-pdf"></i>
        </button>
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit" title="Editar">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger btn-del" title="Borrar">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    
    // Prevenir llamadas múltiples simultáneas
    if (isLoadingList) {
      console.log('[Trabajos] Ya hay una carga en progreso, ignorando...');
      return;
    }
    
    const tbody = document.querySelector('#trabajos-tbody');
    if (!tbody) return;

    isLoadingList = true;
    tbody.innerHTML = `<tr><td colspan="12" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) {
        params.set('estado', filterEstado.value);
      }

      const filterPrioridad = document.querySelector('#filter-prioridad');
      if (filterPrioridad && filterPrioridad.value) {
        params.set('prioridad', filterPrioridad.value);
      }

      const btnSaldo = document.querySelector('#filter-saldo-pendiente');
      if (btnSaldo && btnSaldo.classList.contains('active')) {
        params.set('saldo_pendiente', '1');
      }

      const q = document.querySelector('#trabajos-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="12" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Trabajos list error', e);
      tbody.innerHTML = `<tr><td colspan="12" class="text-danger p-3">Error cargando datos</td></tr>`;
    } finally {
      isLoadingList = false;
    }
  }

  async function loadClientes() {
    try {
      const data = await fetchJSON(API.clientes);
      const select = document.querySelector('#trabajo-cliente-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccionar Cliente --</option>';
      data.forEach(c => {
        select.innerHTML += `<option value="${c.id}">${c.contacto_nombre}${c.razon_social ? ' - ' + c.razon_social : ''}</option>`;
      });
    } catch (e) {
      console.error('Error cargando clientes', e);
    }
  }

  async function loadServicios() {
    try {
      const data = await fetchJSON(API.servicios);
      const select = document.querySelector('#trabajo-servicio-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccionar Servicio --</option>';
      data.forEach(s => {
        select.innerHTML += `<option value="${s.id}">${s.nombre} (${s.codigo})</option>`;
      });
    } catch (e) {
      console.error('Error cargando servicios', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { 
      const el = document.querySelector(sel); 
      if (el) el.value = val; 
    };
    
    set('#trabajo-id', '0');
    set('#trabajo-codigo', '');
    set('#trabajo-nombre', '');
    set('#trabajo-descripcion', '');
    set('#trabajo-cliente-id', '');
    set('#trabajo-servicio-id', '');
    set('#trabajo-presupuesto-id', '');
    set('#trabajo-presupuesto-codigo', '');
    set('#trabajo-fecha-ingreso', new Date().toISOString().split('T')[0]);
    set('#trabajo-fecha-entrega-estimada', '');
    set('#trabajo-fecha-entrega-real', '');
    set('#trabajo-estado', 'pendiente');
    set('#trabajo-prioridad', 'normal');
    set('#trabajo-total', '0');
    set('#trabajo-saldo', '0');
    set('#trabajo-moneda', 'ARS');
    set('#trabajo-medio-pago', '');
    set('#trabajo-orden', '0');
    set('#trabajo-observaciones', '');
    
    const chk = document.querySelector('#trabajo-requiere-homologacion');
    if (chk) chk.checked = false;
    
    set('#trabajo-homologacion-url', '');
    set('#trabajo-homologacion-usuario', '');
    set('#trabajo-homologacion-password', '');
    set('#trabajo-homologacion-notas', '');
    set('#trabajo-homologacion-estado', '');
    
    toggleHomologacionFields();
  }

  function toggleHomologacionFields() {
    const chk = document.querySelector('#trabajo-requiere-homologacion');
    const fields = document.querySelector('#homologacion-fields');
    if (fields) {
      fields.style.display = chk && chk.checked ? 'block' : 'none';
    }
  }

  async function openForm(id = 0) {
    if (!here()) return;
    
    clearForm();
    await loadClientes();
    await loadServicios();

    if (id === 0) {
      // Código se autogenera
      const codigoEl = document.querySelector('#trabajo-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const t = await fetchJSON(API.get(id));
        console.log('[T]',t);
        document.querySelector('#trabajo-id').value = t.id;
        document.querySelector('#trabajo-codigo').value = t.codigo || '';
        document.querySelector('#trabajo-nombre').value = t.nombre || '';
        document.querySelector('#trabajo-descripcion').value = t.descripcion || '';
        document.querySelector('#trabajo-cliente-id').value = t.cliente_id;
        document.querySelector('#trabajo-servicio-id').value = t.servicio_id || '';
        document.querySelector('#trabajo-presupuesto-id').value = t.presupuesto_id || '';
        document.querySelector('#trabajo-presupuesto-codigo').value = t.presupuesto_codigo || '';
        document.querySelector('#trabajo-fecha-ingreso').value = t.fecha_ingreso || '';
        document.querySelector('#trabajo-fecha-entrega-estimada').value = t.fecha_entrega_estimada || '';
        document.querySelector('#trabajo-fecha-entrega-real').value = t.fecha_entrega_real || '';
        document.querySelector('#trabajo-estado').value = t.estado || 'pendiente';
        document.querySelector('#trabajo-prioridad').value = t.prioridad || 'normal';
        document.querySelector('#trabajo-total').value = t.total || '0';
        document.querySelector('#trabajo-saldo').value = t.saldo || '0';
        document.querySelector('#trabajo-moneda').value = t.moneda || 'ARS';
        document.querySelector('#trabajo-medio-pago').value = t.medio_pago || '';
        document.querySelector('#trabajo-orden').value = t.orden || '0';
        document.querySelector('#trabajo-observaciones').value = t.observaciones || '';
        
        const chk = document.querySelector('#trabajo-requiere-homologacion');
        if (chk) chk.checked = !!t.requiere_homologacion;
        
        document.querySelector('#trabajo-homologacion-url').value = t.homologacion_url || '';
        document.querySelector('#trabajo-homologacion-usuario').value = t.homologacion_usuario || '';
        document.querySelector('#trabajo-homologacion-password').value = t.homologacion_password || '';
        document.querySelector('#trabajo-homologacion-notas').value = t.homologacion_notas || '';
        document.querySelector('#trabajo-homologacion-estado').value = t.homologacion_estado || '';
        
        toggleHomologacionFields();
      } catch (e) {
        console.error('Error cargando trabajo', e);
        alert('Error al cargar el trabajo');
        return;
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-trabajo');
      m.show();
    }
  }

  async function saveTrabajo() {
    const form = {
      id: Number(document.querySelector('#trabajo-id').value) || 0,
      codigo: document.querySelector('#trabajo-codigo')?.value || null,
      nombre: document.querySelector('#trabajo-nombre').value,
      descripcion: document.querySelector('#trabajo-descripcion').value || null,
      cliente_id: Number(document.querySelector('#trabajo-cliente-id').value),
      servicio_id: Number(document.querySelector('#trabajo-servicio-id').value) || null,
      presupuesto_id: Number(document.querySelector('#trabajo-presupuesto-id').value) || null,
      presupuesto_codigo: document.querySelector('#trabajo-presupuesto-codigo').value || null,
      fecha_ingreso: document.querySelector('#trabajo-fecha-ingreso').value || null,
      fecha_entrega_estimada: document.querySelector('#trabajo-fecha-entrega-estimada').value || null,
      fecha_entrega_real: document.querySelector('#trabajo-fecha-entrega-real').value || null,
      estado: document.querySelector('#trabajo-estado').value,
      prioridad: document.querySelector('#trabajo-prioridad').value,
      total: Number(document.querySelector('#trabajo-total').value) || 0,
      saldo: Number(document.querySelector('#trabajo-saldo').value) || 0,
      moneda: document.querySelector('#trabajo-moneda').value,
      medio_pago: document.querySelector('#trabajo-medio-pago').value || null,
      orden: Number(document.querySelector('#trabajo-orden').value) || 0,
      observaciones: document.querySelector('#trabajo-observaciones').value || null,
      requiere_homologacion: document.querySelector('#trabajo-requiere-homologacion')?.checked ? 1 : 0,
      homologacion_url: document.querySelector('#trabajo-homologacion-url').value || null,
      homologacion_usuario: document.querySelector('#trabajo-homologacion-usuario').value || null,
      homologacion_password: document.querySelector('#trabajo-homologacion-password').value || null,
      homologacion_notas: document.querySelector('#trabajo-homologacion-notas').value || null,
      homologacion_estado: document.querySelector('#trabajo-homologacion-estado').value || null
    };

    if (!form.nombre || !form.cliente_id || !form.fecha_ingreso) {
      alert('Nombre, Cliente y Fecha de Ingreso son obligatorios');
      return;
    }

    try {
      const result = await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form)
      });

      if (window.bootstrap?.Modal) {
        const m = bootstrap.Modal.getInstance(document.querySelector('#modal-trabajo'));
        if (m) m.hide();
      }
      
      await loadList();
      console.log('Trabajo guardado OK', result);
    } catch (e) {
      if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error al guardar el trabajo');
      console.error('save error', e);
    }
  }

  async function delTrabajo(id) {
    if (!confirm('¿Eliminar este trabajo y todos sus datos asociados?')) return;
    
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo eliminar el trabajo');
      console.error('delete error', e);
    }
  }

  async function openPagos(trabajoId) {
    currentTrabajoId = trabajoId;
    
    try {
      const t = await fetchJSON(API.get(trabajoId));
      
      document.querySelector('#pago-trabajo-id').value = t.id;
      document.querySelector('#pago-trabajo-nombre').textContent = t.nombre;
      document.querySelector('#pago-trabajo-total').textContent = `${t.moneda} ${money(t.total)}`;
      document.querySelector('#pago-trabajo-saldo').textContent = `${t.moneda} ${money(t.saldo)}`;
      
      // Reset form
      document.querySelector('#pago-fecha').value = new Date().toISOString().split('T')[0];
      document.querySelector('#pago-monto').value = '';
      document.querySelector('#pago-medio').value = '';
      document.querySelector('#pago-referencia').value = '';
      document.querySelector('#pago-estado').value = 'confirmado';
      document.querySelector('#pago-observaciones').value = '';
      
      renderPagos(t.pagos || []);

      if (window.bootstrap?.Modal) {
        const m = new bootstrap.Modal('#modal-pagos');
        m.show();
      }
    } catch (e) {
      console.error('Error cargando trabajo', e);
    }
  }

  function renderPagos(pagos) {
    const tbody = document.querySelector('#pagos-tbody');
    if (!tbody) return;

    if (!pagos || pagos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Sin pagos registrados</td></tr>';
      return;
    }

    const estadoPagoBadge = {
      'confirmado': '<span class="badge bg-success">Confirmado</span>',
      'pendiente': '<span class="badge bg-warning">Pendiente</span>',
      'rechazado': '<span class="badge bg-danger">Rechazado</span>',
      'anulado': '<span class="badge bg-secondary">Anulado</span>'
    };

    tbody.innerHTML = pagos.map(p => `
      <tr>
        <td>${formatDate(p.fecha_pago)}</td>
        <td class="text-end fw-bold">${money(p.monto)}</td>
        <td>${p.medio_pago}</td>
        <td>${p.referencia || '-'}</td>
        <td class="text-center">${estadoPagoBadge[p.estado] || p.estado}</td>
        <td class="text-center">
          ${p.recibo_generado ? '<i class="bi bi-file-pdf text-success"></i>' : '-'}
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger btn-del-pago" data-pago-id="${p.id}">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  }

  async function savePago() {
    const trabajoId = Number(document.querySelector('#pago-trabajo-id').value);
    const monto = Number(document.querySelector('#pago-monto').value);
    const medio = document.querySelector('#pago-medio').value;
    const fecha = document.querySelector('#pago-fecha').value;

    if (!fecha || !monto || !medio) {
      alert('Fecha, Monto y Medio de Pago son obligatorios');
      return;
    }

    const payload = {
      id: 0,
      trabajo_id: trabajoId,
      fecha_pago: fecha,
      monto: monto,
      moneda: 'ARS',
      medio_pago: medio,
      referencia: document.querySelector('#pago-referencia').value || null,
      estado: document.querySelector('#pago-estado').value,
      observaciones: document.querySelector('#pago-observaciones').value || null
    };

    try {
      const result = await fetchJSON(API.pagos.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      // Recargar el trabajo para actualizar la lista de pagos
      const t = await fetchJSON(API.get(trabajoId));
      renderPagos(t.pagos || []);
      
      // Actualizar el saldo en el modal
      document.querySelector('#pago-trabajo-saldo').textContent = `${t.moneda} ${money(t.saldo)}`;
      
      // Limpiar formulario
      document.querySelector('#pago-monto').value = '';
      document.querySelector('#pago-medio').value = '';
      document.querySelector('#pago-referencia').value = '';
      document.querySelector('#pago-observaciones').value = '';
      
      // Recargar lista principal
      await loadList();
      
      console.log('Pago registrado OK', result);
    } catch (e) {
      console.error('save pago error:', e);
      if (e.data) {
        console.error('Error data:', e.data);
        if (e.data.errors) {
          alert('Error de validación:\n' + e.data.errors.join('\n'));
        } else if (e.data.error) {
          alert('Error: ' + e.data.error + (e.data.detail ? '\n' + e.data.detail : ''));
        } else {
          alert('Error registrando el pago. Ver consola para detalles.');
        }
      } else {
        alert('Error registrando el pago');
      }
    }
  }

  async function delPago(pagoId) {
    if (!confirm('¿Eliminar este pago?')) return;
    
    try {
      await fetchJSON(API.pagos.del(pagoId), { method: 'POST' });
      
      // Recargar trabajo
      const t = await fetchJSON(API.get(currentTrabajoId));
      renderPagos(t.pagos || []);
      document.querySelector('#pago-trabajo-saldo').textContent = `${t.moneda} ${money(t.saldo)}`;
      
      await loadList();
    } catch (e) {
      alert('No se pudo eliminar el pago');
      console.error('delete pago error', e);
    }
  }

  // ==================== EVENTOS ====================

  // Función para generar PDF
  async function generarPDF(trabajoId) {
    try {
      // Abrir en nueva ventana/pestaña
      window.open(`/api/trabajos/pdf.php?id=${trabajoId}`, '_blank');
    } catch (e) {
      console.error('Error generando PDF', e);
      alert('Error al generar el PDF');
    }
  }

  // Delegación global
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-trabajo')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-trabajo')) {
      ev.preventDefault();
      saveTrabajo();
      return;
    }

    if (ev.target.closest('#btn-add-pago')) {

      ev.preventDefault();
      savePago();
      return;
    }

    // Botones de la tabla
    const tr = ev.target.closest('#trabajos-tbody tr');
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
        delTrabajo(id);
        return;
      }

      if (ev.target.closest('.btn-pagos')) {
        ev.preventDefault();
        openPagos(id);
        return;
      }

      if (ev.target.closest('.btn-pdf')) {
        ev.preventDefault();
        generarPDF(id);
        return;
      }
    }

    // Eliminar pago
    const btnDelPago = ev.target.closest('.btn-del-pago');
    if (btnDelPago) {
      ev.preventDefault();
      const pagoId = Number(btnDelPago.dataset.pagoId || 0);
      if (pagoId) delPago(pagoId);
      return;
    }

    // Toggle filtro saldo pendiente
    if (ev.target.closest('#filter-saldo-pendiente')) {
      ev.preventDefault();
      const btn = ev.target.closest('#filter-saldo-pendiente');
      btn.classList.toggle('active');
      btn.classList.toggle('btn-outline-light');
      btn.classList.toggle('btn-light');
      loadList();
      return;
    }
  });

  // Toggle campos de homologación
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    
    if (ev.target.id === 'trabajo-requiere-homologacion') {
      toggleHomologacionFields();
    }

    // Filtros
    if (ev.target.id === 'filter-estado' || ev.target.id === 'filter-prioridad') {
      loadList();
    }
  });

  // Búsqueda en tiempo real
  let searchTimeout;
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    
    if (ev.target.id === 'trabajos-search') {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadList(), 300);
    }
  });

  // Bootstrap inicial con debounce
  let bootTimeout;
  const bootList = () => {
    if (!here()) return;
    
    // Debounce para evitar múltiples llamadas en rápida sucesión
    clearTimeout(bootTimeout);
    bootTimeout = setTimeout(() => {
      loadList();
    }, 100);
  };

  // CORRECCIÓN: Solo se usa un evento de navegación
  // Se removió 'hashchange' y 'DOMContentLoaded' para evitar llamadas duplicadas
  // Solo se escucha 'orion:navigate' que es el evento principal de navegación
  document.addEventListener('orion:navigate', bootList);
  
  // Si el módulo ya está cargado al momento de ejecutar el script
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootList);
  } else if (here()) {
    bootList();
  }
})();