// assets/js/modules/contable.servicios.js
(() => {
  const API = {
    list: '/api/contable/servicios/list.php',
    getCobro: (clienteId, servicioId) => `/api/contable/servicios/get_cobro.php?cliente_id=${clienteId}&servicio_id=${servicioId}`,
    saveCobro: '/api/contable/servicios/save_cobro.php',
    getCotizacion: (fecha) => `/api/core/moneda_cotizacion.php?fecha=${fecha}`
  };

  let currentRow = null;

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/contable/servicios';
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
    //return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return v;
  }

  function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('es-AR');
  }

  function todayISO() {
    const d = new Date();
    return d.toISOString().split('T')[0];
  }

  const tipoCobroBadge = {
    'mensual': '<span class="badge bg-primary">Mensual</span>',
    'anual': '<span class="badge bg-info">Anual</span>'
  };

  const estadoBadge = {
    'pendiente': '<span class="badge bg-warning text-dark">Pendiente</span>',
    'parcial': '<span class="badge bg-info">Parcial</span>',
    'pagado': '<span class="badge bg-success">Pagado</span>',
    'vencido': '<span class="badge bg-danger">Vencido</span>',
    'cancelado': '<span class="badge bg-secondary">Cancelado</span>'
  };

  function rowTpl(item) {
    const ultimoCobro = item.ultimo_cobro_fecha 
      ? `<div>${formatDate(item.ultimo_cobro_fecha)}</div><small class="text-muted">${estadoBadge[item.ultimo_cobro_estado] || ''}</small>`
      : '<span class="text-muted">Sin cobros</span>';

    const btnText = item.ultimo_cobro_id 
      ? '<i class="bi bi-pencil me-1"></i>Editar Cobro'
      : '<i class="bi bi-plus-circle me-1"></i>Crear Cobro';

    return `<tr data-cliente-id="${item.cliente_id}" data-servicio-id="${item.servicio_id}">
      <td>
        <div class="fw-semibold">${item.cliente_nombre}</div>
        <small class="text-muted">${item.cliente_doc}</small>
      </td>
      <td>
        <div>${item.servicio_nombre}</div>
        <small class="text-muted">${item.servicio_codigo}</small>
      </td>
      <td class="text-end fw-bold">$${money(item.precio_usd)}</td>
      <td class="text-center">${tipoCobroBadge[item.tipo_cobro] || item.tipo_cobro}</td>
      <td class="text-center">${ultimoCobro}</td>
      <td class="text-center">
        ${estadoBadge[item.servicio_estado] || item.servicio_estado}
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-primary btn-crear-cobro">
          ${btnText}
        </button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#servicios-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const q = document.querySelector('#servicios-search')?.value || '';
      const url = API.list + (q ? '?q=' + encodeURIComponent(q) : '');
      const data = await fetchJSON(url);

      if (data && data.length > 0) {
        tbody.innerHTML = data.map(rowTpl).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4 text-muted">No hay clientes con servicios activos</td></tr>`;
      }
    } catch (e) {
      console.error('Error cargando listado', e);
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function getCotizacion(fecha) {
    try {
      const data = await fetchJSON(API.getCotizacion(fecha));
      return data?.cotizacion || 0;
    } catch (e) {
      console.error('Error obteniendo cotización', e);
      return 0;
    }
  }

  function clearForm() {
    const set = (sel, val) => {
      const el = document.querySelector(sel);
      if (el) el.value = val;
    };
    
      var hoy = new Date();
      var anio = hoy.getFullYear() % 100;
      var mes = String(hoy.getMonth() + 1);
      var random = Math.floor(100000 + Math.random() * 900000);
      var numeroFactura = anio+mes+'-'+random;

    set('#cobro-id', '0');
    set('#cobro-cliente-id', '');
    set('#cobro-servicio-id', '');
    set('#cobro-codigo', '');
    set('#cobro-numero-factura', numeroFactura);
    set('#cobro-concepto', '');
    set('#cobro-fecha-emision', todayISO());
    set('#cobro-fecha-vencimiento', new Date(new Date().getFullYear(), new Date().getMonth() + 1, 10).toISOString().split('T')[0]);
    set('#cobro-estado', 'pendiente');
    set('#cobro-precio-usd', '');
    set('#cobro-cotizacion', '');
    set('#cobro-subtotal', '0');
    set('#cobro-descuento', '0');
    set('#cobro-impuestos', '0');
    set('#cobro-total', '0');
    set('#cobro-observaciones', '');

    // Info visual
    document.querySelector('#info-cliente-nombre').textContent = '-';
    document.querySelector('#info-cliente-doc').textContent = '-';
    document.querySelector('#info-servicio-nombre').textContent = '-';
    document.querySelector('#info-tipo-cobro').textContent = '-';
  }

  function calcularTotal() {
    const subtotal = parseFloat(document.querySelector('#cobro-subtotal')?.value || 0);
    const descuento = parseFloat(document.querySelector('#cobro-descuento')?.value || 0);
    const impuestos = parseFloat(document.querySelector('#cobro-impuestos')?.value || 0);
    const total = subtotal - descuento + impuestos;
    document.querySelector('#cobro-total').value = total.toFixed(2);
  }

  async function openForm(row) {
    if (!here()) return;

    currentRow = row;
    const clienteId = parseInt(row.dataset.clienteId);
    const servicioId = parseInt(row.dataset.servicioId);

    clearForm();

    // Obtener datos del row
    const clienteNombre = row.querySelector('td:nth-child(1) .fw-semibold')?.textContent || '';
    const clienteDoc = row.querySelector('td:nth-child(1) small')?.textContent || '';
    const servicioNombre = row.querySelector('td:nth-child(2) div')?.textContent || '';
    const precioUSD = row.querySelector('td:nth-child(3)')?.textContent.replace('$', '').replace(/,/g, '') || '0';
    const tipoCobro = row.querySelector('td:nth-child(4)')?.textContent.trim() || 'mensual';

    // Llenar info visual
    document.querySelector('#info-cliente-nombre').textContent = clienteNombre;
    document.querySelector('#info-cliente-doc').textContent = clienteDoc;
    document.querySelector('#info-servicio-nombre').textContent = servicioNombre;
    document.querySelector('#info-tipo-cobro').textContent = tipoCobro;

    // Campos hidden
    document.querySelector('#cobro-cliente-id').value = clienteId;
    document.querySelector('#cobro-servicio-id').value = servicioId;

    // Obtener cotización del día
    const fechaHoy = todayISO();
    document.querySelector('#cobro-fecha-emision').value = fechaHoy;
    
    const cotizacion = await getCotizacion(fechaHoy);
    document.querySelector('#cobro-cotizacion').value = money(cotizacion);
    document.querySelector('#cobro-precio-usd').value = money(precioUSD);

    // Calcular subtotal en ARS
    const precioUSDNum = parseFloat(precioUSD);
    const subtotalARS = (precioUSDNum * cotizacion).toFixed(2);
    document.querySelector('#cobro-subtotal').value = subtotalARS;
    
    // Generar observaciones automáticas
    const obsAuto = `Monto en dólares: $${money(precioUSDNum)} USD - Cotización del día: $${money(cotizacion)} x dólar`;
    document.querySelector('#cobro-observaciones').value = obsAuto;

    // Concepto por defecto
    document.querySelector('#cobro-concepto').value = `Servicio: ${servicioNombre}`;

    // Calcular total
    calcularTotal();

    // Intentar cargar cobro existente
    try {
      const cobro = await fetchJSON(API.getCobro(clienteId, servicioId));
      
      if (cobro) {
        document.querySelector('#modal-title-text').textContent = 'Editar Cobro';
        document.querySelector('#cobro-id').value = cobro.id;
        document.querySelector('#cobro-codigo').value = cobro.codigo || '';
        document.querySelector('#cobro-numero-factura').value = cobro.numero_factura || '';
        document.querySelector('#cobro-concepto').value = cobro.concepto || '';
        document.querySelector('#cobro-fecha-emision').value = cobro.fecha_emision || '';
        document.querySelector('#cobro-fecha-vencimiento').value = cobro.fecha_vencimiento || '';
        document.querySelector('#cobro-estado').value = cobro.estado || 'pendiente';
        document.querySelector('#cobro-subtotal').value = cobro.subtotal || '0';
        document.querySelector('#cobro-descuento').value = cobro.descuento || '0';
        document.querySelector('#cobro-impuestos').value = cobro.impuestos || '0';
        document.querySelector('#cobro-total').value = cobro.total || '0';
        document.querySelector('#cobro-observaciones').value = cobro.observaciones || '';
      } else {
        document.querySelector('#modal-title-text').textContent = 'Nuevo Cobro';
      }
    } catch (e) {
      // No existe cobro previo, es nuevo
      document.querySelector('#modal-title-text').textContent = 'Nuevo Cobro';
    }

    // Abrir modal
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
      btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';
    }

    const payload = {
      id: parseInt(document.querySelector('#cobro-id')?.value || 0),
      cliente_id: parseInt(document.querySelector('#cobro-cliente-id')?.value || 0),
      servicio_id: parseInt(document.querySelector('#cobro-servicio-id')?.value || 0),
      tipo: 'servicio',
      codigo: (document.querySelector('#cobro-codigo')?.value || '').trim(),
      numero_factura: (document.querySelector('#cobro-numero-factura')?.value || '').trim(),
      concepto: (document.querySelector('#cobro-concepto')?.value || '').trim(),
      fecha_emision: document.querySelector('#cobro-fecha-emision')?.value || '',
      fecha_vencimiento: document.querySelector('#cobro-fecha-vencimiento')?.value || null,
      estado: document.querySelector('#cobro-estado')?.value || 'pendiente',
      subtotal: parseFloat(document.querySelector('#cobro-subtotal')?.value || 0),
      descuento: parseFloat(document.querySelector('#cobro-descuento')?.value || 0),
      impuestos: parseFloat(document.querySelector('#cobro-impuestos')?.value || 0),
      total: parseFloat(document.querySelector('#cobro-total')?.value || 0),
      moneda: 'ARS',
      observaciones: (document.querySelector('#cobro-observaciones')?.value || '').trim()
    };

    if (!payload.concepto) {
      alert('El concepto es obligatorio');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.original || 'Guardar';
      }
      saving = false;
      return;
    }

    try {
      const result = await fetchJSON(API.saveCobro, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      // Cerrar modal
      const modalEl = document.querySelector('#modal-cobro');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      // Recargar listado
      await loadList();

      console.log('Cobro guardado OK', result);
    } catch (e) {
      if (e.status === 409) {
        alert('El código ya existe.');
      } else if (e.data && e.data.error) {
        alert('Error: ' + e.data.error);
      } else {
        alert('Error guardando el cobro');
      }
      console.error('Error save', e);
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.original || 'Guardar';
      }
      saving = false;
    }
  }

  // Delegación global
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-refresh-servicios')) {
      ev.preventDefault();
      loadList();
      return;
    }

    if (ev.target.closest('#btn-save-cobro')) {
      ev.preventDefault();
      saveCobro();
      return;
    }

    const tr = ev.target.closest('#servicios-tbody tr');
    if (tr && ev.target.closest('.btn-crear-cobro')) {
      ev.preventDefault();
      openForm(tr);
      return;
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'servicios-search') {
      loadList();
    }
  });

  // Recalcular total al cambiar subtotal, descuento o impuestos
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    const id = ev.target.id;
    if (id === 'cobro-subtotal' || id === 'cobro-descuento' || id === 'cobro-impuestos') {
      calcularTotal();
    }
  });

  // Bootstrap
  const bootList = () => {
    if (here()) {
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();