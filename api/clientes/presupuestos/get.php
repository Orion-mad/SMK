<?php
// /api/clientes/presupuestos/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Obtener presupuesto con datos del cliente
  $stmt = $db->prepare("
    SELECT 
      p.*,
      CASE 
        WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
        ELSE c.contacto_nombre
      END AS cliente_nombre,
      c.tipo_doc AS cliente_tipo_doc,
      c.nro_doc AS cliente_doc,
      c.email AS cliente_email,
      c.telefono AS cliente_telefono,
      c.direccion AS cliente_direccion,
      c.iva_cond AS cliente_iva_cond
    FROM cli_presupuestos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    WHERE p.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $presupuesto = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$presupuesto) {
    http_response_code(404);
    echo json_encode(['error' => 'no encontrado']);
    exit;
  }

  // Convertir campos numéricos
  $presupuesto['id'] = (int)$presupuesto['id'];
  $presupuesto['cliente_id'] = (int)$presupuesto['cliente_id'];
  $presupuesto['subtotal'] = (float)$presupuesto['subtotal'];
  $presupuesto['descuento_porc'] = (float)$presupuesto['descuento_porc'];
  $presupuesto['descuento_monto'] = (float)$presupuesto['descuento_monto'];
  $presupuesto['iva_porc'] = (float)$presupuesto['iva_porc'];
  $presupuesto['iva_monto'] = (float)$presupuesto['iva_monto'];
  $presupuesto['total'] = (float)$presupuesto['total'];
  $presupuesto['dias_validez'] = (int)$presupuesto['dias_validez'];
  $presupuesto['version'] = (int)$presupuesto['version'];
  $presupuesto['activo'] = (int)$presupuesto['activo'];

  // Obtener items del presupuesto
  $stmt = $db->prepare("
    SELECT 
      i.*,
      s.codigo AS servicio_codigo,
      s.nombre AS servicio_nombre
    FROM cli_presupuestos_items i
    LEFT JOIN prm_servicios s ON s.id = i.servicio_id
    WHERE i.presupuesto_id = ?
    ORDER BY i.orden ASC, i.id ASC
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $items = [];
  while ($row = $result->fetch_assoc()) {
    // Convertir campos numéricos
    $row['id'] = (int)$row['id'];
    $row['presupuesto_id'] = (int)$row['presupuesto_id'];
    $row['servicio_id'] = $row['servicio_id'] ? (int)$row['servicio_id'] : null;
    $row['orden'] = (int)$row['orden'];
    $row['cantidad'] = (float)$row['cantidad'];
    $row['precio_unitario'] = (float)$row['precio_unitario'];
    $row['subtotal'] = (float)$row['subtotal'];
    $row['descuento_porc'] = (float)$row['descuento_porc'];
    $row['descuento_monto'] = (float)$row['descuento_monto'];
    $row['subtotal_con_desc'] = (float)$row['subtotal_con_desc'];
    $row['iva_porc'] = (float)$row['iva_porc'];
    $row['iva_monto'] = (float)$row['iva_monto'];
    $row['total'] = (float)$row['total'];
    $row['activo'] = (int)$row['activo'];
    
    $items[] = $row;
  }
  $stmt->close();

  $presupuesto['items'] = $items;

  // Obtener historial de cambios de estado
  $stmt = $db->prepare("
    SELECT h.*, u.razon_social AS usuario_nombre
    FROM cli_presupuestos_historial h
    LEFT JOIN clientes u ON u.id = h.usuario_id
    WHERE h.presupuesto_id = ?
    ORDER BY h.creado_en DESC
    LIMIT 20
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $historial = [];
  while ($row = $result->fetch_assoc()) {
    $historial[] = $row;
  }
  $stmt->close();

  $presupuesto['historial'] = $historial;

  http_response_code(200);
  echo json_encode($presupuesto, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[presupuestos/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}