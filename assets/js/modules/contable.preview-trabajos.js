// assets/js/modules/contable.preview-trabajos.js
(() => {
  const API = {
    preview: '/api/contable/cobros/preview-trabajos-pagos.php',
    migrar: '/api/contable/cobros/migrar-trabajos.php'
  };

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/contable/preview-trabajos';
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

  function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('es-AR');
  }

  function rowTpl(p) {
    const migradoBadge = p.ya_migrado 
      ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Sí</span>'
      : '<span class="badge bg-secondary">No</span>';

    const estadoBadge = p.estado === 'pagado'
      ? '<span class="badge bg-success">Pagado</span>'
      : '<span class="badge bg-warning text-dark">Pendiente</span>';

    return `<tr class="${p.ya_migrado ? 'table-success' : ''}">
      <td>${p.id}</td>
      <td><strong>${p.trabajo_codigo}</strong><br><small class="text-muted">${p.trabajo_nombre}</small></td>
      <td>${p.cliente_nombre}</td>
      <td>${formatDate(p.fecha_pago)}</td>
      <td class="text-end fw-bold">${p.moneda} ${money(p.monto)}</td>
      <td>${p.medio_pago || '-'}</td>
      <td>${p.referencia || '-'}</td>
      <td>${estadoBadge}</td>
      <td>${migradoBadge}</td>
    </tr>`;
  }

  async function loadPreview() {
    if (!here()) return;
    const tbody = document.querySelector('#preview-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) params.set('estado', filterEstado.value);

      const q = document.querySelector('#preview-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.preview + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = result?.items || [];

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="9" class="text-center p-4 text-muted">Sin datos</td></tr>`;

      // Actualizar stats
      const statTotal = document.getElementById('stat-total');
      const statPendientes = document.getElementById('stat-pendientes');
      
      if (statTotal) statTotal.textContent = result.meta?.total || 0;
      if (statPendientes) statPendientes.textContent = result.meta?.pendientes_migracion || 0;

    } catch (e) {
      console.error('Preview error', e);
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger p-3">Error: ${e.detail || e.message || 'Error desconocido'}</td></tr>`;
    }
  }

  async function migrarTodos() {
    if (!confirm('¿Migrar TODOS los pagos pendientes a cobros?\n\nEsto creará un cobro por cada pago que no haya sido migrado aún.')) {
      return;
    }

    const btn = document.querySelector('#btn-migrar-todos');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Migrando...';
    }

    try {
      const result = await fetchJSON(API.migrar);
      
      if (result.ok) {
        alert(`✅ Migración completada!\n\n- Procesados: ${result.resultado.procesados}\n- Cobros creados: ${result.resultado.cobros_creados}\n- Errores: ${result.resultado.errores}`);
        
        // Recargar vista
        await loadPreview();
      } else {
        alert('❌ Error en la migración: ' + (result.error || 'Error desconocido'));
      }

    } catch (e) {
      console.error('Migración error', e);
      alert('❌ Error en la migración: ' + (e.detail || e.message || 'Error de red'));
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> Migrar Todos a Cobros';
      }
    }
  }

  // Delegación de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;
    if (ev.target.closest('#btn-migrar-todos')) {
      ev.preventDefault();
      migrarTodos();
    }
  });

  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'preview-search') {
      loadPreview();
    }
  });

  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-estado') {
      loadPreview();
    }
  });

  // Bootstrap
  const bootPreview = () => { if (here()) loadPreview(); };
  
  window.addEventListener('hashchange', bootPreview);
  document.addEventListener('DOMContentLoaded', bootPreview);
  document.addEventListener('orion:navigate', bootPreview);
})();