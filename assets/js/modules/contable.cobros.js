// assets/js/modules/contable.cobros.js
(() => {
  const API = {
    list: '/api/contable/cobros/list.php',
    get: id => `/api/contable/cobros/get.php?id=${id}`,
    save: '/api/contable/cobros/save.php',
    del: id => `/api/contable/cobros/delete.php?id=${id}`,
    trabajosPendientes: '/api/contable/cobros/trabajos-pendientes.php',
    clientesSelect: '/api/clientes/select.php',
    trabajosSelect: '/api/trabajos/select.php',
    serviciosSelect: '/api/parameters/servicios/select.php',
    proforma: '/api/contable/cobros/proforma.php',
    emailProforma: '/api/contable/cobros/email_proforma.php'
  };

  let itemsWrap = null;
  let clientesData = [];
  let trabajosData = [];
  let serviciosData = [];

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/contable/cobros';
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
    'pendiente': '<span class="badge bg-warning text-dark">Pendiente</span>',
    'parcial': '<span class="badge bg-info">Parcial</span>',
    'pagado': '<span class="badge bg-success">Pagado</span>',
    'vencido': '<span class="badge bg-danger">Vencido</span>',
    'cancelado': '<span class="badge bg-secondary">Cancelado</span>'
  };

  const tipoBadge = {
    'trabajo': '<span class="badge bg-primary">Trabajo</span>',
    'servicio': '<span class="badge bg-info">Servicio</span>',
    'otro': '<span class="badge bg-secondary">Otro</span>'
  };

  function rowTpl(c) {
    const saldo = Number(c.saldo || 0);
    const saldoClass = saldo > 0 ? 'text-danger' : 'text-success';
    
    return `<tr data-id="${c.id}">
      <td>${c.id}</td>
      <td class="fw-semibold">${c.codigo}</td>
      <td>${c.numero_factura || '-'}</td>
      <td>${c.cliente_nombre || '-'}</td>
      <td>${tipoBadge[c.tipo] || c.tipo}</td>
      <td>${c.concepto}</td>
      <td class="text-end fw-bold">${c.moneda} ${money(c.total)}</td>
      <td class="text-end">${c.moneda} ${money(c.monto_pagado)}</td>
      <td class="text-end ${saldoClass}">${c.moneda} ${money(saldo)}</td>
      <td>${formatDate(c.fecha_emision)}</td>
      <td>${estadoBadge[c.estado] || c.estado}</td>
      <td class="text-end">
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear-wide"></i>
                </a>
                <ul class="dropdown-menu border-0 text-end bg-transparent">
                    <li>
                        <button class="btn btn-sm btn-primary me-1 btn-edit" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-danger mt-1 btn-del" title="Eliminar"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-sm btn-info mt-1 btn-ver-detalle"><i class="bi bi-eye me-1"></i></button>
                    </li>
                <ul>
            </li>
        <ul>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#cobros-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="12" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) params.set('estado', filterEstado.value);
      
      const filterTipo = document.querySelector('#filter-tipo');
      if (filterTipo && filterTipo.value) params.set('tipo', filterTipo.value);

      const q = document.querySelector('#cobros-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="12" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Cobros list error', e);
      tbody.innerHTML = `<tr><td colspan="12" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function loadSelects() {
    try {
      [clientesData, trabajosData, serviciosData] = await Promise.all([
        fetchJSON(API.clientesSelect).catch(() => []),
        fetchJSON(API.trabajosSelect).catch(() => []),
        fetchJSON(API.serviciosSelect).catch(() => [])
      ]);

      // Clientes
      const selectCliente = document.querySelector('#cobro-cliente-id');
      if (selectCliente) {
        selectCliente.innerHTML = '<option value="">-- Sin cliente --</option>';
        clientesData.forEach(c => {
          const displayName = c.nombre_display || c.razon_social || c.contacto_nombre || `Cliente ${c.id}`;
          selectCliente.innerHTML += `<option value="${c.id}">${displayName}</option>`;
        });
      }

      // Trabajos
      const selectTrabajo = document.querySelector('#cobro-trabajo-id');
      if (selectTrabajo) {
        selectTrabajo.innerHTML = '<option value="">-- Sin trabajo asociado --</option>';
        trabajosData.forEach(t => {
          selectTrabajo.innerHTML += `<option value="${t.id}">${t.codigo} - ${t.nombre}</option>`;
        });
      }

      // Servicios
      const selectServicio = document.querySelector('#cobro-servicio-id');
      if (selectServicio) {
        selectServicio.innerHTML = '<option value="">-- Sin servicio asociado --</option>';
        serviciosData.forEach(s => {
          selectServicio.innerHTML += `<option value="${s.id}">${s.codigo} - ${s.nombre}</option>`;
        });
      }
    } catch (e) {
      console.error('Error cargando selects', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    
    set('#cobro-id', '0');
    set('#cobro-codigo', '');
    set('#cobro-numero-factura', '');
    set('#cobro-cliente-id', '');
    set('#cobro-tipo', 'trabajo');
    set('#cobro-concepto', '');
    set('#cobro-trabajo-id', '');
    set('#cobro-servicio-id', '');
    set('#cobro-subtotal', '0');
    set('#cobro-descuento', '0');
    set('#cobro-impuestos', '0');
    set('#cobro-total', '0');
    set('#cobro-moneda', 'ARS');
    set('#cobro-fecha-emision', new Date().toISOString().split('T')[0]);
    set('#cobro-fecha-vencimiento', '');
    set('#cobro-estado', 'pendiente');
    set('#cobro-afip-cae', '');
    set('#cobro-afip-vencimiento-cae', '');
    set('#cobro-afip-tipo', '');
    set('#cobro-afip-punto-venta', '');
    set('#cobro-observaciones', '');
    set('#cobro-orden', '0');

    if (itemsWrap) itemsWrap.innerHTML = '';
  }

  function itemRow(item = {}) {
    return `<div class="row g-2 align-items-end border rounded p-2 mb-2" data-item>
      <div class="col-5">
        <label class="form-label">Descripción *</label>
        <input type="text" class="form-control" value="${item.descripcion || ''}" data-f="descripcion">
      </div>
      <div class="col-2">
        <label class="form-label">Cantidad</label>
        <input type="number" step="0.01" class="form-control text-end" value="${item.cantidad || 1}" data-f="cantidad">
      </div>
      <div class="col-2">
        <label class="form-label">Precio Unit.</label>
        <input type="number" step="0.01" class="form-control text-end" value="${item.precio_unitario || 0}" data-f="precio_unitario">
      </div>
      <div class="col-2">
        <label class="form-label">IVA %</label>
        <input type="number" step="0.01" class="form-control text-end" value="${item.alicuota_iva || 0}" data-f="alicuota_iva">
      </div>
      <div class="col-1 text-end">
        <button class="btn btn-outline-danger" type="button" data-action="remove-item">&times;</button>
      </div>
    </div>`;
  }

  function collectItems() {
    return Array.from(document.querySelectorAll('#items-list [data-item]')).map(row => ({
      descripcion: row.querySelector('[data-f="descripcion"]')?.value.trim() || '',
      cantidad: Number(row.querySelector('[data-f="cantidad"]')?.value || 1),
      precio_unitario: Number(row.querySelector('[data-f="precio_unitario"]')?.value || 0),
      alicuota_iva: Number(row.querySelector('[data-f="alicuota_iva"]')?.value || 0),
    })).filter(i => i.descripcion !== '');
  }

  function calcularTotal() {
    const subtotal = Number(document.querySelector('#cobro-subtotal')?.value || 0);
    const descuento = Number(document.querySelector('#cobro-descuento')?.value || 0);
    const impuestos = Number(document.querySelector('#cobro-impuestos')?.value || 0);
    const total = subtotal - descuento + impuestos;
    
    const elTotal = document.querySelector('#cobro-total');
    if (elTotal) elTotal.value = total.toFixed(2);
  }

  async function openForm(id = 0) {
    if (!here()) return;
    itemsWrap = document.getElementById('items-list');
    clearForm();
    await loadSelects();

    if (id === 0) {
      // Nuevo cobro
      calcularTotal();
    } else {
      // Editar cobro existente
      try {
        const c = await fetchJSON(API.get(id));
        
        document.querySelector('#cobro-id').value = c.id;
        document.querySelector('#cobro-codigo').value = c.codigo || '';
        document.querySelector('#cobro-numero-factura').value = c.numero_factura || '';
        document.querySelector('#cobro-cliente-id').value = c.cliente_id || '';
        document.querySelector('#cobro-tipo').value = c.tipo || 'trabajo';
        document.querySelector('#cobro-concepto').value = c.concepto || '';
        document.querySelector('#cobro-trabajo-id').value = c.trabajo_id || '';
        document.querySelector('#cobro-servicio-id').value = c.servicio_id || '';
        document.querySelector('#cobro-subtotal').value = c.subtotal || 0;
        document.querySelector('#cobro-descuento').value = c.descuento || 0;
        document.querySelector('#cobro-impuestos').value = c.impuestos || 0;
        document.querySelector('#cobro-total').value = c.total || 0;
        document.querySelector('#cobro-moneda').value = c.moneda || 'ARS';
        document.querySelector('#cobro-fecha-emision').value = c.fecha_emision || '';
        document.querySelector('#cobro-fecha-vencimiento').value = c.fecha_vencimiento || '';
        document.querySelector('#cobro-estado').value = c.estado || 'pendiente';
        document.querySelector('#cobro-afip-cae').value = c.afip_cae || '';
        document.querySelector('#cobro-afip-vencimiento-cae').value = c.afip_vencimiento_cae || '';
        document.querySelector('#cobro-afip-tipo').value = c.afip_tipo_comprobante || '';
        document.querySelector('#cobro-afip-punto-venta').value = c.afip_punto_venta || '';
        document.querySelector('#cobro-observaciones').value = c.observaciones || '';
        document.querySelector('#cobro-orden').value = c.orden || 0;

        // Cargar items
        (c.items || []).forEach(item => {
          itemsWrap.insertAdjacentHTML('beforeend', itemRow(item));
        });

      } catch (e) {
        console.error('get cobro error', e);
        alert('Error al cargar el cobro');
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-cobro');
      m.show();
    }
  }

  let saving = false;
  async function saveCobro() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-cobro');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const payload = {
      id: Number(document.querySelector('#cobro-id')?.value || 0),
      codigo: (document.querySelector('#cobro-codigo')?.value || '').trim(),
      numero_factura: (document.querySelector('#cobro-numero-factura')?.value || '').trim() || null,
      cliente_id: document.querySelector('#cobro-cliente-id')?.value || null,
      tipo: document.querySelector('#cobro-tipo')?.value || 'trabajo',
      concepto: (document.querySelector('#cobro-concepto')?.value || '').trim(),
      trabajo_id: document.querySelector('#cobro-trabajo-id')?.value || null,
      servicio_id: document.querySelector('#cobro-servicio-id')?.value || null,
      subtotal: Number(document.querySelector('#cobro-subtotal')?.value || 0),
      descuento: Number(document.querySelector('#cobro-descuento')?.value || 0),
      impuestos: Number(document.querySelector('#cobro-impuestos')?.value || 0),
      moneda: document.querySelector('#cobro-moneda')?.value || 'ARS',
      fecha_emision: document.querySelector('#cobro-fecha-emision')?.value || null,
      fecha_vencimiento: document.querySelector('#cobro-fecha-vencimiento')?.value || null,
      estado: document.querySelector('#cobro-estado')?.value || 'pendiente',
      monto_pagado: 0, // Se calcula desde pagos
      afip_cae: (document.querySelector('#cobro-afip-cae')?.value || '').trim() || null,
      afip_vencimiento_cae: document.querySelector('#cobro-afip-vencimiento-cae')?.value || null,
      afip_tipo_comprobante: document.querySelector('#cobro-afip-tipo')?.value || null,
      afip_punto_venta: document.querySelector('#cobro-afip-punto-venta')?.value || null,
      observaciones: (document.querySelector('#cobro-observaciones')?.value || '').trim() || null,
      orden: Number(document.querySelector('#cobro-orden')?.value || 0),
      items: collectItems()
    };

    if (!payload.concepto) {
      alert('El concepto es obligatorio');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    if (!payload.fecha_emision) {
      alert('La fecha de emisión es obligatoria');
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

      const modalEl = document.querySelector('#modal-cobro');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el cobro');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delCobro(id) {
    if (!confirm('¿Eliminar el cobro? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo borrar. Puede tener pagos asociados.');
      console.error('delete error', e);
    }
  }

  // Importar trabajos pendientes
  async function openImportarTrabajos() {
    try {
      const trabajos = await fetchJSON(API.trabajosPendientes);
      const list = document.querySelector('#trabajos-pendientes-list');
      
      if (!list) return;

      if (!trabajos || trabajos.length === 0) {
        list.innerHTML = '<p class="text-muted text-center p-3">No hay pagos de trabajos pendientes de facturar</p>';
        
        // Deshabilitar botón de crear
        const btnCrear = document.querySelector('#btn-crear-cobro-trabajos');
        if (btnCrear) btnCrear.disabled = true;
      } else {
        list.innerHTML = trabajos.map(t => `
          <label class="list-group-item d-flex gap-3 align-items-center">
            <input type="checkbox" class="form-check-input flex-shrink-0" value="${t.pago_id}" data-trabajo='${JSON.stringify(t)}'>
            <div class="flex-grow-1">
              <div class="fw-semibold">${t.trabajo_codigo} - ${t.trabajo_nombre}</div>
              <small class="text-muted">${t.cliente_nombre || 'Sin cliente'} · ${formatDate(t.fecha_pago)}</small>
            </div>
            <div class="text-end">
              <div class="fw-bold">${t.moneda} ${money(t.monto)}</div>
              <small class="text-muted">${t.medio_pago}</small>
            </div>
          </label>
        `).join('');
        
        // Habilitar botón de crear
        const btnCrear = document.querySelector('#btn-crear-cobro-trabajos');
        if (btnCrear) btnCrear.disabled = false;
      }

      if (window.bootstrap?.Modal) {
        const m = new bootstrap.Modal('#modal-importar-trabajos');
        m.show();
      }
    } catch (e) {
      console.error('Error cargando trabajos pendientes', e);
      alert('Error al cargar trabajos pendientes');
    }
  }

  async function crearCobroDesdeTrabajos() {
    const checks = document.querySelectorAll('#trabajos-pendientes-list input[type="checkbox"]:checked');
    
    if (checks.length === 0) {
      alert('Seleccione al menos un pago de trabajo');
      return;
    }

    const btnCrear = document.querySelector('#btn-crear-cobro-trabajos');
    if (btnCrear) {
      btnCrear.disabled = true;
      btnCrear.dataset.original = btnCrear.innerHTML;
      btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creando...';
    }

    try {
      // Recopilar datos de pagos seleccionados
      const trabajos = Array.from(checks).map(c => JSON.parse(c.dataset.trabajo));
      
      // Crear un cobro por cada pago seleccionado
      let creados = 0;
      let errores = 0;

      for (const trabajo of trabajos) {
        try {
          const subtotal = trabajo.monto;
          
          const payload = {
            id: 0,
            codigo: '', // Auto-generado
            numero_factura: trabajo.comprobante_numero || null,
            cliente_id: trabajo.cliente_id || null,
            tipo: 'trabajo',
            concepto: `Pago trabajo ${trabajo.trabajo_codigo}${trabajo.trabajo_nombre ? ' - ' + trabajo.trabajo_nombre : ''}${trabajo.referencia ? ' (Ref: ' + trabajo.referencia + ')' : ''}`,
            trabajo_id: trabajo.trabajo_id,
            servicio_id: null,
            subtotal: subtotal,
            descuento: 0,
            impuestos: 0,
            moneda: trabajo.moneda,
            fecha_emision: trabajo.fecha_pago,
            fecha_vencimiento: null,
            estado: 'pagado',
            monto_pagado: subtotal,
            observaciones: 'Importado desde trabajos',
            orden: 0,
            items: [
              {
                descripcion: `Pago trabajo ${trabajo.trabajo_codigo}${trabajo.medio_pago ? ' - ' + trabajo.medio_pago : ''}${trabajo.referencia ? ' (Ref: ' + trabajo.referencia + ')' : ''}`,
                cantidad: 1,
                precio_unitario: subtotal,
                alicuota_iva: 0,
                trabajo_pago_id: trabajo.pago_id
              }
            ]
          };

          await fetchJSON(API.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });

          creados++;
        } catch (e) {
          console.error('Error creando cobro:', e);
          errores++;
        }
      }

      // Cerrar modal
      const modalImportar = document.querySelector('#modal-importar-trabajos');
      if (modalImportar && window.bootstrap?.Modal) {
        const instance = bootstrap.Modal.getInstance(modalImportar);
        if (instance) instance.hide();
      }

      // Recargar listado
      await loadList();

      // Mensaje de resultado
      if (errores === 0) {
        alert(`✅ Se importaron ${creados} pagos exitosamente como cobros`);
      } else {
        alert(`⚠️ Se importaron ${creados} pagos. ${errores} fallaron.`);
      }

    } catch (e) {
      console.error('Error en importación:', e);
      alert('Error durante la importación');
    } finally {
      if (btnCrear) {
        btnCrear.disabled = false;
        btnCrear.innerHTML = btnCrear.dataset.original || 'Crear Cobros';
      }
    }
  }
  // Ver detalle del cobro
  async function verDetalle(id) {
    try {
      const cobro = await fetchJSON(API.get(id));
      currentCobro = cobro;

      const content = document.querySelector('#cobro-detalle-content');
      if (!content) return;

      const isProforma = cobro.observaciones && cobro.observaciones.includes('[PROFORMA');

      content.innerHTML = `
        <div class="row g-3">
          ${isProforma ? `
          <div class="col-12">
            <div class="alert alert-info mb-0">
              <i class="bi bi-info-circle me-2"></i>
              <strong>Proforma:</strong> Este cobro está pendiente de facturación oficial.
            </div>
          </div>
          ` : ''}
          
          <div class="col-md-6">
            <h6 class="text-muted mb-2">Información General</h6>
            <table class="table table-sm">
              <tr>
                <td class="text-muted">Código:</td>
                <td class="fw-semibold">${cobro.codigo}</td>
              </tr>
              ${cobro.numero_factura ? `
              <tr>
                <td class="text-muted">Nro. Factura:</td>
                <td class="fw-semibold">${cobro.numero_factura}</td>
              </tr>
              ` : ''}
              <tr>
                <td class="text-muted">Tipo:</td>
                <td><span class="badge bg-secondary">${cobro.tipo}</span></td>
              </tr>
              <tr>
                <td class="text-muted">Estado:</td>
                <td>${estadoBadge[cobro.estado] || cobro.estado}</td>
              </tr>
            </table>
          </div>

          <div class="col-md-6">
            <h6 class="text-muted mb-2">Cliente</h6>
            <table class="table table-sm">
              <tr>
                <td class="text-muted">Nombre:</td>
                <td class="fw-semibold">${cobro.cliente_nombre}</td>
              </tr>
              ${cobro.cliente_doc ? `
              <tr>
                <td class="text-muted">Documento:</td>
                <td>${cobro.cliente_doc}</td>
              </tr>
              ` : ''}
              ${cobro.cliente_email ? `
              <tr>
                <td class="text-muted">Email:</td>
                <td>${cobro.cliente_email}</td>
              </tr>
              ` : ''}
            </table>
          </div>

          <div class="col-12">
            <h6 class="text-muted mb-2">Concepto</h6>
            <p class="mb-0">${cobro.concepto}</p>
          </div>

          <div class="col-12">
            <h6 class="text-muted mb-2">Montos</h6>
            <table class="table table-sm">
              <tr>
                <td class="text-muted">Subtotal:</td>
                <td class="text-end fw-semibold">$${money(cobro.subtotal)}</td>
              </tr>
              ${cobro.descuento > 0 ? `
              <tr>
                <td class="text-muted">Descuento:</td>
                <td class="text-end text-danger">-$${money(cobro.descuento)}</td>
              </tr>
              ` : ''}
              ${cobro.impuestos > 0 ? `
              <tr>
                <td class="text-muted">Impuestos:</td>
                <td class="text-end">$${money(cobro.impuestos)}</td>
              </tr>
              ` : ''}
              <tr class="fw-bold">
                <td>Total:</td>
                <td class="text-end">$${money(cobro.total)} ${cobro.moneda}</td>
              </tr>
              <tr>
                <td class="text-muted">Pagado:</td>
                <td class="text-end text-success">$${money(cobro.monto_pagado)}</td>
              </tr>
              <tr class="fw-bold">
                <td>Saldo:</td>
                <td class="text-end ${cobro.saldo > 0 ? 'text-danger' : 'text-success'}">
                  $${money(cobro.saldo)}
                </td>
              </tr>
            </table>
          </div>

          <div class="col-12">
            <h6 class="text-muted mb-2">Fechas</h6>
            <table class="table table-sm">
              <tr>
                <td class="text-muted">Emisión:</td>
                <td>${formatDate(cobro.fecha_emision)}</td>
              </tr>
              ${cobro.fecha_vencimiento ? `
              <tr>
                <td class="text-muted">Vencimiento:</td>
                <td>${formatDate(cobro.fecha_vencimiento)}</td>
              </tr>
              ` : ''}
            </table>
          </div>

          ${cobro.observaciones ? `
          <div class="col-12">
            <h6 class="text-muted mb-2">Observaciones</h6>
            <div class="border rounded p-2">
              <small>${cobro.observaciones.replace(/\n/g, '<br>')}</small>
            </div>
          </div>
          ` : ''}
        </div>
      `;

      // Mostrar modal
      if (window.bootstrap?.Modal) {
        const modal = new bootstrap.Modal('#modal-cobro-detalle');
        modal.show();
      }
    } catch (e) {
      console.error('Error cargando detalle', e);
      alert('Error cargando el detalle del cobro');
    }
  }

  // Generar y descargar proforma en PDF
  async function downloadProforma() {
    if (!currentCobro) {
      alert('No hay un cobro seleccionado');
      return;
    }

    try {
      const url = `${API.proforma}?id=${currentCobro.id}&action=download`;
      
      // Usar window.open para evitar el bloqueo del router
      // El navegador se encargará de la descarga automáticamente
      window.open(url, '_blank');
      
    } catch (e) {
      console.error('Error descargando proforma', e);
      alert('Error generando la proforma. Intente nuevamente.');
    }
  }
  // Enviar proforma por email
  async function emailProforma() {
    if (!currentCobro) {
      alert('No hay un cobro seleccionado');
      return;
    }

    if (!currentCobro.cliente_email) {
      alert('El cliente no tiene email registrado');
      return;
    }

    if (!confirm(`¿Enviar proforma a ${currentCobro.cliente_email}?`)) {
      return;
    }

    try {
      const result = await fetchJSON(API.emailProforma, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentCobro.id })
      });

      if (result.ok) {
        alert('Proforma enviada exitosamente');
      } else {
        alert('Error enviando la proforma: ' + (result.error || 'Error desconocido'));
      }
    } catch (e) {
      console.error('Error enviando proforma', e);
      alert('Error enviando la proforma. Intente nuevamente.');
    }
  }

// Función para enviar proforma por WhatsApp
// Permite editar el número antes de enviar

function whatsappProforma() {
  if (!currentCobro) {
    alert('No hay un cobro seleccionado');
    return;
  }

  // Obtener teléfono del cliente (puede estar en telefono o celular)
  let phone = currentCobro.cliente_celular || currentCobro.cliente_telefono || '';
  phone = phone.replace(/[^0-9]/g, ''); // Limpiar: solo números

  // SIEMPRE mostrar prompt para editar/confirmar el número
  const numeroIngresado = prompt(
    `Número de WhatsApp (con código de país):\n\n` +
    `Cliente: ${currentCobro.cliente_nombre || 'Sin nombre'}\n` +
    `Proforma: ${currentCobro.codigo}\n\n` +
    `Ejemplo: ${phone}`,
    phone // Valor por defecto (número del cliente)
  );

  // Si canceló, salir
  if (numeroIngresado === null) {
    return;
  }

  // Limpiar el número ingresado
  phone = numeroIngresado.trim().replace(/[^0-9]/g, '');

  // Validar que tenga contenido
  if (!phone) {
    alert('Debe ingresar un número de teléfono');
    return;
  }

  // Validar longitud mínima
  if (phone.length < 10) {
    alert('El número de teléfono es demasiado corto.\nVerifique que incluya el código de país.');
    return;
  }

  // URL completa del PDF
  const pdfUrl = window.location.origin + `/api/contable/cobros/proforma.php?id=${currentCobro.id}&action=download`;
  
  // Generar mensaje
  const mensaje = encodeURIComponent(
    `Hola! Le enviamos la proforma ${currentCobro.codigo}\n\n` +
    `Cliente: ${currentCobro.cliente_nombre || ''}\n` +
    `Concepto: ${currentCobro.concepto || ''}\n` +
    `Total: $${money(currentCobro.total)} ${currentCobro.moneda}\n\n` +
    `Puede descargar el PDF desde:\n${pdfUrl}`
  );

  // Abrir WhatsApp con el número editado
  const url = `https://wa.me/${phone}?text=${mensaje}`;
  window.open(url, '_blank');
}

// Función auxiliar para formatear moneda (si no existe en el scope)
function money(amount) {
  if (!amount && amount !== 0) return '0.00';
  return Number(amount).toLocaleString('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-cobro')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-cobro')) {
      ev.preventDefault();
      saveCobro();
      return;
    }


    if (ev.target.closest('#btn-crear-cobro-trabajos')) {
      ev.preventDefault();
      crearCobroDesdeTrabajos();
      return;
    }

    const tr = ev.target.closest('#cobros-tbody tr');
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
        delCobro(id);
        return;
      }
    }

    if (ev.target.closest('[data-action="remove-item"]')) {
      ev.preventDefault();
      ev.target.closest('[data-item]')?.remove();
      calcularTotal();
    }

    if (ev.target.closest('#btn-add-item')) {
      ev.preventDefault();
      itemsWrap = document.getElementById('items-list');
      itemsWrap?.insertAdjacentHTML('beforeend', itemRow());
    }
    // Botón refrescar
    if (ev.target.closest('#btn-refresh-cobros')) {
      ev.preventDefault();
      loadList();
      return;
    }

    // Botón ver detalle
    const btnDetalle = ev.target.closest('.btn-ver-detalle');
    if (btnDetalle) {
      ev.preventDefault();
      const tr = btnDetalle.closest('tr');
      if (tr) {
        const id = Number(tr.dataset.id || 0);
        if (id) verDetalle(id);
      }
      return;
    }

    // Botones de proforma
    if (ev.target.closest('#btn-proforma-download')) {
      ev.preventDefault();
      downloadProforma();
      return;
    }

    if (ev.target.closest('#btn-proforma-email')) {
      ev.preventDefault();
      emailProforma();
      return;
    }

    if (ev.target.closest('#btn-proforma-whatsapp')) {
      ev.preventDefault();
      whatsappProforma();
      return;
    }


  });

  // Recalcular total cuando cambien importes
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    
    const id = ev.target.id;
    if (id === 'cobro-subtotal' || id === 'cobro-descuento' || id === 'cobro-impuestos') {
      calcularTotal();
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'cobros-search') {
      loadList();
    }
  });

  // Filtros
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado' || ev.target.id === 'filter-tipo') {
      loadList();
    }
  });

  // Bootstrap inicial
  const bootList = () => {
    if (here()) {
      itemsWrap = document.getElementById('items-list');
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();