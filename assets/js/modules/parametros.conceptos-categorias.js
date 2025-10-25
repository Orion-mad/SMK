// assets/js/modules/parametros.conceptos-categorias.js
(() => {
  const API = {
    list: '/api/parameters/conceptos/categorias/list.php',
    get: id => `/api/parameters/conceptos/categorias/get.php?id=${id}`,
    save: '/api/parameters/conceptos/categorias/save.php',
    del: id => `/api/parameters/conceptos/categorias/delete.php?id=${id}`,
  };

  function here() {
    return location.hash.replace(/\/+$/,'') === '#/parametros/conceptos-categorias';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  const tipoBadge = {
    'ingreso': '<span class="badge bg-success">Ingreso</span>',
    'egreso': '<span class="badge bg-danger">Egreso</span>',
    'ambos': '<span class="badge bg-info">Ambos</span>'
  };

  function rowTpl(c) {
    return `<tr data-id="${c.id}">
      <td>${c.id}</td>
      <td class="fw-semibold">${c.codigo}</td>
      <td>
        <span class="d-inline-block rounded-circle" style="width: 20px; height: 20px; background-color: ${c.color};" title="${c.color}"></span>
      </td>
      <td>
        <i class="${c.icono} me-2" style="color: ${c.color};"></i>${c.nombre}
      </td>
      <td>${tipoBadge[c.tipo_flujo] || c.tipo_flujo}</td>
      <td class="text-center">
        <span class="badge bg-secondary">${c.total_conceptos || 0}</span>
      </td>
      <td class="text-center">${c.orden}</td>
      <td>${c.activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del" ${c.total_conceptos > 0 ? 'disabled title="Tiene conceptos asociados"' : ''}>Borrar</button>
      </td>
    </tr>`;
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#categorias-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="9" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterTipo = document.querySelector('#filter-tipo-flujo');
      if (filterTipo && filterTipo.value) {
        params.set('tipo_flujo', filterTipo.value);
      }

      const filterActivo = document.querySelector('#filter-activo');
      if (filterActivo && filterActivo.value !== '') {
        params.set('activo', filterActivo.value);
      }

      const q = document.querySelector('#categorias-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="9" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Categorías list error', e);
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#categoria-id', '0');
    set('#categoria-codigo', '');
    set('#categoria-nombre', '');
    set('#categoria-descripcion', '');
    set('#categoria-tipo-flujo', 'ambos');
    set('#categoria-color', '#6c757d');
    set('#categoria-icono', 'bi-tag');
    set('#categoria-orden', '0');
    
    const chk = document.querySelector('#categoria-activo');
    if (chk) chk.checked = true;

    updateIconoPreview();
  }

  function updateIconoPreview() {
    const iconoInput = document.querySelector('#categoria-icono');
    const preview = document.querySelector('#icono-preview');
    if (iconoInput && preview) {
      preview.className = iconoInput.value || 'bi bi-tag';
    }
  }

  async function openForm(id = 0) {
    if (!here()) return;
    clearForm();

    if (id > 0) {
      try {
        const c = await fetchJSON(API.get(id));
        document.querySelector('#categoria-id').value = c.id;
        document.querySelector('#categoria-codigo').value = c.codigo || '';
        document.querySelector('#categoria-nombre').value = c.nombre || '';
        document.querySelector('#categoria-descripcion').value = c.descripcion || '';
        document.querySelector('#categoria-tipo-flujo').value = c.tipo_flujo || 'ambos';
        document.querySelector('#categoria-color').value = c.color || '#6c757d';
        document.querySelector('#categoria-icono').value = c.icono || 'bi-tag';
        document.querySelector('#categoria-orden').value = c.orden || 0;
        
        const chk = document.querySelector('#categoria-activo');
        if (chk) chk.checked = !!c.activo;

        updateIconoPreview();
      } catch (e) {
        console.error('get categoria error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-categoria');
      m.show();
    } else {
      const modalEl = document.querySelector('#modal-categoria');
      if (modalEl) { modalEl.classList.add('show'); modalEl.style.display = 'block'; }
    }
  }

  let saving = false;
  async function saveCategoria() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-categoria');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const payload = {
      id: Number(document.querySelector('#categoria-id')?.value || 0),
      codigo: (document.querySelector('#categoria-codigo')?.value || '').trim(),
      nombre: (document.querySelector('#categoria-nombre')?.value || '').trim(),
      descripcion: (document.querySelector('#categoria-descripcion')?.value || '').trim(),
      tipo_flujo: document.querySelector('#categoria-tipo-flujo')?.value || 'ambos',
      color: document.querySelector('#categoria-color')?.value || '#6c757d',
      icono: document.querySelector('#categoria-icono')?.value || 'bi-tag',
      orden: Number(document.querySelector('#categoria-orden')?.value || 0),
      activo: document.querySelector('#categoria-activo')?.checked ? 1 : 0
    };

    if (!payload.nombre) {
      alert('El nombre es obligatorio');
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
      return;
    }

    // Validar formato de color
    if (!/^#[0-9A-Fa-f]{6}$/.test(payload.color)) {
      alert('El color debe estar en formato hexadecimal (#RRGGBB)');
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

      const modalEl = document.querySelector('#modal-categoria');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      } else if (modalEl) {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando la categoría');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delCategoria(id) {
    if (!confirm('¿Eliminar la categoría? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      if (e.status === 409 && e.data?.detail) {
        alert(e.data.detail);
      } else {
        alert('No se pudo borrar');
      }
      console.error('delete error', e);
    }
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    if (!here()) return;

    if (ev.target.closest('#btn-new-categoria')) {
      ev.preventDefault();
      openForm(0);
      return;
    }

    if (ev.target.closest('#btn-save-categoria')) {
      ev.preventDefault();
      saveCategoria();
      return;
    }

    const tr = ev.target.closest('#categorias-tbody tr');
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
        delCategoria(id);
        return;
      }
    }
  });

  // Actualizar preview de icono en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'categoria-icono') {
      updateIconoPreview();
    }
  });

  // Búsqueda en tiempo real
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'categorias-search') {
      loadList();
    }
  });

  // Filtros
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    if (ev.target.id === 'filter-tipo-flujo' || ev.target.id === 'filter-activo') {
      loadList();
    }
  });

  // Bootstrap inicial y re-navegación
  const bootList = () => {
    if (here()) {
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();