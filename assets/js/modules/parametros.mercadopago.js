// assets/js/modules/parametros.mercadopago.js
(() => {
  const API = {
    get: '/api/parameters/mercadopago/get.php',
    save: '/api/parameters/mercadopago/save.php',
    test: '/api/parameters/mercadopago/test.php'
  };

  function here() {
    const hash = location.hash.replace(/\/+$/, '');
    return hash === '#/parametros/mercadopago';
  }

  async function fetchJSON(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    if (r.status === 204) return null;
    const data = await r.json().catch(() => null);
    if (!r.ok) throw { status: r.status, data };
    return data;
  }

  function showAlert(message, type = 'info') {
    const container = document.querySelector('#mp-alert-container');
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

    // Auto-ocultar después de 5 segundos
    setTimeout(() => {
      alert.remove();
    }, 5000);
  }

  async function load() {
    if (!here()) return;
    
    try {
      const data = await fetchJSON(API.get);
      
      if (!data) {
        console.log('No hay configuración previa, usando valores por defecto');
        return;
      }

      // Verificar que los elementos existan antes de asignar valores
      const elements = {
        prodPublicKey: document.querySelector('#mp-prod-public-key'),
        prodAccessToken: document.querySelector('#mp-prod-access-token'),
        prodWebhook: document.querySelector('#mp-prod-webhook'),
        prodSuccess: document.querySelector('#mp-prod-success-url'),
        prodPending: document.querySelector('#mp-prod-pending-url'),
        prodFailure: document.querySelector('#mp-prod-failure-url'),
        prodActivo: document.querySelector('#mp-prod-activo'),
        testPublicKey: document.querySelector('#mp-test-public-key'),
        testAccessToken: document.querySelector('#mp-test-access-token'),
        testActivo: document.querySelector('#mp-test-activo'),
        modo: document.querySelector('#mp-modo'),
        expiracion: document.querySelector('#mp-expiracion'),
        intentos: document.querySelector('#mp-intentos'),
        statement: document.querySelector('#mp-statement'),
        emailNotif: document.querySelector('#mp-email-notif'),
        autoReturn: document.querySelector('#mp-auto-return'),
        binaryMode: document.querySelector('#mp-binary-mode')
      };

      // Si algún elemento no existe, salir
      if (!elements.prodPublicKey) {
        console.log('DOM no está listo, esperando...');
        return;
      }

      // Credenciales de Producción
      elements.prodPublicKey.value = data.prod_public_key || '';
      elements.prodAccessToken.value = data.prod_access_token || '';
      elements.prodWebhook.value = data.prod_webhook_url || '';
      elements.prodSuccess.value = data.prod_success_url || '';
      elements.prodPending.value = data.prod_pending_url || '';
      elements.prodFailure.value = data.prod_failure_url || '';
      elements.prodActivo.checked = data.prod_activo == 1;

      // Credenciales de Test
      elements.testPublicKey.value = data.test_public_key || '';
      elements.testAccessToken.value = data.test_access_token || '';
      elements.testActivo.checked = data.test_activo == 1;

      // Configuración Adicional
      elements.modo.value = data.modo_activo || 'test';
      elements.expiracion.value = data.expiracion_minutos || 30;
      elements.intentos.value = data.max_intentos || 2;
      elements.statement.value = data.statement_descriptor || '';
      elements.emailNotif.value = data.email_notificaciones || '';
      elements.autoReturn.checked = data.auto_return == 1;
      elements.binaryMode.checked = data.binary_mode == 1;

    } catch (e) {
      console.error('Error cargando configuración MP:', e);
      if (here()) {
        showAlert('Error al cargar la configuración de Mercado Pago', 'error');
      }
    }
  }

  let saving = false;
  async function guardar() {
    if (saving) return;
    saving = true;

    const btn = document.querySelector('#btn-guardar-mp');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    try {
      // Validar formularios
      const formProd = document.querySelector('#form-mp-produccion');
      const formTest = document.querySelector('#form-mp-test');
      
      const modoActivo = document.querySelector('#mp-modo').value;
      
      // Validar según el modo activo
      if (modoActivo === 'produccion') {
        if (!formProd.checkValidity()) {
          formProd.classList.add('was-validated');
          throw new Error('Complete las credenciales de producción requeridas');
        }
      } else {
        if (!formTest.checkValidity()) {
          formTest.classList.add('was-validated');
          throw new Error('Complete las credenciales de test requeridas');
        }
      }

      const payload = {
        // Producción
        prod_public_key: document.querySelector('#mp-prod-public-key').value.trim(),
        prod_access_token: document.querySelector('#mp-prod-access-token').value.trim(),
        prod_webhook_url: document.querySelector('#mp-prod-webhook').value.trim() || null,
        prod_success_url: document.querySelector('#mp-prod-success-url').value.trim() || null,
        prod_pending_url: document.querySelector('#mp-prod-pending-url').value.trim() || null,
        prod_failure_url: document.querySelector('#mp-prod-failure-url').value.trim() || null,
        prod_activo: document.querySelector('#mp-prod-activo').checked ? 1 : 0,
        
        // Test
        test_public_key: document.querySelector('#mp-test-public-key').value.trim(),
        test_access_token: document.querySelector('#mp-test-access-token').value.trim(),
        test_activo: document.querySelector('#mp-test-activo').checked ? 1 : 0,
        
        // Configuración Adicional
        modo_activo: modoActivo,
        expiracion_minutos: Number(document.querySelector('#mp-expiracion').value) || 30,
        max_intentos: Number(document.querySelector('#mp-intentos').value) || 2,
        statement_descriptor: document.querySelector('#mp-statement').value.trim() || null,
        email_notificaciones: document.querySelector('#mp-email-notif').value.trim() || null,
        auto_return: document.querySelector('#mp-auto-return').checked ? 1 : 0,
        binary_mode: document.querySelector('#mp-binary-mode').checked ? 1 : 0
      };

      const result = await fetchJSON(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!result || !result.ok) {
        throw new Error(result?.error || 'Error al guardar');
      }

      showAlert('Configuración de Mercado Pago guardada correctamente', 'success');
      load(); // Recargar datos

    } catch (e) {
      console.error('Error guardando configuración MP:', e);
      showAlert(e.message || 'Error al guardar la configuración', 'error');
    } finally {
      saving = false;
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar Configuración';
      }
    }
  }

  async function probarConexion() {
    const btn = document.querySelector('#btn-test-connection');
    const statusDiv = document.querySelector('#mp-connection-status');
    
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Probando...';
    }

    if (statusDiv) {
      statusDiv.innerHTML = `
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Probando conexión...</span>
        </div>
        <p class="mt-2 text-muted">Verificando credenciales...</p>
      `;
    }

    try {
      const modoActivo = document.querySelector('#mp-modo').value;
      
      const publicKey = modoActivo === 'produccion'
        ? document.querySelector('#mp-prod-public-key').value.trim()
        : document.querySelector('#mp-test-public-key').value.trim();
      
      const accessToken = modoActivo === 'produccion'
        ? document.querySelector('#mp-prod-access-token').value.trim()
        : document.querySelector('#mp-test-access-token').value.trim();

      if (!publicKey || !accessToken) {
        throw new Error('Debe completar las credenciales antes de probar la conexión');
      }

      const result = await fetchJSON(API.test, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          modo: modoActivo,
          public_key: publicKey,
          access_token: accessToken
        })
      });

      if (!result) {
        throw new Error('No se recibió respuesta del servidor');
      }

      if (result.ok) {
        if (statusDiv) {
          statusDiv.innerHTML = `
            <div class="alert alert-success mb-0">
              <i class="bi bi-check-circle me-2"></i>
              <strong>Conexión exitosa</strong>
              <hr>
              <p class="mb-0"><strong>Usuario ID:</strong> ${result.user_id || 'N/A'}</p>
              <p class="mb-0"><strong>Email:</strong> ${result.email || 'N/A'}</p>
              <p class="mb-0"><strong>Modo:</strong> ${modoActivo === 'produccion' ? 'Producción' : 'Test'}</p>
            </div>
          `;
        }
        showAlert('Conexión con Mercado Pago establecida correctamente', 'success');
      } else {
        throw new Error(result.error || 'Error en la conexión');
      }

    } catch (e) {
      console.error('Error probando conexión MP:', e);
      
      if (statusDiv) {
        statusDiv.innerHTML = `
          <div class="alert alert-danger mb-0">
            <i class="bi bi-x-circle me-2"></i>
            <strong>Error en la conexión</strong>
            <hr>
            <p class="mb-0">${e.message}</p>
          </div>
        `;
      }
      
      showAlert('Error al probar la conexión: ' + e.message, 'error');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-wifi me-1"></i>Probar Conexión';
      }
    }
  }

  function init() {
    // Toggle para mostrar/ocultar tokens
    const toggleProd = document.querySelector('#toggle-prod-token');
    const toggleTest = document.querySelector('#toggle-test-token');
    
    if (toggleProd) {
      toggleProd.addEventListener('change', (e) => {
        const input = document.querySelector('#mp-prod-access-token');
        input.type = e.target.checked ? 'text' : 'password';
      });
    }

    if (toggleTest) {
      toggleTest.addEventListener('change', (e) => {
        const input = document.querySelector('#mp-test-access-token');
        input.type = e.target.checked ? 'text' : 'password';
      });
    }

    // Botón guardar
    const btnGuardar = document.querySelector('#btn-guardar-mp');
    if (btnGuardar) {
      btnGuardar.addEventListener('click', guardar);
    }

    // Botón probar conexión
    const btnTest = document.querySelector('#btn-test-connection');
    if (btnTest) {
      btnTest.addEventListener('click', probarConexion);
    }

    // Validar longitud de statement descriptor
    const statement = document.querySelector('#mp-statement');
    if (statement) {
      statement.addEventListener('input', (e) => {
        if (e.target.value.length > 22) {
          e.target.value = e.target.value.substring(0, 22);
        }
      });
    }
  }

  // Navegar
  window.addEventListener('hashchange', () => {
    if (here()) {
      // Esperar un poco para que el DOM se renderice
      setTimeout(() => {
        init();
        load();
      }, 100);
    }
  });

  // Escuchar evento de navegación del router
  document.addEventListener('orion:navigate', (e) => {
    if (here()) {
      setTimeout(() => {
        init();
        load();
      }, 100);
    }
  });

  if (here()) {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
        init();
        load();
      }, 100);
    });
  }

  // API pública
  window.parametrosMercadoPago = {
    load,
    guardar,
    probarConexion
  };

})();