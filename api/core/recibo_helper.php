<?php
// /api/core/recibo_helper.php
declare(strict_types=1);

/**
 * Generador de recibos en PDF para pagos de trabajos
 * Requiere FPDF o similar (a implementar según librería disponible)
 */

/**
 * Genera un número de recibo único
 */
function generar_numero_recibo(mysqli $db): string {
  $anio = date('Y');
  $query = $db->query("
    SELECT MAX(CAST(SUBSTRING(recibo_numero, 6) AS UNSIGNED)) as ultimo
    FROM prm_trabajos_pagos 
    WHERE recibo_numero LIKE '{$anio}-%'
  ");
  
  $row = $query->fetch_assoc();
  $ultimo = (int)($row['ultimo'] ?? 0);
  $siguiente = $ultimo + 1;
  
  return sprintf('%s-%05d', $anio, $siguiente);
}

/**
 * Genera un recibo en PDF y lo guarda
 * 
 * @param mysqli $db Conexión a BD
 * @param int $pagoId ID del pago
 * @return array ['ok' => bool, 'path' => string, 'numero' => string]
 */
function generar_recibo_pago(mysqli $db, int $pagoId): array {
  // Obtener datos del pago y trabajo
  $stmt = $db->prepare("
    SELECT 
      p.*,
      t.codigo as trabajo_codigo,
      t.nombre as trabajo_nombre,
      t.total as trabajo_total,
      c.nombre as cliente_nombre,
      c.razon_social,
      c.cuit,
      c.domicilio,
      c.localidad,
      c.provincia
    FROM prm_trabajos_pagos p
    INNER JOIN prm_trabajos t ON t.id = p.trabajo_id
    INNER JOIN cuentas_clientes c ON c.id = t.cliente_id
    WHERE p.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $pagoId);
  $stmt->execute();
  $pago = $stmt->get_result()->fetch_assoc();
  
  if (!$pago) {
    return ['ok' => false, 'error' => 'Pago no encontrado'];
  }

  // Generar número de recibo
  $numeroRecibo = generar_numero_recibo($db);
  $fechaRecibo = date('Y-m-d');

  // Crear directorio si no existe
  $uploadDir = __DIR__ . '/../../uploads/recibos/' . date('Y') . '/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $filename = "recibo_{$numeroRecibo}.pdf";
  $filepath = $uploadDir . $filename;
  $relativePath = 'uploads/recibos/' . date('Y') . '/' . $filename;

  // Generar PDF (implementación básica - ajustar según librería)
  try {
    // TODO: Implementar con FPDF, TCPDF o similar
    // Por ahora, generamos un HTML simple que puede convertirse a PDF
    
    $html = generar_html_recibo($pago, $numeroRecibo, $fechaRecibo);
    
    // Guardar HTML temporalmente (en producción convertir a PDF)
    file_put_contents($filepath . '.html', $html);
    
    // Actualizar registro del pago
    $stmt = $db->prepare("
      UPDATE prm_trabajos_pagos 
      SET recibo_generado = 1,
          recibo_numero = ?,
          recibo_fecha = ?,
          recibo_path = ?
      WHERE id = ?
    ");
    
    $stmt->bind_param('sssi', $numeroRecibo, $fechaRecibo, $relativePath, $pagoId);
    $stmt->execute();

    return [
      'ok' => true,
      'numero' => $numeroRecibo,
      'path' => $relativePath,
      'url' => '/' . $relativePath . '.html' // temporal
    ];

  } catch (Exception $e) {
    return ['ok' => false, 'error' => $e->getMessage()];
  }
}

/**
 * Genera HTML del recibo (base para PDF)
 */
function generar_html_recibo(array $pago, string $numero, string $fecha): string {
  $monto = number_format((float)$pago['monto'], 2, ',', '.');
  $trabajoTotal = number_format((float)$pago['trabajo_total'], 2, ',', '.');
  
  return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Recibo {$numero}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
    .recibo-numero { font-size: 24px; font-weight: bold; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
    .campo { margin-bottom: 15px; }
    .campo label { font-weight: bold; display: block; margin-bottom: 5px; }
    .campo .valor { padding: 8px; background: #f5f5f5; border-radius: 4px; }
    .monto-box { background: #e8f4f8; border: 2px solid #0066cc; padding: 20px; text-align: center; margin: 30px 0; }
    .monto-box .monto { font-size: 32px; font-weight: bold; color: #0066cc; }
    .footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 12px; }
  </style>
</head>
<body>
  <div class="header">
    <div class="recibo-numero">RECIBO Nº {$numero}</div>
    <div>Fecha: {$fecha}</div>
  </div>

  <div class="info-grid">
    <div>
      <div class="campo">
        <label>Cliente:</label>
        <div class="valor">{$pago['cliente_nombre']}</div>
      </div>
      <div class="campo">
        <label>Razón Social:</label>
        <div class="valor">{$pago['razon_social']}</div>
      </div>
      <div class="campo">
        <label>CUIT:</label>
        <div class="valor">{$pago['cuit']}</div>
      </div>
      <div class="campo">
        <label>Domicilio:</label>
        <div class="valor">{$pago['domicilio']}, {$pago['localidad']}, {$pago['provincia']}</div>
      </div>
    </div>
    <div>
      <div class="campo">
        <label>Trabajo:</label>
        <div class="valor">{$pago['trabajo_codigo']} - {$pago['trabajo_nombre']}</div>
      </div>
      <div class="campo">
        <label>Total del Trabajo:</label>
        <div class="valor">\$ {$trabajoTotal}</div>
      </div>
      <div class="campo">
        <label>Medio de Pago:</label>
        <div class="valor">{$pago['medio_pago']}</div>
      </div>
      <div class="campo">
        <label>Referencia:</label>
        <div class="valor">{$pago['referencia']}</div>
      </div>
    </div>
  </div>

  <div class="monto-box">
    <div style="font-size: 18px; margin-bottom: 10px;">MONTO RECIBIDO</div>
    <div class="monto">\$ {$monto}</div>
    <div style="margin-top: 10px; font-size: 14px;">{$pago['moneda']}</div>
  </div>

  <div class="campo">
    <label>Observaciones:</label>
    <div class="valor">{$pago['observaciones']}</div>
  </div>

  <div class="footer">
    <p>Recibo generado automáticamente - Sistema LCARS</p>
    <p>Documento válido como comprobante de pago</p>
  </div>
</body>
</html>
HTML;
}

/**
 * Endpoint para generar recibo desde API
 */
if (basename(__FILE__) === 'generar_recibo.php') {
  header('Content-Type: application/json; charset=utf-8');
  
  require_once __DIR__ . '/../../inc/conect.php';
  
  try {
    $input = json_decode(file_get_contents('php://input'), true);
    $pagoId = (int)($input['pago_id'] ?? $_GET['pago_id'] ?? 0);
    
    if ($pagoId <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'pago_id requerido']);
      exit;
    }

    $db = DB::get();
    $result = generar_recibo_pago($db, $pagoId);
    
    if ($result['ok']) {
      http_response_code(200);
      echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
      http_response_code(400);
      echo json_encode($result);
    }

  } catch (Throwable $e) {
    error_log('[generar_recibo] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
  }
}