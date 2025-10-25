<?php
// /api/contable/trabajos/get.php
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

  // Obtener datos del trabajo con información del cliente y servicio
  $sql = "SELECT 
            t.*,
            c.razon_social AS cliente_razon_social,
            c.contacto_nombre AS cliente_nombre,
            c.tipo_doc AS cliente_tipo_doc,
            c.nro_doc AS cliente_doc,
            c.email AS cliente_email,
            s.nombre AS servicio_nombre,
            s.codigo AS servicio_codigo
          FROM prm_trabajos t
          INNER JOIN clientes c ON c.id = t.cliente_id
          LEFT JOIN prm_servicios s ON s.id = t.servicio_id
          WHERE t.id = ?
          LIMIT 1";

  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $trabajo = $result->fetch_assoc();
  $stmt->close();

  if (!$trabajo) {
    http_response_code(404);
    echo json_encode(['error' => 'Trabajo no encontrado']);
    exit;
  }

  // Calcular total pagado y saldo actual
  $pagosSql = "SELECT COALESCE(SUM(monto), 0) AS total_pagado 
               FROM prm_trabajos_pagos 
               WHERE trabajo_id = ? AND estado = 'confirmado'";
  $pagosStmt = $db->prepare($pagosSql);
  $pagosStmt->bind_param('i', $id);
  $pagosStmt->execute();
  $pagosResult = $pagosStmt->get_result();
  $pagosData = $pagosResult->fetch_assoc();
  $pagosStmt->close();

  $totalPagado = (float)$pagosData['total_pagado'];
  $saldo = (float)$trabajo['total'] - $totalPagado;

  // Preparar respuesta
  $data = [
    'id'                    => (int)$trabajo['id'],
    'codigo'                => $trabajo['codigo'],
    'nombre'                => $trabajo['nombre'],
    'descripcion'           => $trabajo['descripcion'],
    'cliente_id'            => (int)$trabajo['cliente_id'],
    'cliente_razon_social'  => $trabajo['cliente_razon_social'],
    'cliente_nombre'        => $trabajo['cliente_nombre'],
    'cliente_tipo_doc'      => $trabajo['cliente_tipo_doc'],
    'cliente_doc'           => $trabajo['cliente_doc'],
    'cliente_email'         => $trabajo['cliente_email'],
    'servicio_id'           => $trabajo['servicio_id'] ? (int)$trabajo['servicio_id'] : null,
    'servicio_nombre'       => $trabajo['servicio_nombre'],
    'servicio_codigo'       => $trabajo['servicio_codigo'],
    'presupuesto_id'        => $trabajo['presupuesto_id'] ? (int)$trabajo['presupuesto_id'] : null,
    'fecha_ingreso'         => $trabajo['fecha_ingreso'],
    'fecha_entrega_estimada'=> $trabajo['fecha_entrega_estimada'],
    'fecha_entrega_real'    => $trabajo['fecha_entrega_real'],
    'estado'                => $trabajo['estado'],
    'prioridad'             => $trabajo['prioridad'],
    'total'                 => (float)$trabajo['total'],
    'moneda'                => $trabajo['moneda'],
    'medio_pago'            => $trabajo['medio_pago'],
    'total_pagado'          => $totalPagado,
    'saldo'                 => $saldo,
    'requiere_homologacion' => (bool)$trabajo['requiere_homologacion'],
    'homologacion_estado'   => $trabajo['homologacion_estado'],
    'observaciones'         => $trabajo['observaciones'],
    'creado_en'             => $trabajo['creado_en'],
    'actualizado_en'        => $trabajo['actualizado_en'],
  ];

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[contable/trabajos/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}