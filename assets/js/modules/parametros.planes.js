// assets/js/modules/parametros.planes.js
(() => {
  const API = {
    list:  '/api/parameters/planes/list.php',
    get:   id => `/api/parameters/planes/get.php?id=${id}`,
    save:  '/api/parameters/planes/save.php',
    del:   id => `/api/parameters/planes/delete.php?id=${id}`,
  };

  const allowedMonedas = ['ARG','DOL','EUR'];

  let featuresWrap = null;

  function here() {
    return location.hash.replace(/\/+$/,'') === '#/parametros/planes';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status:r.status, data };
    return data;
  }

  async function prefillCodigoNuevoPlan() {
    try {
      const j = await fetchJSON('/api/core/codigo_next.php?tabla=prm_planes');
      const code = j?.codigo || j?.data?.codigo;
      const el = document.querySelector('#plan-codigo');
      if (code && el) el.value = code;
    } catch {
      // silencioso: si falla, el usuario podrá tipear el código
    }
  }

  function money(v) {
    const n = Number(v || 0);
    return n.toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 });
  }

  function rowTpl(p) {
    return `<tr data-id="${p.id}">
      <td>${p.id}</td>
      <td class="fw-semibold">${p.codigo}</td>
      <td>${p.nombre}</td>
      <td class="text-end">${money(p.precio_mensual)}</td>
      <td class="text-end">${money(p.precio_anual)}</td>
      <td>${p.moneda || 'ARG'}</td>
      <td>${p.activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#planes-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = `<tr><td colspan="8" class="text-center p-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </td></tr>`;
    
    try {
      const result = await fetchJSON(API.list);
      
      // Manejar diferentes estructuras de respuesta
      const data = Array.isArray(result) ? result : (result?.items || []);
      
      // Verificar explícitamente si hay items
      if (data.length > 0) {
        tbody.innerHTML = data.map(rowTpl).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center p-4 text-muted">
          <div class="py-4">
            <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i>
            <h5>No hay planes registrados</h5>
            <p class="mb-0">Haz clic en "Nuevo Plan" para comenzar</p>
          </div>
        </td></tr>`;
      }
      
      console.log(`? ${data.length} planes cargados`);
      
    } catch (e) {
      console.error('? Planes list error', e);
      tbody.innerHTML = `<tr><td colspan="8" class="text-center p-4">
        <div class="alert alert-danger m-0">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Error cargando datos
        </div>
      </td></tr>`;
    }
  }

  function setMoneda(val) {
    const v = allowedMonedas.includes(val) ? val : 'ARG';
    const map = { ARG: '#moneda-arg', DOL: '#moneda-dol', EUR: '#moneda-eur' };
    Object.entries(map).forEach(([k, sel]) => {
      const el = document.querySelector(sel);
      if (el) el.checked = (k === v);
    });
  }

  function getMoneda() {
    const sel = document.querySelector('input[name="plan-moneda"]:checked');
    const v = sel ? sel.value : 'ARG';
    return allowedMonedas.includes(v) ? v : 'ARG';
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#plan-id','0');
    set('#plan-codigo',''); 
    set('#plan-nombre',''); 
    set('#plan-descripcion','');
    setMoneda('ARG');
    set('#plan-pm','0'); 
    set('#plan-pa','0'); 
    set('#plan-orden','0');
    const chk = document.querySelector('#plan-activo'); 
    if (chk) chk.checked = true;
    if (featuresWrap) featuresWrap.innerHTML = '';
  }

  function featureRow(f={}) {
    return `<div class="row g-2 align-items-end border rounded p-2 mb-2" data-feature>
      <div class="col-5">
        <label class="form-label">Título *</label>
        <input type="text" class="form-control" value="${f.titulo||''}" data-f="titulo">
      </div>
      <div class="col-3">
        <label class="form-label">Valor</label>
        <input type="text" class="form-control" value="${f.valor||''}" data-f="valor">
      </div>
      <div class="col-2">
        <label class="form-label">Unidad</label>
        <input type="text" class="form-control" value="${f.unidad||''}" data-f="unidad">
      </div>
      <div class="col-1">
        <label class="form-label">Orden</label>
        <input type="number" class="form-control" value="${f.orden||0}" data-f="orden">
      </div>
      <div class="col-1 text-end">
        <button class="btn btn-outline-danger" type="button" data-action="remove-feature">&times;</button>
      </div>
    </div>`;
  }

  function collectFeatures() {
    return Array.from(document.querySelectorAll('#features-list [data-feature]')).map((row, i) => ({
      titulo: row.querySelector('[data-f="titulo"]')?.value.trim() || '',
      valor:  row.querySelector('[data-f="valor"]')?.value.trim() || '',
      unidad: row.querySelector('[data-f="unidad"]')?.value.trim() || '',
      orden:  Number(row.querySelector('[data-f="orden"]')?.value || (i+1)),
      activo: 1,
    })).filter(f => f.titulo !== '');
  }

  async function openForm(id=0) {
    if (!here()) return;
    featuresWrap = document.getElementById('features-list');
    clearForm();

    if (id === 0) {
      await prefillCodigoNuevoPlan();
    } else {
      try {
        const p = await fetchJSON(API.get(id));
        document.querySelector('#plan-id').value = p.id;
        document.querySelector('#plan-codigo').value = p.codigo || '';
        document.querySelector('#plan-nombre').value = p.nombre || '';
        document.querySelector('#plan-descripcion').value = p.descripcion || '';
        setMoneda(p.moneda || 'ARG');
        document.querySelector('#plan-pm').value = p.precio_mensual ?? 0;
        document.querySelector('#plan-pa').value = p.precio_anual ?? 0;
        document.querySelector('#plan-orden').value = p.orden ?? 0;
        const chk = document.querySelector('#plan-activo'); 
        if (chk) chk.checked = !!p.activo;
        if (featuresWrap && p.features) {
          p.features.forEach(f => featuresWrap.insertAdjacentHTML('beforeend', featureRow(f)));
        }
      } catch (e) {
        console.error('get plan error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-plan');
      m.show();
    } else {
      const modalEl = document.querySelector('#modal-plan');
      if (modalEl) { 
        modalEl.classList.add('show'); 
        modalEl.style.display = 'block'; 
      }
    }
  }

  let saving = false;
  async function savePlan() {
    if (saving) return; 
    saving = true;
    const btn = document.querySelector('#btn-save-plan');
    if (btn) { 
      btn.disabled = true; 
      btn.dataset.original = btn.innerHTML; 
      btn.innerHTML = 'Guardando...'; 
    }

    const payload = {
      id: Number(document.querySelector('#plan-id')?.value || 0),
      codigo: (document.querySelector('#plan-codigo')?.value || '').trim(),
      nombre: (document.querySelector('#plan-nombre')?.value || '').trim(),
      descripcion: (document.querySelector('#plan-descripcion')?.value || '').trim(),
      moneda: getMoneda(),
      precio_mensual: Number(document.querySelector('#plan-pm')?.value || 0),
      precio_anual:   Number(document.querySelector('#plan-pa')?.value || 0),
      orden:          Number(document.querySelector('#plan-orden')?.value || 0),
      activo: document.querySelector('#plan-activo')?.checked ? 1 : 0,
      features: collectFeatures(),
    };

    if (!payload.codigo || !payload.nombre) {
      alert('Código y Nombre son obligatorios');
      if (btn) { 
        btn.disabled = false; 
        btn.innerHTML = btn.dataset.original || 'Guardar'; 
      }
      saving = false; 
      return;
    }

    try {
      const data = await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const modalEl = document.querySelector('#modal-plan');
      if (modalEl && window.bootstrap?.Modal) {
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
          instance.hide();
        } else {
          new bootstrap.Modal(modalEl).hide();
        }
      } else if (modalEl) {
        modalEl.classList.remove('show'); 
        modalEl.style.display = 'none';
      }

      await loadList();
      console.log('? Plan guardado correctamente', data);
    } catch (e) {
      if (e.status === 409) {
        alert('El código ya existe.');
      } else {
        alert('Error guardando el plan');
      }
      console.error('save error', e);
    } finally {
      if (btn) { 
        btn.disabled = false; 
        btn.innerHTML = btn.dataset.original || 'Guardar'; 
      }
      saving = false;
    }
  }

  async function delPlan(id) {
    if (!confirm('¿Eliminar el plan? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
      console.log('? Plan eliminado');
    } catch (e) {
      alert('No se pudo borrar');
      console.error('delete error', e);
    }
  }

  // Delegación global
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-plan')) { 
      ev.preventDefault(); 
      openForm(0); 
      return; 
    }
    
    if (ev.target.closest('#btn-save-plan')) { 
      ev.preventDefault(); 
      savePlan(); 
      return; 
    }

    const tr = ev.target.closest('#planes-tbody tr');
    if (tr) {
      const id = Number(tr.dataset.id || 0);
      if (!id) return;
      if (ev.target.closest('.btn-edit')) { 
        ev.preventDefault(); 
        openForm(id); 
        return; 
      }
      if (ev.target.closest('.btn-del'))  { 
        ev.preventDefault(); 
        delPlan(id);  
        return; 
      }
    }

    if (ev.target.closest('[data-action="remove-feature"]')) {
      ev.preventDefault(); 
      ev.target.closest('[data-feature]')?.remove();
    }
    
    if (ev.target.closest('#btn-add-feature')) {
      ev.preventDefault(); 
      featuresWrap = document.getElementById('features-list'); 
      if (featuresWrap) {
        featuresWrap.insertAdjacentHTML('beforeend', featureRow());
      }
    }
  });

  const bootList = () => { 
    if (here()) { 
      console.log('?? Vista planes activa, cargando lista...');
      featuresWrap = document.getElementById('features-list'); 
      loadList(); 
    } 
  };
  
  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
  
  console.log('? Módulo parametros.planes.js cargado');
})();