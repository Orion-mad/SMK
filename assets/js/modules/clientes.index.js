// assets/js/modules/clientes.index.js - VERSION DEBUG
(() => {

  const API = {
    summaryClientes: '/api/clientes/summary.php',
    summaryDominios: '/api/clientes/dominios/summary.php',
    summaryPresupuestos: '/api/clientes/presupuestos/summary.php',
    summaryTrabajos: '/api/trabajos/summary.php'
  };

  function here() {
    const normalized = location.hash.replace(/\/+$/, '').replace(/\?.*$/, '');
    return normalized === '#/clientes';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function money(v) {
    const n = Number(v || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function safeText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  let charts = {
    clientesEstado: null,
    presupuestosEstado: null,
    trabajosEstado: null,
    trabajosPrioridad: null
  };

  let isLoading = false;

  function destroyCharts() {
    Object.keys(charts).forEach(k => {
      if (charts[k] && typeof charts[k].destroy === 'function') charts[k].destroy();
      charts[k] = null;
    });
  }

  function drawChartClientesEstado(data) {
    const ctx = document.getElementById('chart-clientes-estado');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.clientesEstado) {
      charts.clientesEstado.destroy();
      charts.clientesEstado = null;
    }

    charts.clientesEstado = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Activos', 'Inactivos'],
        datasets: [{
          data: [data.activos || 0, data.inactivos || 0],
          backgroundColor: ['#198754', '#6c757d']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              padding: 8,
              font: { size: 11 }
            }
          }
        },
        cutout: '60%'
      }
    });
  }

  function drawChartPresupuestosEstado(data) {
    const ctx = document.getElementById('chart-presupuestos-estado');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.presupuestosEstado) {
      charts.presupuestosEstado.destroy();
      charts.presupuestosEstado = null;
    }

    charts.presupuestosEstado = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Borrador', 'Enviado', 'Aprobado', 'Rechazado'],
        datasets: [{
          data: [
            data.borrador || 0,
            data.enviado || 0,
            data.aprobado || 0,
            data.rechazado || 0
          ],
          backgroundColor: ['#6c757d', '#0dcaf0', '#198754', '#dc3545']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              padding: 6,
              font: { size: 10 }
            }
          }
        },
        cutout: '60%'
      }
    });
  }

  function drawChartTrabajosEstado(data) {
    const ctx = document.getElementById('chart-trabajos-estado');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.trabajosEstado) {
      charts.trabajosEstado.destroy();
      charts.trabajosEstado = null;
    }

    charts.trabajosEstado = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pendiente', 'En Proceso', 'Homologación', 'Finalizado', 'Entregado'],
        datasets: [{
          data: [
            data.pendiente || 0,
            data.en_proceso || 0,
            data.homologacion || 0,
            data.finalizado || 0,
            data.entregado || 0
          ],
          backgroundColor: ['#6c757d', '#0d6efd', '#ffc107', '#0dcaf0', '#198754']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              padding: 6,
              font: { size: 9 }
            }
          }
        },
        cutout: '60%'
      }
    });
  }

  function drawChartTrabajosPrioridad(data) {
    const ctx = document.getElementById('chart-trabajos-prioridad');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.trabajosPrioridad) {
      charts.trabajosPrioridad.destroy();
      charts.trabajosPrioridad = null;
    }

    charts.trabajosPrioridad = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Baja', 'Normal', 'Alta', 'Urgente'],
        datasets: [{
          label: 'Trabajos',
          data: [
            data.prioridad_baja || 0,
            data.prioridad_normal || 0,
            data.prioridad_alta || 0,
            data.prioridad_urgente || 0
          ],
          backgroundColor: ['#6c757d', '#0dcaf0', '#ffc107', '#dc3545']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  }

  async function loadSummaryClientes() {
    try {
      const data = await fetchJSON(API.summaryClientes);

      safeText('stat-clientes-total', data.total ?? '0');
      safeText('stat-clientes-activos', data.activos ?? '0');
      safeText('stat-clientes-inactivos', data.inactivos ?? '0');
      safeText('stat-clientes-recientes', data.recientes ?? '0');
      safeText('stat-clientes-con-servicios', data.con_servicios ?? '0');
      safeText('stat-emails-unicos', data.emails_unicos ?? '0');
      safeText('stat-empresas', data.empresas ?? '0');

      drawChartClientesEstado(data);

    } catch (e) {
      console.error('❌ Error loading clientes summary', e);
      safeText('stat-clientes-total', '0');
      safeText('stat-clientes-activos', '0');
      safeText('stat-clientes-inactivos', '0');
      safeText('stat-clientes-recientes', '0');
      safeText('stat-clientes-con-servicios', '0');
      safeText('stat-emails-unicos', '0');
      safeText('stat-empresas', '0');
    }
  }

  async function loadSummaryDominios() {
    try {
      const data = await fetchJSON(API.summaryDominios);

      safeText('stat-dominios-total', data.total ?? '0');
      safeText('stat-dominios-activos', data.activos ?? '0');
      safeText('stat-dominios-ssl', data.con_ssl ?? '0');
      safeText('stat-dominios-vencer', data.por_vencer ?? '0');
      safeText('stat-dominios-vencidos', data.vencidos ?? '0');
      safeText('stat-ssl-activos', data.con_ssl ?? '0');

      const alertasDiv = document.getElementById('alertas-dominios-vencer');
      if (alertasDiv && data.proximos_vencer && data.proximos_vencer.length > 0) {
        alertasDiv.innerHTML = data.proximos_vencer.map(d => `
          <div class="list-group-item p-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong>${d.dominio}</strong>
                <br><small class="text-muted">${d.cliente_nombre}</small>
              </div>
              <span class="badge bg-warning text-dark">${d.dias_restantes}d</span>
            </div>
          </div>
        `).join('');
      } else if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">No hay dominios próximos a vencer</div>';
      }

    } catch (e) {
      console.error('❌ Error loading dominios summary', e);
      safeText('stat-dominios-total', '0');
      safeText('stat-dominios-activos', '0');
      safeText('stat-dominios-ssl', '0');
      safeText('stat-dominios-vencer', '0');
      safeText('stat-dominios-vencidos', '0');
      safeText('stat-ssl-activos', '0');
      
      const alertasDiv = document.getElementById('alertas-dominios-vencer');
      if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">No hay datos disponibles</div>';
      }
    }
  }

  async function loadSummaryPresupuestos() {
    try {
      const data = await fetchJSON(API.summaryPresupuestos);

      safeText('stat-presupuestos-total', data.total ?? '0');
      safeText('stat-presupuestos-borrador', data.borrador ?? '0');
      safeText('stat-presupuestos-enviados', data.enviado ?? '0');
      safeText('stat-presupuestos-aprobados', data.aprobado ?? '0');
      safeText('stat-presupuestos-vencer', data.por_vencer ?? '0');
      safeText('stat-presupuestos-monto', 'ARG ' + money(data.monto_total_arg ?? 0));
      safeText('stat-monto-total-presupuestos', 'ARG ' + money(data.monto_total_arg ?? 0));

      drawChartPresupuestosEstado(data);

      const alertasDiv = document.getElementById('alertas-presupuestos-vencer');
      if (alertasDiv && data.proximos_vencer && data.proximos_vencer.length > 0) {
        alertasDiv.innerHTML = data.proximos_vencer.map(p => `
          <div class="list-group-item p-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong>${p.codigo}</strong>
                <br><small class="text-muted">${p.cliente_nombre}</small>
              </div>
              <div class="text-end">
                <span class="badge bg-info text-dark">${p.dias_restantes}d</span>
                <br><small class="text-success fw-bold">${p.moneda} ${money(p.total)}</small>
              </div>
            </div>
          </div>
        `).join('');
      } else if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">No hay presupuestos por vencer</div>';
      }

    } catch (e) {
      console.error('❌ Error loading presupuestos summary', e);
      safeText('stat-presupuestos-total', '0');
      safeText('stat-presupuestos-borrador', '0');
      safeText('stat-presupuestos-enviados', '0');
      safeText('stat-presupuestos-aprobados', '0');
      safeText('stat-presupuestos-vencer', '0');
      safeText('stat-presupuestos-monto', 'ARG 0.00');
      safeText('stat-monto-total-presupuestos', 'ARG 0.00');
      
      const alertasDiv = document.getElementById('alertas-presupuestos-vencer');
      if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">No hay datos disponibles</div>';
      }
    }
  }

  async function loadSummaryTrabajos() {
    try {
      const data = await fetchJSON(API.summaryTrabajos);

      safeText('stat-trabajos-total', data.total ?? '0');
      safeText('stat-trabajos-proceso', data.en_proceso ?? '0');
      safeText('stat-trabajos-urgentes', data.urgentes ?? '0');
      safeText('stat-trabajos-entregados', data.entregados ?? '0');
      safeText('stat-trabajos-por-entregar', data.por_entregar ?? '0');
      safeText('stat-trabajos-con-saldo', data.con_saldo ?? '0');
      safeText('stat-trabajos-facturado', '$ ' + money(data.total_facturado ?? 0));
      safeText('stat-trabajos-saldo', '$ ' + money(data.saldo_pendiente ?? 0));
      safeText('stat-trabajos-mes', data.entregas_mes ?? '0');
      safeText('stat-trabajos-atrasados', data.atrasados ?? '0');

      if (data.por_estado) {
        drawChartTrabajosEstado(data.por_estado);
      }
      if (data.por_prioridad) {
        drawChartTrabajosPrioridad(data.por_prioridad);
      }

      const alertasDiv = document.getElementById('alertas-trabajos-entregar');
      if (alertasDiv && data.alertas && data.alertas.trabajos && data.alertas.trabajos.length > 0) {
        alertasDiv.innerHTML = data.alertas.trabajos.map(t => `
          <div class="list-group-item p-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong>${t.nombre}</strong>
                <br><small class="text-muted">${t.codigo} • ${t.cliente}</small>
              </div>
              <span class="badge ${t.dias <= 0 ? 'bg-danger' : t.dias <= 3 ? 'bg-warning text-dark' : 'bg-info'}">${t.dias}d</span>
            </div>
          </div>
        `).join('');
      } else if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">Sin trabajos próximos a entregar</div>';
      }

    } catch (e) {
      console.error('❌ Error loading trabajos summary', e);
      safeText('stat-trabajos-total', '—');
      safeText('stat-trabajos-proceso', '—');
      safeText('stat-trabajos-urgentes', '—');
      safeText('stat-trabajos-entregados', '—');
      safeText('stat-trabajos-por-entregar', '—');
      safeText('stat-trabajos-con-saldo', '—');
      safeText('stat-trabajos-facturado', '—');
      safeText('stat-trabajos-saldo', '—');
      safeText('stat-trabajos-mes', '—');
      safeText('stat-trabajos-atrasados', '—');
      
      const alertasDiv = document.getElementById('alertas-trabajos-entregar');
      if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">No hay datos disponibles</div>';
      }
    }
  }

  async function loadAlertasClientesInactivos() {
    try {
      const alertasDiv = document.getElementById('alertas-clientes-inactivos');
      if (alertasDiv) {
        alertasDiv.innerHTML = '<div class="text-muted small">Sin clientes inactivos recientes</div>';
      }
    } catch (e) {
      console.error('❌ Error loading clientes inactivos', e);
    }
  }

  async function loadAll() {
    if (!here() || isLoading) {
      return;
    }

    isLoading = true;
    destroyCharts();

    try {
      await Promise.all([
        loadSummaryClientes(),
        loadSummaryDominios(),
        loadSummaryPresupuestos(),
        loadSummaryTrabajos(),
        loadAlertasClientesInactivos()
      ]);
    } finally {
      isLoading = false;
    }
  }

  const boot = () => {
    if (here()) {
      // Verificar que el elemento principal del DOM esté presente antes de cargar
      let attempts = 0;
      const maxAttempts = 50; // ~1 segundo máximo (50 frames a 60fps)

      const checkAndLoad = () => {
        const viewElement = document.getElementById('view-clientes');
        if (viewElement) {
          loadAll();
        } else if (attempts < maxAttempts) {
          // Si el DOM aún no está listo, reintentamos en el siguiente frame
          attempts++;
          requestAnimationFrame(checkAndLoad);
        } else {
          console.warn('clientes.index: Timeout esperando que el DOM esté listo');
        }
      };
      checkAndLoad();
    } else {
      destroyCharts();
    }
  };

  window.addEventListener('hashchange', boot);
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('orion:navigate', boot);
})();