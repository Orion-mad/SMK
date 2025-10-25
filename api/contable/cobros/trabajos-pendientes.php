<?php
// /api/contable/cobros/trabajos-pendientes.php
// Devuelve trabajos con pagos pendientes de facturar
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  // Obtener pagos de trabajos que no están vinculados a ningún cobro
  $sql = "
    SELECT 
      tp.id AS pago_id,
      tp.trabajo_id,
      tp.fecha_pago,
      tp.monto,
      tp.moneda,
      tp.medio_pago,
      tp.referencia,
      tp.estado,
      t.codigo AS trabajo_codigo,
      t.nombre AS trabajo_nombre,
      t.cliente_id,
      CONCAT(COALESCE(c.razon_social, ''), ' ', COALESCE(c.contacto_nombre, '')) AS cliente_nombre,
      t.total AS trabajo_total
    FROM prm_trabajos_pagos tp
    INNER JOIN prm_trabajos t ON t.id = tp.trabajo_id
    LEFT JOIN clientes c ON c.id = t.cliente_id
    WHERE tp.estado IN ('pendiente', 'pagado')
      AND NOT EXISTS (
        SELECT 1 FROM cnt_cobros_items ci 
        WHERE ci.trabajo_pago_id = tp.id
      )
    ORDER BY tp.fecha_pago DESC, tp.id DESC
  ";

  $result = $db->query($sql);
  $pagos = [];

  while ($row = $result->fetch_assoc()) {
    $pagos[] = [
      'pago_id' => (int)$row['pago_id'],
      'trabajo_id' => (int)$row['trabajo_id'],
      'trabajo_codigo' => $row['trabajo_codigo'],
      'trabajo_nombre' => $row['trabajo_nombre'],
      'cliente_id' => $row['cliente_id'] ? (int)$row['cliente_id'] : null,
      'cliente_nombre' => $row['cliente_nombre'],
      'fecha_pago' => $row['fecha_pago'],
      'monto' => (float)$row['monto'],
      'moneda' => $row['moneda'],
      'medio_pago' => $row['medio_pago'],
      'referencia' => $row['referencia'],
      'estado' => $row['estado'],
      'trabajo_total' => (float)$row['trabajo_total'],
    ];
  }

  http_response_code(200);
  echo json_encode($pagos, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[cobros/trabajos-pendientes] ' . $e->getMessage());
  //http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}