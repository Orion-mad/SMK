// assets/js/modules/parametros.conceptos.js
(() => {
  const API = {
    list: '/api/parameters/conceptos/list.php',
    get: id => `/api/parameters/conceptos/get.php?id=${id}`,
    save: '/api/parameters/conceptos/save.php',
    del: id => `/api/parameters/conceptos/delete.php?id=${id}`,
    categorias: '/api/parameters/conceptos/categorias/select.php',
    entidadesAvailable: tipo => `/api/parameters/conceptos/entidades/available.php?tipo=${tipo}`,
    entidadesSearch: (tipo, q) => `/api/parameters/conceptos/entidades/search.php?tipo=${tipo}&q=${encodeURIComponent(q)}`,
  };

  let entidadesSeleccionadas = [];
  let entidadSelected = null;

  function here() {
    const hash = location.hash.replace(/\/+$/,'');
    return hash === '#/parametros/conceptos';
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

  const tipoBadge = {
    'ingreso': '<span class="badge bg-success">Ingreso</span>',
    'egreso': '<span class="badge bg-danger">Egreso</span>'
  };

  const tipoEntidadIcon = {
    'cliente': 'bi-person',
    'proveedor': 'bi-building',
    'empleado': 'bi-person-badge',
    'otro': 'bi-question-circle'
  };

  function rowTpl(c) {
    const categoria = c.categoria_nombre 
      ? `<span class="badge" style="background-color: ${c.categoria_color || '#6c757d'}">${c.categoria_nombre}</span>`
      : '<span class="text-muted">Sin categoría</span>';
    
    const costoBase = c.costo_base !== null && c.costo_base !== undefined
      ? `${c.moneda_costo} ${money(c.costo_base)}`
      : '<span class="text-muted">Variable</span>';

    return `<tr data-id="${c.id}">
      <td>${c.id}</td>
      <td class="fw-semibold">${c.codigo}</td>
      <td>${c.nombre}</td>
      <td>${categoria}</td>
      <td>${tipoBadge[c.tipo_flujo] || c.tipo_flujo}</td>
      <td class="text-end">${costoBase}</td>
      <td>${c.moneda_costo || 'ARG'}</td>
      <td class="text-center">
        <span class="badge bg-secondary">${c.total_entidades || 0}</span>
      </td>
      <td>${c.activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1 btn-edit">Editar</button>
        <button class="btn btn-sm btn-outline-danger btn-del">Borrar</button>
      </td>
    </tr>`;
  }

  async function loadCategorias() {
    try {
      const categorias = await fetchJSON(API.categorias);
      const select = document.querySelector('#concepto-categoria');
      const filterSelect = document.querySelector('#filter-categoria');
      
      if (select) {
        select.innerHTML = '<option value="">-- Sin categoría --</option>';
        categorias.forEach(cat => {
          select.innerHTML += `<option value="${cat.id}">${cat.nombre}</option>`;
        });
      }

      if (filterSelect) {
        filterSelect.innerHTML = '<option value="">Todas categorías</option>';
        categorias.forEach(cat => {
          filterSelect.innerHTML += `<option value="${cat.id}">${cat.nombre}</option>`;
        });
      }
    } catch (e) {
      console.error('Error cargando categorías', e);
    }
  }

  async function loadList() {
    if (!here()) return;
    const tbody = document.querySelector('#conceptos-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="10" class="text-center p-4">Cargando...</td></tr>`;

    try {
      const params = new URLSearchParams();
      
      const filterTipo = document.querySelector('#filter-tipo-flujo');
      if (filterTipo && filterTipo.value) {
        params.set('tipo_flujo', filterTipo.value);
      }

      const filterCat = document.querySelector('#filter-categoria');
      if (filterCat && filterCat.value) {
        params.set('categoria_id', filterCat.value);
      }

      const filterActivo = document.querySelector('#filter-activo');
      if (filterActivo && filterActivo.value !== '') {
        params.set('activo', filterActivo.value);
      }

      const q = document.querySelector('#conceptos-search')?.value || '';
      if (q) params.set('q', q);

      const url = API.list + (params.toString() ? '?' + params.toString() : '');
      const result = await fetchJSON(url);
      
      const items = Array.isArray(result) ? result : (result?.items || []);

      tbody.innerHTML = (items.length) 
        ? items.map(rowTpl).join('') 
        : `<tr><td colspan="10" class="text-center p-4 text-muted">Sin datos</td></tr>`;
    } catch (e) {
      console.error('Conceptos list error', e);
      tbody.innerHTML = `<tr><td colspan="10" class="text-danger p-3">Error cargando datos</td></tr>`;
    }
  }

  function clearForm() {
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#concepto-id', '0');
    set('#concepto-codigo', '');
    set('#concepto-nombre', '');
    set('#concepto-descripcion', '');
    set('#concepto-categoria', '');
    set('#concepto-tipo-flujo', 'ingreso');
    set('#concepto-costo-base', '');
    set('#concepto-moneda', 'ARG');
    set('#concepto-periodicidad', 'unico');
    set('#concepto-cuenta', '');
    set('#concepto-orden', '0');
    
    const chkAfip = document.querySelector('#concepto-imputable-afip');
    const chkAprob = document.querySelector('#concepto-requiere-aprobacion');
    const chkActivo = document.querySelector('#concepto-activo');
    
    if (chkAfip) chkAfip.checked = false;
    if (chkAprob) chkAprob.checked = false;
    if (chkActivo) chkActivo.checked = true;

    entidadesSeleccionadas = [];
    renderEntidades();
    
    const historialSection = document.querySelector('#historial-section');
    if (historialSection) historialSection.style.display = 'none';
  }

  function renderEntidades() {
    const container = document.querySelector('#entidades-list');
    if (!container) return;

    if (entidadesSeleccionadas.length === 0) {
      container.innerHTML = '<p class="text-muted text-center">No hay entidades relacionadas</p>';
      return;
    }

    container.innerHTML = entidadesSeleccionadas.map((ent, idx) => `
      <div class="card mb-2">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="${tipoEntidadIcon[ent.tipo]} fs-5"></i>
            <div>
              <strong>${ent.nombre}</strong>
              <small class="d-block text-muted">${ent.tipo.charAt(0).toUpperCase() + ent.tipo.slice(1)}</small>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            ${ent.es_principal ? '<span class="badge bg-primary">Principal</span>' : ''}
            <button class="btn btn-sm btn-outline-danger" data-remove-entidad="${idx}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      </div>
    `).join('');
  }

  function renderHistorial(historial) {
    const container = document.querySelector('#historial-list');
    const section = document.querySelector('#historial-section');
    
    if (!container || !section) return;

    if (!historial || historial.length === 0) {
      section.style.display = 'none';
      return;
    }

    section.style.display = 'block';
    container.innerHTML = historial.map(h => `
      <div class="border-start border-3 border-info ps-3 mb-2">
        <div class="fw-semibold">${h.campo.replace('_', ' ')}</div>
        <div class="text-muted">
          <span class="badge bg-light text-dark">${h.valor_anterior || 'NULL'}</span>
          <i class="bi bi-arrow-right mx-2"></i>
          <span class="badge bg-light text-dark">${h.valor_nuevo || 'NULL'}</span>
        </div>
        <small class="text-muted">${new Date(h.fecha).toLocaleString()} · ${h.usuario_nombre}</small>
      </div>
    `).join('');
  }

  async function openForm(id = 0) {
    if (!here()) return;
    
    // Cargar categorías primero
    await loadCategorias();
    
    // Limpiar formulario
    clearForm();

    if (id > 0) {
      try {
        const c = await fetchJSON(API.get(id));
        document.querySelector('#concepto-id').value = c.id;
        document.querySelector('#concepto-codigo').value = c.codigo || '';
        document.querySelector('#concepto-nombre').value = c.nombre || '';
        document.querySelector('#concepto-descripcion').value = c.descripcion || '';
        document.querySelector('#concepto-categoria').value = c.categoria_id || '';
        document.querySelector('#concepto-tipo-flujo').value = c.tipo_flujo || 'ingreso';
        document.querySelector('#concepto-costo-base').value = c.costo_base || '';
        document.querySelector('#concepto-moneda').value = c.moneda_costo || 'ARG';
        document.querySelector('#concepto-periodicidad').value = c.periodicidad || 'unico';
        document.querySelector('#concepto-cuenta').value = c.cuenta_contable || '';
        document.querySelector('#concepto-orden').value = c.orden || 0;
        
        const chkAfip = document.querySelector('#concepto-imputable-afip');
        const chkAprob = document.querySelector('#concepto-requiere-aprobacion');
        const chkActivo = document.querySelector('#concepto-activo');
        
        if (chkAfip) chkAfip.checked = !!c.imputable_afip;
        if (chkAprob) chkAprob.checked = !!c.requiere_aprobacion;
        if (chkActivo) chkActivo.checked = !!c.activo;

        // Cargar entidades
        entidadesSeleccionadas = (c.entidades || []).map(ent => ({
          tipo: ent.entidad_tipo,
          id: ent.entidad_id,
          nombre: ent.entidad_nombre,
          es_principal: ent.es_principal
        }));
        renderEntidades();

        // Renderizar historial
        renderHistorial(c.historial || []);

      } catch (e) {
        console.error('get concepto error', e);
      }
    }

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-concepto');
      m.show();
    }
  }

  let saving = false;
  async function saveConcepto() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-save-concepto');
    if (btn) {
      btn.disabled = true;
      btn.dataset.original = btn.innerHTML;
      btn.innerHTML = 'Guardando...';
    }

    const costoBase = document.querySelector('#concepto-costo-base')?.value;

    const payload = {
      id: Number(document.querySelector('#concepto-id')?.value || 0),
      codigo: (document.querySelector('#concepto-codigo')?.value || '').trim(),
      nombre: (document.querySelector('#concepto-nombre')?.value || '').trim(),
      descripcion: (document.querySelector('#concepto-descripcion')?.value || '').trim(),
      categoria_id: Number(document.querySelector('#concepto-categoria')?.value) || null,
      tipo_flujo: document.querySelector('#concepto-tipo-flujo')?.value || 'ingreso',
      costo_base: costoBase && costoBase !== '' ? Number(costoBase) : null,
      moneda_costo: document.querySelector('#concepto-moneda')?.value || 'ARG',
      periodicidad: document.querySelector('#concepto-periodicidad')?.value || 'unico',
      cuenta_contable: (document.querySelector('#concepto-cuenta')?.value || '').trim() || null,
      orden: Number(document.querySelector('#concepto-orden')?.value || 0),
      imputable_afip: document.querySelector('#concepto-imputable-afip')?.checked ? 1 : 0,
      requiere_aprobacion: document.querySelector('#concepto-requiere-aprobacion')?.checked ? 1 : 0,
      activo: document.querySelector('#concepto-activo')?.checked ? 1 : 0,
      entidades: entidadesSeleccionadas.map(ent => ({
        entidad_tipo: ent.tipo,
        entidad_id: ent.id,
        es_principal: ent.es_principal ? 1 : 0
      }))
    };

    if (!payload.nombre) {
      alert('El nombre es obligatorio');
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

      const modalEl = document.querySelector('#modal-concepto');
      if (modalEl && window.bootstrap?.Modal) {
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
      }

      await loadList();
      console.log('Guardado OK', data);
    } catch (e) {
      if (e.status === 409) alert('El código ya existe.');
      else if (e.data && e.data.error) alert('Error: ' + e.data.error);
      else alert('Error guardando el concepto');
      console.error('save error', e);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.original || 'Guardar'; }
      saving = false;
    }
  }

  async function delConcepto(id) {
    if (!confirm('¿Eliminar el concepto? Esta acción no se puede deshacer.')) return;
    try {
      await fetchJSON(API.del(id), { method: 'POST' });
      await loadList();
    } catch (e) {
      alert('No se pudo borrar');
      console.error('delete error', e);
    }
  }

  // Modal de agregar entidad
  function openAddEntidadModal() {
    entidadSelected = null;
    document.querySelector('#entidad-tipo').value = '';
    document.querySelector('#entidad-search').value = '';
    document.querySelector('#entidad-results').innerHTML = '';
    document.querySelector('#entidad-principal').checked = false;

    if (window.bootstrap?.Modal) {
      const m = new bootstrap.Modal('#modal-add-entidad');
      m.show();
    }
  }

  async function searchEntidades(tipo, query) {
    if (!tipo || query.length < 2) {
      document.querySelector('#entidad-results').innerHTML = '';
      return;
    }

    try {
      const results = await fetchJSON(API.entidadesSearch(tipo, query));
      const container = document.querySelector('#entidad-results');
      
      if (results.length === 0) {
        container.innerHTML = '<p class="text-muted small">No se encontraron resultados</p>';
        return;
      }

      container.innerHTML = '<div class="list-group">' + results.map(ent => `
        <button type="button" class="list-group-item list-group-item-action" data-select-entidad='${JSON.stringify(ent)}'>
          <div class="d-flex align-items-center gap-2">
            <i class="${tipoEntidadIcon[ent.tipo]}"></i>
            <div>
              <strong>${ent.nombre}</strong>
              ${ent.email ? `<small class="d-block text-muted">${ent.email}</small>` : ''}
              ${ent.cuit ? `<small class="d-block text-muted">CUIT: ${ent.cuit}</small>` : ''}
              ${ent.legajo ? `<small class="d-block text-muted">Legajo: ${ent.legajo}</small>` : ''}
            </div>
          </div>
        </button>
      `).join('') + '</div>';

    } catch (e) {
      console.error('Error buscando entidades', e);
    }
  }

  function selectEntidad(entData) {
    const esPrincipal = document.querySelector('#entidad-principal')?.checked || false;
    
    // Verificar si ya está agregada
    const existe = entidadesSeleccionadas.some(e => e.tipo === entData.tipo && e.id === entData.id);
    if (existe) {
      alert('Esta entidad ya está agregada');
      return;
    }

    entidadesSeleccionadas.push({
      tipo: entData.tipo,
      id: entData.id,
      nombre: entData.nombre,
      es_principal: esPrincipal
    });

    renderEntidades();

    // Cerrar modal
    const modalEl = document.querySelector('#modal-add-entidad');
    if (modalEl && window.bootstrap?.Modal) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
  }

  function removeEntidad(idx) {
    entidadesSeleccionadas.splice(idx, 1);
    renderEntidades();
  }

  // Delegación global de eventos
  document.addEventListener('click', (ev) => {
    // Verificar botones específicos ANTES de validar la ruta
    if (ev.target.closest('#btn-new-concepto')) {
      ev.preventDefault();
      if (here()) {
        openForm(0);
      }
      return;
    }

    if (ev.target.closest('#btn-save-concepto')) {
      ev.preventDefault();
      if (here()) {
        saveConcepto();
      }
      return;
    }

    if (ev.target.closest('#btn-add-entidad')) {
      ev.preventDefault();
      if (here()) {
        openAddEntidadModal();
      }
      return;
    }

    // Solo validar ruta para los demás eventos
    if (!here()) return;

    const removeBtn = ev.target.closest('[data-remove-entidad]');
    if (removeBtn) {
      ev.preventDefault();
      const idx = Number(removeBtn.dataset.removeEntidad);
      removeEntidad(idx);
      return;
    }

    const selectBtn = ev.target.closest('[data-select-entidad]');
    if (selectBtn) {
      ev.preventDefault();
      const entData = JSON.parse(selectBtn.dataset.selectEntidad);
      selectEntidad(entData);
      return;
    }

    const tr = ev.target.closest('#conceptos-tbody tr');
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
        delConcepto(id);
        return;
      }
    }
  });

  // Búsqueda de entidades en tiempo real
  let searchTimeout = null;
  document.addEventListener('input', (ev) => {
    if (!here()) return;
    
    if (ev.target.id === 'conceptos-search') {
      loadList();
    }

    if (ev.target.id === 'entidad-search') {
      const tipo = document.querySelector('#entidad-tipo')?.value;
      const query = ev.target.value.trim();
      
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        searchEntidades(tipo, query);
      }, 300);
    }
  });

  // Cambio de tipo de entidad
  document.addEventListener('change', (ev) => {
    if (!here()) return;
    
    if (ev.target.id === 'filter-tipo-flujo' || 
        ev.target.id === 'filter-categoria' || 
        ev.target.id === 'filter-activo') {
      loadList();
    }

    if (ev.target.id === 'entidad-tipo') {
      document.querySelector('#entidad-search').value = '';
      document.querySelector('#entidad-results').innerHTML = '';
    }
  });

  // Bootstrap inicial y re-navegación
  const bootList = () => {
    if (here()) {
      loadCategorias();
      loadList();
    }
  };

  window.addEventListener('hashchange', bootList);
  document.addEventListener('DOMContentLoaded', bootList);
  document.addEventListener('orion:navigate', bootList);
})();