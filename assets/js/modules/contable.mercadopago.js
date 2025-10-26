// assets/js/modules/contable.mercadopago.js
(() => {
  const API = {
    balance: '/api/contable/mercadopago/balance.php',
    movimientos: '/api/contable/mercadopago/movimientos.php',
    detalle: (id) => `/api/contable/mercadopago/detalle.php?id=${id}`
  };

  let currentPage = 1;
  let currentTab = 'ingresos';
  const pageSize = 20;

  function here() {
    const hash = location.hash.replace(/\/+$/, '');
    return hash === '#/contable/mercadopago';
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
    return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-AR', { 
      day: '2-digit', 
      month: '2-digit', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function showAlert(message, type = 'info') {
    const container = document.querySelector('#mp-contable-alert');
    if (!container) return;

    const alertClass = {
      'success': 'alert-success',
      'error': 'alert-danger',
      'warning': 'alert-warning',
      'info': 'alert-info'
    }[type] || 'alert-info';

    const icon = {
      'success': 'check-circle',
      'error': 'exclamation-triangle',
      'warning': 'exclamation-triangle',
      'info': 'info-circle'
    }[type] || 'info-circle';

    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = `
      <i class="bi bi-${icon} me-2"></i>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.innerHTML = '';
    container.appendChild(alert);

    setTimeout(() => {
      alert.remove();
    }, 5000);
  }

  const estadoBadge = {
    'approved': '<span class="badge bg-success">Aprobado</span>',
    'pending': '<span class="badge bg-warning text-dark">Pendiente</span>',
    'in_process': '<span class="badge bg-info">En Proceso</span>',
    'rejected': '<span class="badge bg-danger">Rechazado</span>',
    'cancelled': '<span class="badge bg-secondary">Cancelado</span>',
    'refunded': '<span class="badge bg-dark">Reembolsado</span>',
    'charged_back': '<span class="badge bg-danger">Contracargo</span>'
  };

  const tipoBadge = {
    'payment': '<span class="badge bg-primary">Pago</span>',
    'refund': '<span class="badge bg-warning">Reembolso</span>',
    'fee': '<span class="badge bg-secondary">Comisión</span>',
    'release': '<span class="badge bg-info">Liberación</span>',
    'payout': '<span class="badge bg-success">Retiro</span>'
  };

  function rowTpl(mov) {
    return `<tr data-id="${mov.id}">
      <td>${formatDate(mov.date_created)}</td>
      <td>
        <span class="font-monospace">#${mov.id}</span>
      </td>
      <td>
        <div>${mov.description || mov.statement_descriptor || '-'}</div>
        ${mov.payer_email ? `<small class="text-muted">${mov.payer_email}</small>` : ''}
      </td>
      <td>${tipoBadge[mov.type] || mov.type}</td>
      <td class="text-end fw-bold">
        ${mov.currency_id || 'ARS'} ${money(Math.abs(mov.net_amount || mov.total_amount))}
      </td>
      <td>${estadoBadge[mov.status] || mov.status}</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-primary" onclick="contableMercadoPago.verDetalle('${mov.id}')">
          <i class="bi bi-eye"></i>
        </button>
      </td>
    </tr>`;
  }

  async function loadBalance() {
    if (!here()) return;

    try {
      const data = await fetchJSON(API.balance);

      if (!data || !data.ok) {
        throw new Error(data?.error || 'Error al obtener el balance');
      }

      const saldo = document.querySelector('#mp-saldo-disponible');
      const ultAct = document.querySelector('#mp-ultima-actualizacion');

      if (saldo) {
        saldo.textContent = `${data.currency || 'ARS'} ${money(data.available_balance)}`;
      }

      if (ultAct) {
        ultAct.textContent = `Última actualización: ${formatDate(new Date())}`;
      }

    } catch (e) {
      console.error('Error cargando balance:', e);
      if (here()) {
        showAlert('Error al cargar el balance de Mercado Pago. Verifique la configuración.', 'error');
      }
    }
  }

  async function loadMovimientos(tipo = 'ingresos', page = 1) {
    if (!here()) return;

    const tbody = document.querySelector(`#mp-${tipo}-tbody`);
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </td></tr>`;

    try {
      const params = new URLSearchParams({
        tipo: tipo,
        page: page.toString(),
        limit: pageSize.toString()
      });

      const fechaDesde = document.querySelector('#mp-fecha-desde')?.value;
      const fechaHasta = document.querySelector('#mp-fecha-hasta')?.value;
      const search = document.querySelector('#mp-search')?.value;

      if (fechaDesde) params.set('fecha_desde', fechaDesde);
      if (fechaHasta) params.set('fecha_hasta', fechaHasta);
      if (search) params.set('q', search);

      const url = `${API.movimientos}?${params.toString()}`;
      const data = await fetchJSON(url);

      if (!data || !data.ok) {
        throw new Error(data?.error || 'Error al obtener movimientos');
      }

      // Actualizar resumen
      if (tipo === 'ingresos') {
        const ingresosMes = document.querySelector('#mp-ingresos-mes');
        const ingresosCount = document.querySelector('#mp-ingresos-count');
        
        if (ingresosMes) {
          ingresosMes.textContent = `${data.summary?.currency || 'ARS'} ${money(data.summary?.total || 0)}`;
        }
        if (ingresosCount) {
          ingresosCount.textContent = `${data.summary?.count || 0} transacciones`;
        }
      } else {
        const egresosMes = document.querySelector('#mp-egresos-mes');
        const egresosCount = document.querySelector('#mp-egresos-count');
        
        if (egresosMes) {
          egresosMes.textContent = `${data.summary?.currency || 'ARS'} ${money(data.summary?.total || 0)}`;
        }
        if (egresosCount) {
          egresosCount.textContent = `${data.summary?.count || 0} transacciones`;
        }
      }

      // Renderizar tabla
      if (!data.items || data.items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">
          No hay ${tipo} registrados
        </td></tr>`;
        return;
      }

      tbody.innerHTML = data.items.map(rowTpl).join('');

      // Paginación
      renderPagination(tipo, page, data.total_pages || 1);

    } catch (e) {
      console.error(`Error cargando ${tipo}:`, e);
      tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">
        Error al cargar datos: ${e.message}
      </td></tr>`;
      
      if (here()) {
        showAlert(`Error al cargar ${tipo}. Verifique la configuración de Mercado Pago.`, 'error');
      }
    }
  }

  function renderPagination(tipo, currentPage, totalPages) {
    const container = document.querySelector(`#mp-${tipo}-pagination`);
    if (!container) return;

    if (totalPages <= 1) {
      container.classList.add('d-none');
      return;
    }

    container.classList.remove('d-none');
    const ul = container.querySelector('ul');
    if (!ul) return;

    let html = '';

    // Anterior
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${currentPage - 1}">Anterior</a>
    </li>`;

    // Páginas
    for (let i = 1; i <= Math.min(totalPages, 5); i++) {
      html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
        <a class="page-link" href="#" data-page="${i}">${i}</a>
      </li>`;
    }

    // Siguiente
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${currentPage + 1}">Siguiente</a>
    </li>`;

    ul.innerHTML = html;

    // Event listeners
    ul.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const page = parseInt(a.dataset.page);
        if (page > 0 && page <= totalPages) {
          currentPage = page;
          loadMovimientos(tipo, page);
        }
      });
    });
  }

  async function verDetalle(transactionId) {
    try {
      const modal = new bootstrap.Modal('#modal-detalle-transaccion');
      const content = document.querySelector('#detalle-transaccion-content');

      if (content) {
        content.innerHTML = `<div class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
        </div>`;
      }

      modal.show();

      const data = await fetchJSON(API.detalle(transactionId));

      if (!data || !data.ok) {
        throw new Error(data?.error || 'Error al obtener el detalle');
      }

      const mov = data.transaction;

      content.innerHTML = `
        <div class="row g-3">
          <div class="col-12">
            <h5 class="border-bottom pb-2">Información General</h5>
          </div>
          <div class="col-md-6">
            <strong>ID de Operación:</strong><br>
            <span class="font-monospace">#${mov.id}</span>
          </div>
          <div class="col-md-6">
            <strong>Fecha:</strong><br>
            ${formatDate(mov.date_created)}
          </div>
          <div class="col-md-6">
            <strong>Estado:</strong><br>
            ${estadoBadge[mov.status] || mov.status}
          </div>
          <div class="col-md-6">
            <strong>Tipo:</strong><br>
            ${tipoBadge[mov.type] || mov.type}
          </div>
          <div class="col-md-6">
            <strong>Monto Bruto:</strong><br>
            ${mov.currency_id || 'ARS'} ${money(mov.total_amount)}
          </div>
          <div class="col-md-6">
            <strong>Monto Neto:</strong><br>
            ${mov.currency_id || 'ARS'} ${money(mov.net_amount)}
          </div>
          <div class="col-12">
            <strong>Descripción:</strong><br>
            ${mov.description || mov.statement_descriptor || '-'}
          </div>
          ${mov.payer_email ? `
          <div class="col-12">
            <h5 class="border-bottom pb-2 mt-3">Información del Pagador</h5>
          </div>
          <div class="col-md-6">
            <strong>Email:</strong><br>
            ${mov.payer_email}
          </div>
          ${mov.payer_name ? `
          <div class="col-md-6">
            <strong>Nombre:</strong><br>
            ${mov.payer_name}
          </div>` : ''}
          ` : ''}
          ${mov.payment_method ? `
          <div class="col-12">
            <h5 class="border-bottom pb-2 mt-3">Método de Pago</h5>
          </div>
          <div class="col-md-6">
            <strong>Tipo:</strong><br>
            ${mov.payment_method.type || '-'}
          </div>
          <div class="col-md-6">
            <strong>Método:</strong><br>
            ${mov.payment_method.id || '-'}
          </div>
          ` : ''}
        </div>
      `;

    } catch (e) {
      console.error('Error cargando detalle:', e);
      const content = document.querySelector('#detalle-transaccion-content');
      if (content) {
        content.innerHTML = `<div class="alert alert-danger">
          Error al cargar el detalle: ${e.message}
        </div>`;
      }
    }
  }

  function init() {
    if (!here()) return;

    // Botón refresh
    const btnRefresh = document.querySelector('#btn-refresh-mp');
    if (btnRefresh) {
      btnRefresh.addEventListener('click', () => {
        loadBalance();
        loadMovimientos(currentTab, currentPage);
      });
    }

    // Botón filtrar
    const btnFiltrar = document.querySelector('#btn-filtrar-mp');
    if (btnFiltrar) {
      btnFiltrar.addEventListener('click', () => {
        currentPage = 1;
        loadMovimientos(currentTab, currentPage);
      });
    }

    // Tabs
    const tabIngresos = document.querySelector('#tab-ingresos');
    const tabEgresos = document.querySelector('#tab-egresos');

    if (tabIngresos) {
      tabIngresos.addEventListener('shown.bs.tab', () => {
        currentTab = 'ingresos';
        currentPage = 1;
        loadMovimientos('ingresos', currentPage);
      });
    }

    if (tabEgresos) {
      tabEgresos.addEventListener('shown.bs.tab', () => {
        currentTab = 'egresos';
        currentPage = 1;
        loadMovimientos('egresos', currentPage);
      });
    }

    // Inicializar fechas (mes actual)
    const hoy = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const fechaDesde = document.querySelector('#mp-fecha-desde');
    const fechaHasta = document.querySelector('#mp-fecha-hasta');

    if (fechaDesde) {
      fechaDesde.value = primerDia.toISOString().split('T')[0];
    }
    if (fechaHasta) {
      fechaHasta.value = hoy.toISOString().split('T')[0];
    }
  }

  function loadAll() {
    if (!here()) return;
    
    setTimeout(() => {
      init();
      loadBalance();
      loadMovimientos('ingresos', 1);
    }, 100);
  }

  // Navegación
  window.addEventListener('hashchange', () => {
    if (here()) loadAll();
  });

  document.addEventListener('orion:navigate', () => {
    if (here()) loadAll();
  });

  if (here()) {
    document.addEventListener('DOMContentLoaded', loadAll);
  }

  // API pública
  window.contableMercadoPago = {
    loadBalance,
    loadMovimientos,
    verDetalle
  };

})();