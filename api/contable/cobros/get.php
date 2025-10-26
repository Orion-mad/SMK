<?php
// /api/contable/cobros/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    //http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Obtener cobro con datos del cliente
  $stmt = $db->prepare("
    SELECT 
      c.*,
      CONCAT(COALESCE(cl.razon_social, ''), ' ', COALESCE(cl.contacto_nombre, '')) AS cliente_nombre,
      cl.nro_doc AS cliente_documento,
      cl.email AS cliente_email,
      cl.celular AS cliente_celular,
      cl.telefono AS cliente_telefono,
      t.codigo AS trabajo_codigo,
      t.nombre AS trabajo_nombre,
      s.codigo AS servicio_codigo,
      s.nombre AS servicio_nombre
    FROM cnt_cobros c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    LEFT JOIN prm_trabajos t ON t.id = c.trabajo_id
    LEFT JOIN prm_servicios s ON s.id = c.servicio_id
    WHERE c.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $cobro = $stmt->get_result()->fetch_assoc();

  if (!$cobro) {
    //http_response_code(404);
    echo json_encode(['error' => 'Cobro no encontrado']);
    exit;
  }

  // Obtener items del cobro
  $stmtItems = $db->prepare("
    SELECT * FROM cnt_cobros_items
    WHERE cobro_id = ?
    ORDER BY orden ASC, id ASC
  ");
  $stmtItems->bind_param('i', $id);
  $stmtItems->execute();
  $cobro['items'] = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

  // Obtener pagos del cobro
  $stmtPagos = $db->prepare("
    SELECT 
      cp.*,
      mp.nombre AS medio_pago_detalle
    FROM cnt_cobros_pagos cp
    LEFT JOIN prm_medios_pago mp ON mp.id = cp.medio_pago_id
    WHERE cp.cobro_id = ?
    ORDER BY cp.fecha_pago DESC, cp.id DESC
  ");
  $stmtPagos->bind_param('i', $id);
  $stmtPagos->execute();
  $cobro['pagos'] = $stmtPagos->get_result()->fetch_all(MYSQLI_ASSOC);

  // Conversión de tipos
  $cobro['id'] = (int)$cobro['id'];
  $cobro['cliente_id'] = $cobro['cliente_id'] ? (int)$cobro['cliente_id'] : null;
  $cobro['trabajo_id'] = $cobro['trabajo_id'] ? (int)$cobro['trabajo_id'] : null;
  $cobro['servicio_id'] = $cobro['servicio_id'] ? (int)$cobro['servicio_id'] : null;
  $cobro['subtotal'] = (float)$cobro['subtotal'];
  $cobro['descuento'] = (float)$cobro['descuento'];
  $cobro['impuestos'] = (float)$cobro['impuestos'];
  $cobro['total'] = (float)$cobro['total'];
  $cobro['monto_pagado'] = (float)$cobro['monto_pagado'];
  $cobro['saldo'] = (float)$cobro['saldo'];
  $cobro['activo'] = (int)$cobro['activo'];
  $cobro['orden'] = (int)$cobro['orden'];

  http_response_code(200);
  echo json_encode($cobro, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[cobros/get] ' . $e->getMessage());
  //http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}