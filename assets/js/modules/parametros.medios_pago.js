// assets/js/modules/parametros.medios_pago.js
(() => {
  const API = {
    list: '/api/parameters/medios_pago/list.php',
    get: id => `/api/parameters/medios_pago/get.php?id=${id}`,
    save: '/api/parameters/medios_pago/save.php',
    del: id => `/api/parameters/medios_pago/delete.php?id=${id}`,
  };

  function here() {
    return location.hash.replace(/\/+$/, '') === '#/parametros/medios-pago';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function rowTpl(m) {
    return `<tr data-id="${m.id}">
      <td>${m.id}</td>
      <td class="fw-semibold">${m.codigo}</td>
      <td>${m.nombre}</td>
      <td><small class="text-muted">${m.notas || '-'}</small></td>
      <td class="text-center">${m.activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'}</td>
      <td class="text-center">${m.orden}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#medios-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterActivo = document.querySelector('#filter-activo');
      if (filterActivo && filterActivo.value !== '') {
        params.set('activo', filterActivo.value);
      }

      const q = document.querySelector('#medios-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length)
        ? items.map(rowTpl).join('')
        : `<tr><td colspan="7" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Medios list error', e);
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#medio-id', '0');
    set('#medio-codigo', '');
    set('#medio-nombre', '');
    set('#medio-notas', '');
    set('#medio-orden', '0');
    const chk = document.querySelector('#medio-activo');
    if (chk) chk.checked = true;
  }

  async function openForm(id = 0) {
    if (!here()) return;
    clearForm();

    if (id === 0) {
      // Código se autogenera en backend
      const codigoEl = document.querySelector('#medio-codigo');
      if (codigoEl) {
        codigoEl.value = '';
        codigoEl.placeholder = 'Auto-generado';
      }
    } else {
      try {
        const m = await fetchJSON(API.get(id));
        document.querySelector('#medio-id').value = m.id;
        document.querySelector('#medio-codigo').value = m.codigo || '';
        document.querySelector('#medio-nombre').value = m.nombre || '';
        document.querySelector('#medio-notas').value = m.notas || '';
        document.querySelector('#medio-orden').value = m.orden || 0;
        const chk = document.querySelector('#medio-activo');
        if (chk) chk.checked = !!m.activo;
      } catch (e) {
        console.error('get medio error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const modal = new bootstrap.Modal('#modal-medio');
      modal.show();
    }
  }

  let saving = false;
  async function saveMedio() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-medio');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const payload = {
      id: Number(document.querySelector('#medio-id')?.value || 0),
      codigo: (document.querySelector('#medio-codigo')?.value || '').trim(),
      nombre: (document.querySelector('#medio-nombre')?.value || '').trim(),
      notas: (document.querySelector('#medio-notas')?.value || '').trim(),
      orden: Number(document.querySelector('#medio-orden')?.value || 0),
      activo: document.querySelector('#medio-activo')?.checked ? 1 : 0
    };

    if (!payload.nombre) {
      alert('El nombre es obligatorio');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    try {
      await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const modalEl = document.querySelector('#modal-medio');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK');
    } catch (e) {
      if (e.status === 409) alert('El código ya existe.');
      else if (e.data?.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el medio de pago');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delMedio(id) {
    if (!confirm('¿Eliminar el medio de pago? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo borrar');
      console.error('delete error', e);
    }
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-medio')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-medio')) {
      ev.preventDefault();
      saveMedio();
      return;
    }

    const tr = ev.target.closest('#medios-tbody tr');
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
        delMedio(id);
        return;
      }
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'medios-search') {
      loadList();
    }
  });

  // Filtro por activo
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-activo') {
      loadList();
    }
  });

  // Bootstrap inicial
  const bootList = () => {
    if (here()) loadList();
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();