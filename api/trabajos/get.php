<?php
// /api/trabajos/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Obtener trabajo con datos relacionados
  $stmt = $db->prepare("
    SELECT 
      t.*,
      c.contacto_nombre AS cliente_nombre,
      c.razon_social AS cliente_razon_social,
      c.email AS cliente_email,
      c.telefono AS cliente_telefono,
      s.nombre AS servicio_nombre,
      s.codigo AS servicio_codigo,
      p.codigo AS presupuesto_codigo,
      p.total AS presupuesto_total
    FROM prm_trabajos t
    INNER JOIN clientes c ON c.id = t.cliente_id
    LEFT JOIN prm_servicios s ON s.id = t.servicio_id
    LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id
    WHERE t.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $trabajo = $stmt->get_result()->fetch_assoc();
  
  if (!$trabajo) {
    http_response_code(404);
    echo json_encode(['error' => 'trabajo no encontrado']);
    exit;
  }

  // Obtener pagos del trabajo
  $stmt_pagos = $db->prepare("
    SELECT * FROM prm_trabajos_pagos
    WHERE trabajo_id = ?
    ORDER BY fecha_pago DESC, id DESC
  ");
  $stmt_pagos->bind_param('i', $id);
  $stmt_pagos->execute();
  $pagos = $stmt_pagos->get_result()->fetch_all(MYSQLI_ASSOC);

  // Calcular totales de pagos
  $total_pagado = 0;
  $pagos_confirmados = 0;
  $pagos_pendientes = 0;
  
  foreach ($pagos as $pago) {
    if ($pago['estado'] === 'confirmado') {
      $total_pagado += (float)$pago['monto'];
      $pagos_confirmados++;
    } else if ($pago['estado'] === 'pendiente') {
      $pagos_pendientes++;
    }
  }

  // Obtener historial de estados
  $stmt_hist = $db->prepare("
    SELECT 
      h.*,
      u.nombre AS usuario_nombre,
      u.apellido AS usuario_apellido
    FROM prm_trabajos_historial h
    LEFT JOIN users u ON u.id = h.usuario_id
    WHERE h.trabajo_id = ?
    ORDER BY h.fecha DESC
    LIMIT 20
  ");
  $stmt_hist->bind_param('i', $id);
  $stmt_hist->execute();
  $historial = $stmt_hist->get_result()->fetch_all(MYSQLI_ASSOC);

  // Construir respuesta
  $trabajo['pagos'] = $pagos;
  $trabajo['historial'] = $historial;
  $trabajo['resumen_pagos'] = [
    'total_pagado' => $total_pagado,
    'saldo' => (float)$trabajo['saldo'],
    'cantidad_pagos' => count($pagos),
    'pagos_confirmados' => $pagos_confirmados,
    'pagos_pendientes' => $pagos_pendientes,
    'porc_pagado' => $trabajo['total'] > 0 
      ? round(($total_pagado / (float)$trabajo['total']) * 100, 2) 
      : 0,
  ];

  // Calcular días
  if ($trabajo['fecha_entrega_estimada']) {
    $diff = strtotime($trabajo['fecha_entrega_estimada']) - time();
    $trabajo['dias_para_entrega'] = floor($diff / (60 * 60 * 24));
  }

  http_response_code(200);
  echo json_encode($trabajo, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}