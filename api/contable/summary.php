<?php
// /api/contable/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $db = DB::get();

  // =====================================================
  // RESUMEN DE COBROS
  // =====================================================
  
  $q_cobros = $db->query("
    SELECT 
      COUNT(*) AS total,
      SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
      SUM(CASE WHEN estado = 'parcial' THEN 1 ELSE 0 END) AS parciales,
      SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) AS pagados,
      SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) AS vencidos,
      SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
      SUM(total) AS monto_total,
      SUM(saldo) AS saldo_total
    FROM cnt_cobros
    WHERE activo = 1
  ");
  
  $r_cobros = $q_cobros->fetch_assoc() ?: [
    'total' => 0, 'pendientes' => 0, 'parciales' => 0, 'pagados' => 0,
    'vencidos' => 0, 'cancelados' => 0, 'monto_total' => 0, 'saldo_total' => 0
  ];

  $cobros = [
    'total'        => (int)($r_cobros['total'] ?? 0),
    'pendientes'   => (int)($r_cobros['pendientes'] ?? 0),
    'parciales'    => (int)($r_cobros['parciales'] ?? 0),
    'pagados'      => (int)($r_cobros['pagados'] ?? 0),
    'vencidos'     => (int)($r_cobros['vencidos'] ?? 0),
    'cancelados'   => (int)($r_cobros['cancelados'] ?? 0),
    'monto_total'  => (float)($r_cobros['monto_total'] ?? 0),
    'saldo_total'  => (float)($r_cobros['saldo_total'] ?? 0),
    'moneda'       => 'ARS'
  ];

  // =====================================================
  // RESUMEN DE SERVICIOS
  // =====================================================
  
  // Total de clientes con servicios activos
  $q_servicios = $db->query("
    SELECT 
      COUNT(DISTINCT c.id) AS total_clientes,
      COUNT(DISTINCT CASE 
        WHEN co.estado = 'pagado' THEN c.id 
        ELSE NULL 
      END) AS activos,
      COUNT(DISTINCT CASE 
        WHEN co.estado = 'pendiente' THEN c.id 
        ELSE NULL 
      END) AS pendientes,
      COUNT(DISTINCT CASE 
        WHEN co.estado IN ('vencido', 'cancelado') THEN c.id 
        ELSE NULL 
      END) AS suspendidos,
      SUM(s.precio_usd) AS facturacion_usd
    FROM clientes c
    INNER JOIN prm_servicios s ON s.id = c.servicio
    LEFT JOIN cnt_cobros co ON co.cliente_id = c.id 
      AND co.servicio_id = s.id 
      AND co.tipo = 'servicio'
      AND co.activo = 1
    WHERE s.estado = 'activo' 
      AND c.estado = 1
  ");
  
  $r_servicios = $q_servicios->fetch_assoc() ?: [
    'total_clientes' => 0, 'activos' => 0, 'pendientes' => 0, 
    'suspendidos' => 0, 'facturacion_usd' => 0
  ];

  // Estimación aproximada en ARS (cotización fija para el dashboard)
  // En producción deberías usar tc_get() con fecha actual
  $tc_aproximado = 1240.00; // Actualizar según necesidad
  
  $servicios = [
    'total_clientes'         => (int)($r_servicios['total_clientes'] ?? 0),
    'activos'                => (int)($r_servicios['activos'] ?? 0),
    'pendientes'             => (int)($r_servicios['pendientes'] ?? 0),
    'suspendidos'            => (int)($r_servicios['suspendidos'] ?? 0),
    'facturacion_usd'        => (float)($r_servicios['facturacion_usd'] ?? 0),
    'facturacion_ars_aprox'  => round(($r_servicios['facturacion_usd'] ?? 0) * $tc_aproximado, 2),
    'cotizacion_usada'       => $tc_aproximado,
    'moneda'                 => 'USD'
  ];

  // =====================================================
  // TRABAJOS PENDIENTES DE COBRO
  // =====================================================
  
  $q_trabajos = $db->query("
    SELECT 
      COUNT(*) AS total,
      SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
      SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
      SUM(CASE WHEN estado = 'homologacion' THEN 1 ELSE 0 END) AS homologacion,
      SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
      SUM(CASE WHEN estado = 'entregado' AND saldo > 0 THEN 1 ELSE 0 END) AS entregados_pendientes,
      SUM(saldo) AS saldo_total,
      SUM(total) AS monto_total
    FROM prm_trabajos
    WHERE saldo > 0 
      AND estado NOT IN ('cancelado')
  ");
  
  $r_trabajos = $q_trabajos->fetch_assoc() ?: [
    'total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 
    'finalizados' => 0, 'entregados_pendientes' => 0,
    'saldo_total' => 0, 'monto_total' => 0
  ];

  $trabajos = [
    'total'                  => (int)($r_trabajos['total'] ?? 0),
    'pendientes'             => (int)($r_trabajos['pendientes'] ?? 0),
    'en_proceso'             => (int)($r_trabajos['en_proceso'] ?? 0) + (int)($r_trabajos['homologacion'] ?? 0),
    'finalizados'            => (int)($r_trabajos['finalizados'] ?? 0),
    'entregados_pendientes'  => (int)($r_trabajos['entregados_pendientes'] ?? 0),
    'saldo_total'            => (float)($r_trabajos['saldo_total'] ?? 0),
    'monto_total'            => (float)($r_trabajos['monto_total'] ?? 0),
    'moneda'                 => 'ARS'
  ];

  // =====================================================
  // EVOLUCIÓN MENSUAL (Últimos 6 meses)
  // =====================================================
  
  $q_evolucion = $db->query("
    SELECT 
      DATE_FORMAT(fecha_emision, '%Y-%m') AS mes,
      SUM(total) AS total
    FROM cnt_cobros
    WHERE activo = 1
      AND fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_emision, '%Y-%m')
    ORDER BY mes ASC
  ");

  $evolucion = [];
  while ($row = $q_evolucion->fetch_assoc()) {
    $evolucion[] = [
      'mes'   => $row['mes'],
      'total' => (float)$row['total']
    ];
  }

  // =====================================================
  // RESPUESTA
  // =====================================================
  
  $response = [
    'cobros'     => $cobros,
    'servicios'  => $servicios,
    'trabajos'   => $trabajos,
    'evolucion'  => $evolucion,
    'timestamp'  => date('Y-m-d H:i:s')
  ];

  http_response_code(200);
  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  error_log('[contable/summary] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}
