// assets/js/modules/parametros.index.js
(() => {
  const API = { summary: '/api/parameters/summary.php' };

  // ---------- Utils ----------
  const $id = (id) => document.getElementById(id);

  // ⚠️ FUNCIÓN FALTANTE - AQUÍ ESTABA EL ERROR
  function here() {
    return location.hash.replace(/\/+$/,'') === '#/parametros';
  }

  function safeText(target, value) {
    const el = target.startsWith('#') || target.startsWith('.')
      ? document.querySelector(target)
      : $id(target);
    if (el) el.textContent = value;
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

  // ---------- Charts handling ----------
  let charts = { planesEstado: null, planesPrecios: null,estado: null, precios: null, serviciosEstado: null, conceptosCategorias: null };
    
    
  function destroyCharts() {
    Object.keys(charts).forEach(k => {
      if (charts[k]) {
        try {
          if (typeof charts[k].destroy === 'function') {
            charts[k].destroy();
          }
        } catch (e) {
          console.warn(`Error destruyendo chart ${k}:`, e);
        }
        charts[k] = null;
      }
    });
    
    // Limpiar también por canvas ID directamente
    ['chart-planes-estado', 'chart-planes-precios', 'chart-servicios-estado'].forEach(canvasId => {
      const canvas = document.getElementById(canvasId);
      if (canvas) {
        const existingChart = Chart.getChart(canvas);
        if (existingChart) {
          try {
            existingChart.destroy();
          } catch (e) {
            console.warn(`Error destruyendo chart en canvas ${canvasId}:`, e);
          }
        }
      }
    });
  }

  function drawChartsPlanes(p) {
    const ctxEstado = $id('chart-planes-estado');
    const ctxPrecios = $id('chart-planes-precios');
    
    if (!ctxEstado || !ctxPrecios || !window.Chart) {
      console.warn('Charts Planes: elementos no encontrados o Chart.js no cargado');
      return;
    }

    // Gráfico de estado (doughnut)
    try {
      charts.planesEstado = new Chart(ctxEstado, {
        type: 'doughnut',
        data: {
          labels: ['Activos', 'Inactivos'],
          datasets: [{
            data: [p.activos || 0, p.inactivos || 0],
            backgroundColor: ['#198754', '#6c757d']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          },
          cutout: '60%'
        }
      });
    } catch (e) {
      console.error('Error creando gráfico planes estado:', e);
    }

    // Gráfico de precios (bar)
    try {
      charts.planesPrecios = new Chart(ctxPrecios, {
        type: 'bar',
        data: {
          labels: ['Mensual', 'Anual'],
          datasets: [{
            label: p.moneda || 'ARG',
            data: [Number(p.pm_prom || 0), Number(p.pa_prom || 0)],
            backgroundColor: ['#0d6efd', '#6610f2']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: { y: { beginAtZero: true } }
        }
      });
    } catch (e) {
      console.error('Error creando gráfico planes precios:', e);
    }
  }

  function drawChartServicios(s) {
    const ctxEstado = $id('chart-servicios-estado');
    if (!ctxEstado || !window.Chart) {
      console.warn('Chart Servicios: elemento no encontrado o Chart.js no cargado');
      return;
    }

    try {
      charts.serviciosEstado = new Chart(ctxEstado, {
        type: 'doughnut',
        data: {
          labels: ['Activos', 'Suspendidos', 'Cancelados'],
          datasets: [{
            data: [
              s.activos || 0,
              s.suspendidos || 0,
              s.cancelados || 0
            ],
            backgroundColor: ['#198754', '#ffc107', '#dc3545']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          },
          cutout: '60%'
        }
      });
    } catch (e) {
      console.error('Error creando gráfico servicios estado:', e);
    }
  }

  async function loadSummary() {
    if (!here()) return;

    console.log('📊 Cargando resumen de parámetros...');

    try {
      const data = await fetchJSON(API.summary);
      console.log('✅ Datos recibidos:', data);
      
      // ============ PLANES ============
      const p = data.planes || {};
      
      const elPlanesTotal = $id('stat-planes-total');
      const elPlanesActivos = $id('stat-planes-activos');
      const elPlanesFeatures = $id('stat-planes-features');
      const elPlanesPm = $id('stat-planes-pm');
      const elPlanesPa = $id('stat-planes-pa');
      const elPlanesMoneda = $id('stat-planes-moneda');

      if (elPlanesTotal) elPlanesTotal.textContent = p.total ?? '0';
      if (elPlanesActivos) elPlanesActivos.textContent = p.activos ?? '0';
      if (elPlanesFeatures) elPlanesFeatures.textContent = p.features ?? '0';
      if (elPlanesPm) elPlanesPm.textContent = `${p.moneda || 'ARG'} ${money(p.pm_prom)}`;
      if (elPlanesPa) elPlanesPa.textContent = `${p.moneda || 'ARG'} ${money(p.pa_prom)}`;
      if (elPlanesMoneda) elPlanesMoneda.textContent = p.moneda || 'ARG';

      // ============ SERVICIOS ============
      const s = data.servicios || {};

      const elSrvTotal = $id('stat-servicios-total');
      const elSrvActivos = $id('stat-servicios-activos');
      const elSrvExcl = $id('stat-servicios-excl');
      const elSrvPromArs = $id('stat-servicios-prom-ars');
      const elSrvMoneda = $id('stat-servicios-moneda');
      const elSrvConPlanes = $id('stat-servicios-con-planes');

      if (elSrvTotal) elSrvTotal.textContent = s.total ?? '0';
      if (elSrvActivos) elSrvActivos.textContent = s.activos ?? '0';
      if (elSrvExcl) elSrvExcl.textContent = (s.cancelados || 0) + (s.suspendidos || 0);
      if (elSrvPromArs) elSrvPromArs.textContent = `≈ ${money(s.precio_ars_aprox || 0)}`;
      if (elSrvMoneda) elSrvMoneda.textContent = s.moneda || 'DOL';
      if (elSrvConPlanes) elSrvConPlanes.textContent = s.planes_usados ?? '0';

      // Destruir gráficos previos
      destroyCharts();

      // Dibujar nuevos gráficos
      drawChartsPlanes(p);
      drawChartServicios(s);

      console.log('✅ Resumen cargado correctamente');

    } catch (e) {
      console.error('❌ Error cargando summary:', e);
      destroyCharts();
      
      // Mostrar mensaje de error en la UI
      const elPlanesTotal = $id('stat-planes-total');
      if (elPlanesTotal) elPlanesTotal.textContent = 'Error';
    }
  }

  // Bootstrap y navegación
  const bootSummary = () => {
    console.log('🔄 bootSummary ejecutado, hash:', location.hash);
    
    if (here()) {
      console.log('✅ Estamos en #/parametros');
      loadSummary();
    } else {
      console.log('ℹ️ No estamos en #/parametros, limpiando gráficos...');
      // Limpiar gráficos si salimos de la vista
      if (charts.planesEstado || charts.planesPrecios || charts.serviciosEstado) {
        destroyCharts();
      }
    }
  };

  // Event listeners
  window.addEventListener('hashchange', bootSummary);
  document.addEventListener('DOMContentLoaded', bootSummary);
  document.addEventListener('orion:navigate', bootSummary);
  
  console.log('✅ Módulo parametros.index.js cargado correctamente');
  console.log('📍 Hash actual:', location.hash);
})();