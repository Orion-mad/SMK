// assets/js/modules/contable.index.js
(() => {
  const API = { 
    summary: '/api/contable/summary.php'
  };

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/contable';
  }

  function money(v) {
    const n = Number(v || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function safeText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  let charts = { cobrosEstado: null, cobrosEvolucion: null, serviciosEstado: null, trabajosEstado: null };
  let isLoading = false;

  function destroyCharts() {
    Object.keys(charts).forEach(k => {
      if (charts[k] && typeof charts[k].destroy === 'function') charts[k].destroy();
      charts[k] = null;
    });
  }

  function drawChartCobrosEstado(data) {
    const ctx = document.getElementById('chart-cobros-estado');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.cobrosEstado) {
      charts.cobrosEstado.destroy();
      charts.cobrosEstado = null;
    }

    charts.cobrosEstado = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pendientes', 'Parciales', 'Pagados', 'Vencidos', 'Cancelados'],
        datasets: [{
          data: [
            data.pendientes || 0,
            data.parciales || 0,
            data.pagados || 0,
            data.vencidos || 0,
            data.cancelados || 0
          ],
          backgroundColor: ['#ffc107', '#17a2b8', '#198754', '#dc3545', '#6c757d']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: { 
            position: 'bottom', 
            labels: { boxWidth: 12, padding: 6, font: { size: 10 } } 
          }
        },
        cutout: '60%'
      }
    });
  }

  function drawChartServiciosEstado(data) {
    const ctx = document.getElementById('chart-servicios-estado');
    if (!ctx || !window.Chart) return;

    // Destruir gráfico existente si existe
    if (charts.serviciosEstado) {
      charts.serviciosEstado.destroy();
      charts.serviciosEstado = null;
    }

    charts.serviciosEstado = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Activos', 'Pendientes', 'Suspendidos'],
        datasets: [{
          data: [
            data.activos || 0,
            data.pendientes || 0,
            data.suspendidos || 0
          ],
          backgroundColor: ['#198754', '#ffc107', '#dc3545']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: { 
            position: 'bottom', 
            labels: { boxWidth: 12, padding: 6, font: { size: 10 } } 
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
        labels: ['Pendientes', 'En Proceso', 'Finalizados', 'Entregados c/Saldo'],
        datasets: [{
          data: [
            data.pendientes || 0,
            data.en_proceso || 0,
            data.finalizados || 0,
            data.entregados_pendientes || 0
          ],
          backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#fd7e14']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: {
          legend: { 
            position: 'bottom', 
            labels: { boxWidth: 12, padding: 6, font: { size: 10 } } 
          }
        },
        cutout: '60%'
      }
    });
  }

  function drawChartCobrosEvolucion(data) {
    const ctx = document.getElementById('chart-cobros-evolucion');
    if (!ctx || !window.Chart) return;

    if (!data || data.length === 0) {
      return;
    }

    // Destruir gráfico existente si existe
    if (charts.cobrosEvolucion) {
      charts.cobrosEvolucion.destroy();
      charts.cobrosEvolucion = null;
    }

    const meses = data.map(m => m.mes);
    const montos = data.map(m => Number(m.total || 0));

    charts.cobrosEvolucion = new Chart(ctx, {
      type: 'line',
      data: {
        labels: meses,
        datasets: [{
          label: 'Cobros Mensuales',
          data: montos,
          borderColor: '#198754',
          backgroundColor: 'rgba(25, 135, 84, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  async function loadSummary() {
    if (!here()) return;

    try {
      const data = await fetchJSON(API.summary);
      
      // ============ COBROS ============
      const c = data.cobros || {};
      
      safeText('stat-cobros-total', c.total ?? '0');
      safeText('stat-cobros-pendientes', c.pendientes ?? '0');
      safeText('stat-cobros-pagados', c.pagados ?? '0');
      safeText('stat-cobros-monto-total', `${c.moneda || 'ARS'} ${money(c.monto_total)}`);
      safeText('stat-cobros-saldo', `${c.moneda || 'ARS'} ${money(c.saldo_total)}`);

      // ============ SERVICIOS ============
      const s = data.servicios || {};
      
      safeText('stat-servicios-clientes', s.total_clientes ?? '0');
      safeText('stat-servicios-activos', s.activos ?? '0');
      safeText('stat-servicios-pendientes', s.pendientes ?? '0');
      safeText('stat-servicios-usd', `$ ${money(s.facturacion_usd)}`);
      safeText('stat-servicios-ars', `$ ${money(s.facturacion_ars_aprox)}`);

      // ============ TRABAJOS ============
      const t = data.trabajos || {};
      
      safeText('stat-trabajos-total', t.total ?? '0');
      safeText('stat-trabajos-pendientes', t.pendientes ?? '0');
      safeText('stat-trabajos-proceso', t.en_proceso ?? '0');
      safeText('stat-trabajos-saldo', `${t.moneda || 'ARS'} ${money(t.saldo_total)}`);
      safeText('stat-trabajos-monto', `${t.moneda || 'ARS'} ${money(t.monto_total)}`);

      // ============ GRÁFICOS ============
      destroyCharts();
      
      drawChartCobrosEstado(c);
      drawChartServiciosEstado(s);
      drawChartTrabajosEstado(t);
      
      if (data.evolucion && data.evolucion.length > 0) {
        drawChartCobrosEvolucion(data.evolucion);
      }

    } catch (e) {
      console.error('Error contable summary', e);
    }
  }

  async function loadAll() {
    if (!here() || isLoading) return;

    isLoading = true;
    try {
      await loadSummary();
    } finally {
      isLoading = false;
    }
  }

  const boot = () => {
    if (here()) {
      loadAll();
    } else {
      destroyCharts();
    }
  };

  window.addEventListener('hashchange', boot);
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('orion:navigate', boot);
})();
