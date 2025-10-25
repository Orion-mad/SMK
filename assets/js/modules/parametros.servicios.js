// assets/js/modules/parametros.servicios.js
(() => {
  const API = {
    list: '/api/parameters/servicios/list.php',
    get: id => `/api/parameters/servicios/get.php?id=${id}`,
    save: '/api/parameters/servicios/save.php',
    del: id => `/api/parameters/servicios/delete.php?id=${id}`,
    planesSelect: '/api/parameters/planes/select.php'
  };

  let featuresWrap = null;
  let planesData = [];

  function here() {
    return location.hash.replace(/\/+$/,'') === '#/parametros/servicios';
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
    'activo': '<span class="badge bg-success">Activo</span>',
    'suspendido': '<span class="badge bg-warning text-dark">Suspendido</span>',
    'cancelado': '<span class="badge bg-danger">Cancelado</span>'
  };

  function rowTpl(s) {
    return `<tr data-id="${s.id}">
      <td>${s.id}</td>
      <td class="fw-semibold">${s.codigo}</td>
      <td>${s.nombre}</td>
      <td><span class="badge bg-info">${s.plan_nombre || 'N/A'}</span></td>
      <td class="text-end fw-bold">$${money(s.precio_usd)}</td>
      <td><span class="badge bg-secondary">${s.tipo_cobro}</span></td>
      <td>${formatDate(s.fecha_inicio)}</td>
      <td>${estadoBadge[s.estado] || s.estado}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#servicios-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </td></tr>`;

    try {
      // Construir query params para filtros
      const params = new URLSearchParams();
      const filterEstado = document.querySelector('#filter-estado');
      if (filterEstado && filterEstado.value) {
        params.set('estado', filterEstado.value);
      }

      const q = document.querySelector('#servicios-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      // Manejar diferentes estructuras de respuesta
      const items = Array.isArray(result) ? result : (result?.items || []);

      // Verificar explícitamente si hay items
      if (items.length > 0) {
        tbody.innerHTML = items.map(rowTpl).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4 text-muted">
          <div class="py-4">
            <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i>
            <h5>No hay servicios registrados</h5>
            <p class="mb-0">Haz clic en "Nuevo Servicio" para comenzar</p>
          </div>
        </td></tr>`;
      }
      
      console.log(items.length + ' servicios cargados');
      
    } catch (e) {
      console.error('? Servicios list error', e);
      tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4">
        <div class="alert alert-danger m-0">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Error cargando datos
        </div>
      </td></tr>`;
    }
  }

  async function loadPlanes() {
    try {
      planesData = await fetchJSON(API.planesSelect);
      const select = document.querySelector('#servicio-plan-id');
      if (!select) return;

      select.innerHTML = '<option value="">-- Seleccionar Plan --</option>';
      planesData.forEach(p => {
        select.innerHTML += `<option value="${p.id}" 
          data-nombre="${p.nombre}" 
          data-mensual="${p.precio_mensual}" 
          data-anual="${p.precio_anual}"
          data-moneda="${p.moneda}">${p.nombre} (${p.codigo})</option>`;
      });
    } catch (e) {
      console.error('Error cargando planes', e);
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#servicio-id', '0');
    set('#servicio-codigo', '');
    set('#servicio-nombre', '');
    set('#servicio-plan-id', '');
    set('#servicio-descripcion', '');
    set('#servicio-precio-usd', '0');
    set('#servicio-tipo-cobro', 'mensual');
    set('#servicio-fecha-inicio', '');
    set('#servicio-estado', 'activo');
    set('#servicio-orden', '0');
    set('#servicio-observaciones', '');

    const planInfo = document.querySelector('#plan-info');
    if (planInfo) planInfo.innerHTML = '';
    
    if (featuresWrap) {
      featuresWrap.innerHTML = '<small class="text-muted">Seleccione un plan para ver sus características</small>';
    }
  }

  function updatePlanInfo() {
    const select = document.querySelector('#servicio-plan-id');
    const opt = select?.options[select.selectedIndex];
    const planInfo = document.querySelector('#plan-info');
    if (!planInfo) return;
    
    if (!opt || !opt.value) {
      planInfo.innerHTML = '';
      if (featuresWrap) {
        featuresWrap.innerHTML = '<small class="text-muted">Seleccione un plan para ver sus características</small>';
      }
      return;
    }

    const mensual = money(opt.dataset.mensual);
    const anual = money(opt.dataset.anual);
    const moneda = opt.dataset.moneda;
    
    planInfo.innerHTML = `<strong>Costo del plan:</strong> ${moneda} ${mensual} (mensual) / ${moneda} ${anual} (anual)`;
  }

  async function loadPlanFeatures(planId) {
    if (!planId || !featuresWrap) {
      if (featuresWrap) {
        featuresWrap.innerHTML = '<small class="text-muted">Seleccione un plan para ver sus características</small>';
      }
      return;
    }

    try {
      const data = await fetchJSON(`/api/parameters/planes/get.php?id=${planId}`);
      if (data.features && data.features.length > 0) {
        featuresWrap.innerHTML = '<div class="list-group list-group-flush">' +
          data.features.map(f => {
            const valor = f.valor ? `: <strong>${f.valor}${f.unidad ? ' ' + f.unidad : ''}</strong>` : '';
            return `<div class="list-group-item px-0 py-2">
              <i class="bi bi-check-circle-fill text-success me-2"></i>${f.titulo}${valor}
            </div>`;
          }).join('') +
          '</div>';
      } else {
        featuresWrap.innerHTML = '<small class="text-muted">Este plan no tiene características definidas</small>';
      }
    } catch (e) {
      featuresWrap.innerHTML = '<small class="text-danger">Error cargando características</small>';
    }
  }

  async function openForm(id = 0) {
    if (!here()) return;
    featuresWrap = document.getElementById('features-list');
    clearForm();
    await loadPlanes();

    if (id === 0) {
      // Código se autogenera en backend
      const codigoEl = document.querySelector('#servicio-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const s = await fetchJSON(API.get(id));
        document.querySelector('#servicio-id').value = s.id;
        document.querySelector('#servicio-codigo').value = s.codigo || '';
        document.querySelector('#servicio-nombre').value = s.nombre || '';
        document.querySelector('#servicio-plan-id').value = s.plan_id;
        document.querySelector('#servicio-descripcion').value = s.descripcion || '';
        document.querySelector('#servicio-precio-usd').value = s.precio_usd;
        document.querySelector('#servicio-tipo-cobro').value = s.tipo_cobro;
        document.querySelector('#servicio-fecha-inicio').value = s.fecha_inicio || '';
        document.querySelector('#servicio-estado').value = s.estado;
        document.querySelector('#servicio-orden').value = s.orden;
        document.querySelector('#servicio-observaciones').value = s.observaciones || '';
        
        updatePlanInfo();
        
        // Mostrar features
        if (s.features && s.features.length > 0) {
          featuresWrap.innerHTML = '<div class="list-group list-group-flush">' +
            s.features.map(f => {
              const valor = f.valor ? `: <strong>${f.valor}${f.unidad ? ' ' + f.unidad : ''}</strong>` : '';
              return `<div class="list-group-item px-0 py-2">
                <i class="bi bi-check-circle-fill text-success me-2"></i>${f.titulo}${valor}
              </div>`;
            }).join('') +
            '</div>';
        }
      } catch (e) {
        console.error('get servicio error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-servicio');
      m.show();
    }
  }

  let saving = false;
  async function saveServicio() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-servicio');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const payload = {
      id: Number(document.querySelector('#servicio-id')?.value || 0),
      codigo: (document.querySelector('#servicio-codigo')?.value || '').trim(),
      nombre: (document.querySelector('#servicio-nombre')?.value || '').trim(),
      plan_id: Number(document.querySelector('#servicio-plan-id')?.value || 0),
      descripcion: (document.querySelector('#servicio-descripcion')?.value || '').trim(),
      precio_usd: Number(document.querySelector('#servicio-precio-usd')?.value || 0),
      tipo_cobro: document.querySelector('#servicio-tipo-cobro')?.value || 'mensual',
      fecha_inicio: document.querySelector('#servicio-fecha-inicio')?.value || null,
      estado: document.querySelector('#servicio-estado')?.value || 'activo',
      orden: Number(document.querySelector('#servicio-orden')?.value || 0),
      observaciones: (document.querySelector('#servicio-observaciones')?.value || '').trim()
    };

    if (!payload.nombre) {
      alert('El nombre es obligatorio');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    if (!payload.plan_id) {
      alert('Debe seleccionar un Plan Base');
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

      const modalEl = document.querySelector('#modal-servicio');
      if (modalEl && window.bootstrap?.Modal) {
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
          instance.hide();
        } else {
          new bootstrap.Modal(modalEl).hide();
        }
      }

      await loadList();
      console.log('? Servicio guardado correctamente', data);
    } catch (e) {
      if (e.status === 409) {
        alert('El código ya existe.');
      } else if (e.data && e.data.error) {
        alert('Error: ' + e.data.error);
      } else {
        alert('Error guardando el servicio');
      }
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delServicio(id) {
    if (!confirm('¿Eliminar el servicio? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
      console.log('? Servicio eliminado');
    } catch (e) {
      alert('No se pudo borrar');
      console.error('delete error', e);
    }
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-servicio')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-servicio')) {
      ev.preventDefault();
      saveServicio();
      return;
    }

    const tr = ev.target.closest('#servicios-tbody tr');
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
        delServicio(id);
        return;
      }
    }
  });

  // Evento de cambio de plan (actualizar info y features)
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'servicio-plan-id') {
      updatePlanInfo();
      loadPlanFeatures(ev.target.value);
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'servicios-search') {
      loadList();
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
      console.log('?? Vista servicios activa, cargando lista...');
      featuresWrap = document.getElementById('features-list');
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
  
  console.log('? Módulo parametros.servicios.js cargado');
})();