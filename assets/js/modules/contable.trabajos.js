// assets/js/modules/contable_trabajos.js
(() => {
  const API = {
    list: '/api/contable/trabajos/list.php',
    get: id => `/api/contable/trabajos/get.php?id=${id}`,
    cotizacion: (fecha) => `/api/core/moneda_cotizacion.php?fecha=${fecha}`,
    saveCobro: '/api/contable/trabajos/create_cobro.php',
  };

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/contable/trabajos';
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
      return v
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

  const estadoBadge = {
    'pendiente': '<span class="badge bg-secondary">Pendiente</span>',
    'en_proceso': '<span class="badge bg-primary">En Proceso</span>',
    'homologacion': '<span class="badge bg-info">Homologación</span>',
    'finalizado': '<span class="badge bg-success">Finalizado</span>',
    'entregado': '<span class="badge bg-success">Entregado</span>',
    'cancelado': '<span class="badge bg-danger">Cancelado</span>'
  };

  function rowTpl(t) {
    const saldoPorcentaje = t.total > 0 ? ((t.saldo / t.total) * 100).toFixed(0) : 0;
    const saldoClass = saldoPorcentaje > 50 ? 'text-danger' : (saldoPorcentaje > 0 ? 'text-warning' : 'text-success');

    return `<tr data-id="${t.id}">
      <td class="fw-semibold">${t.codigo}</td>
      <td>
        <div>${t.nombre}</div>
        <small class="text-muted">Ingreso: ${formatDate(t.fecha_ingreso)}</small>
      </td>
      <td>
        <div class="fw-semibold">${t.cliente_nombre || t.cliente_razon_social}</div>
        <small class="text-muted">${t.cliente_tipo_doc || ''}: ${t.cliente_doc || ''}</small>
      </td>
      <td><span class="badge bg-info">${t.servicio_nombre || 'N/A'}</span></td>
      <td class="text-end fw-bold">${t.moneda} ${money(t.total)}</td>
      <td class="text-end text-success">$${money(t.total_pagado || 0)}</td>
      <td class="text-end ${saldoClass} fw-bold">
        $${money(t.saldo)}
        ${saldoPorcentaje > 0 ? `<small class="d-block">(${saldoPorcentaje}%)</small>` : ''}
      </td>
      <td>${estadoBadge[t.estado] || t.estado}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-success btn-generar-cobro-trab" ${t.saldo <= 0 ? 'disabled' : ''}>
          <i class="bi bi-cash-coin me-1"></i> Cobrar
        </button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#trabajos-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado-trabajo')?.value;
      if (filterEstado) params.set('estado', filterEstado);

      const q = document.querySelector('#trabajos-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="9" class="text-center p-4 text-muted">No hay trabajos</td></tr>`;
    } catch (e) {
      console.error('Error cargando trabajos', e);
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  async function getCotizacion(fecha) {
    try {
      const data = await fetchJSON(API.cotizacion(fecha));
       return {
        valor: data?.cotizacion || 0,
        fecha: fecha
      };
    } catch (e) {
      console.error('Error obteniendo cotización', e);
      return 0;
    }
  }

  function calcularTotalesTrabajo() {
    const montoOriginal = Number(document.querySelector('#cobro-trab-monto-original')?.value || 0);
    const cotizacion = Number(document.querySelector('#cobro-trab-cotizacion')?.value || 1);
    const descuento = Number(document.querySelector('#cobro-trab-descuento')?.value || 0);
    const impuestos = Number(document.querySelector('#cobro-trab-impuestos')?.value || 0);

    const subtotal = montoOriginal * cotizacion;
    const total = subtotal - descuento + impuestos;

    const subtotalEl = document.querySelector('#cobro-trab-subtotal');
    const totalEl = document.querySelector('#cobro-trab-total');
    
    if (subtotalEl) subtotalEl.value = subtotal.toFixed(2);
    if (totalEl) totalEl.value = total.toFixed(2);
  }

  async function openCobroFormTrabajo(trabajoId) {
    if (!here()) return;

    try {
      // Obtener datos del trabajo
      const trabajo = await fetchJSON(API.get(trabajoId));

      if (!trabajo) {
        alert('No se pudo cargar la información del trabajo');
        return;
      }

      // Limpiar formulario
      document.querySelector('#cobro-trab-id').value = '0';
      document.querySelector('#cobro-trab-codigo').value = '';
      document.querySelector('#cobro-trab-trabajo-id').value = trabajoId;
      document.querySelector('#cobro-trab-cliente-id').value = trabajo.cliente_id;
      document.querySelector('#cobro-trab-servicio-id').value = trabajo.servicio_id || '';
      document.querySelector('#cobro-trab-fecha-emision').value = new Date().toISOString().split('T')[0];
      document.querySelector('#cobro-trab-fecha-vencimiento').value = '';
      document.querySelector('#cobro-trab-descuento').value = '0';
      document.querySelector('#cobro-trab-impuestos').value = '0';
      document.querySelector('#cobro-trab-estado').value = 'pendiente';
      document.querySelector('#cobro-trab-tipo').value = 'trabajo';
      document.querySelector('#cobro-trab-numero-factura').value = '';
      
      // Llenar información
      document.querySelector('#info-trab-codigo').textContent = trabajo.codigo;
      document.querySelector('#info-trab-cliente').textContent = trabajo.cliente_nombre || trabajo.cliente_razon_social;
      document.querySelector('#info-trab-nombre').textContent = trabajo.nombre;
      document.querySelector('#info-trab-servicio').textContent = trabajo.servicio_nombre || 'N/A';
      document.querySelector('#info-trab-total').textContent = `${trabajo.moneda} ${money(trabajo.total)}`;
      document.querySelector('#info-trab-saldo').textContent = `${trabajo.moneda} ${money(trabajo.saldo)}`;
      document.querySelector('#info-trab-moneda').textContent = trabajo.moneda;

      // Monto original (saldo pendiente)
      document.querySelector('#cobro-trab-monto-original').value = trabajo.saldo;

      // Concepto automático
      const concepto = `Trabajo ${trabajo.codigo} - ${trabajo.nombre}`;
      document.querySelector('#cobro-trab-concepto').value = concepto;


      // Manejo de cotización según moneda
      const cotizacionGroup = document.querySelector('#cotizacion-group');
      const conversionInfo = document.querySelector('#conversion-info');
      const observacionesInfo = document.querySelector('#cobro-trab-observaciones');
      const monedaOrigSymbol = document.querySelector('#moneda-orig-symbol');

      if (trabajo.moneda === 'ARS') {
        // Si es en pesos, no necesita conversión
        cotizacionGroup.style.display = 'none';
        conversionInfo.textContent = 'No requiere conversión';
        monedaOrigSymbol.textContent = '$';
        document.querySelector('#cobro-trab-cotizacion').value = '1';
      } else {
        // Si es en dólares o euros, obtener cotización
        cotizacionGroup.style.display = 'block';
        monedaOrigSymbol.textContent = trabajo.moneda === 'DOL' ? 'USD' : '€';
        
        // Obtener cotización del día
        const fechaHoy = todayISO();
        const tc = await getCotizacion(fechaHoy);
        document.querySelector('#cobro-trab-cotizacion').value = tc.valor.toFixed(2);
        document.querySelector('#cotizacion-trab-fecha').textContent = `Fecha: ${fechaHoy}`;
        conversionInfo.textContent = `Monto en dólares: $${money(trabajo.total)} USD - Cotización del día: $${money(tc.valor)} x dólar`;
        observacionesInfo.textContent += `Monto en dólares: $${money(trabajo.total)} USD - Cotización del día: $${money(tc.valor)} x dólar`;
      }

      // Calcular totales
      calcularTotalesTrabajo();

      // Abrir modal
      if (window.bootstrap?.Modal) {
        const m = new bootstrap.Modal('#modal-cobro-trabajo');
        m.show();
      }
    } catch (e) {
      console.error('Error abriendo formulario', e);
      alert('Error al cargar el formulario');
    }
  }

  let saving = false;
  async function guardarCobroTrabajo() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-guardar-cobro-trab');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    try {
      const form = document.querySelector('#form-cobro-trabajo');
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        throw new Error('Complete los campos requeridos');
      }

      const montoOriginal = Number(document.querySelector('#cobro-trab-monto-original').value);
      const cotizacion = Number(document.querySelector('#cobro-trab-cotizacion').value);
      const observaciones = document.querySelector('#cobro-trab-observaciones').value || '';

      // Agregar información de conversión a observaciones
      const monedaOrig = document.querySelector('#info-trab-moneda').textContent;
      let obsConCotizacion = observaciones;
      

      const payload = {
        id: 0,
        codigo: '', // Se autogenera en backend
        cliente_id: Number(document.querySelector('#cobro-trab-cliente-id').value),
        trabajo_id: Number(document.querySelector('#cobro-trab-trabajo-id').value),
        servicio_id: Number(document.querySelector('#cobro-trab-servicio-id').value) || null,
        tipo: document.querySelector('#cobro-trab-tipo').value,
        concepto: document.querySelector('#cobro-trab-concepto').value,
        fecha_emision: document.querySelector('#cobro-trab-fecha-emision').value,
        fecha_vencimiento: document.querySelector('#cobro-trab-fecha-vencimiento').value || null,
        subtotal: Number(document.querySelector('#cobro-trab-subtotal').value),
        descuento: Number(document.querySelector('#cobro-trab-descuento').value),
        impuestos: Number(document.querySelector('#cobro-trab-impuestos').value),
        total: Number(document.querySelector('#cobro-trab-total').value),
        moneda: 'ARS',
        estado: document.querySelector('#cobro-trab-estado').value,
        numero_factura: document.querySelector('#cobro-trab-numero-factura').value || null,
        observaciones: obsConCotizacion.trim(),
        monto_pagado: 0,
        saldo: Number(document.querySelector('#cobro-trab-total').value),
      };

      const data = await fetchJSON(API.saveCobro, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      // Cerrar modal
      const modalEl = document.querySelector('#modal-cobro-trabajo');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      alert('Cobro guardado exitosamente');
      await loadList();

    } catch (e) {
      console.error('Error guardando cobro', e);
      alert(e.message || 'Error al guardar el cobro');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar';
      }
      saving = false;
    }
  }

  // Event listeners
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('.btn-generar-cobro-trab')) {
      ev.preventDefault();
      const tr = ev.target.closest('tr');
      const trabajoId = Number(tr.dataset.id);
      openCobroFormTrabajo(trabajoId);
      return;
    }

    if (ev.target.closest('#btn-guardar-cobro-trab')) {
      ev.preventDefault();
      guardarCobroTrabajo();
      return;
    }

    if (ev.target.closest('#btn-actualizar-cotizacion-trab')) {
      ev.preventDefault();
      const monedaOrig = document.querySelector('#info-trab-moneda').textContent;
      const monedaTc = monedaOrig === 'DOL' ? 'DOL' : (monedaOrig === 'EUR' ? 'EUR' : 'DOL');
      
      getCotizacion(monedaTc).then(tc => {
        document.querySelector('#cobro-trab-cotizacion').value = tc.valor.toFixed(2);
        document.querySelector('#cotizacion-trab-fecha').textContent = `Fecha: ${formatDate(tc.fecha)}`;
        calcularTotalesTrabajo();
      });
      return;
    }
  });

  // Recalcular al cambiar valores
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (['cobro-trab-cotizacion', 'cobro-trab-descuento', 'cobro-trab-impuestos'].includes(ev.target.id)) {
      calcularTotalesTrabajo();
    }
  });

  // Búsqueda y filtros
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'trabajos-search') {
      loadList();
    }
  });

  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado-trabajo') {
      loadList();
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