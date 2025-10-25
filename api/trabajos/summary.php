<?php
// /api/trabajos/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $db = DB::get();

  // ========== ESTADÍSTICAS GENERALES ==========
  $query = $db->query("
    SELECT 
      COUNT(*) as total,
      SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendiente,
      SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
      SUM(CASE WHEN estado = 'homologacion' THEN 1 ELSE 0 END) as homologacion,
      SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizado,
      SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregado,
      SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelado,
      
      SUM(CASE WHEN prioridad = 'baja' THEN 1 ELSE 0 END) as prioridad_baja,
      SUM(CASE WHEN prioridad = 'normal' THEN 1 ELSE 0 END) as prioridad_normal,
      SUM(CASE WHEN prioridad = 'alta' THEN 1 ELSE 0 END) as prioridad_alta,
      SUM(CASE WHEN prioridad = 'urgente' THEN 1 ELSE 0 END) as prioridad_urgente,
      
      SUM(CASE WHEN estado NOT IN ('entregado', 'cancelado') AND fecha_entrega_estimada IS NOT NULL 
           AND fecha_entrega_estimada <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as por_entregar,
      
      SUM(CASE WHEN saldo > 0 THEN 1 ELSE 0 END) as con_saldo,
      
      SUM(CASE WHEN estado NOT IN ('entregado', 'cancelado') AND fecha_entrega_estimada < CURDATE() THEN 1 ELSE 0 END) as atrasados,
      
      SUM(total) as total_facturado,
      SUM(saldo) as saldo_pendiente
    FROM prm_trabajos
  ");

  $stats = $query->fetch_assoc();

  // ========== TRABAJOS POR ENTREGAR ESTE MES ==========
  $queryMes = $db->query("
    SELECT COUNT(*) as entregas_mes
    FROM prm_trabajos
    WHERE fecha_entrega_estimada >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND fecha_entrega_estimada < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
      AND estado NOT IN ('cancelado')
  ");
  $entregas = $queryMes->fetch_assoc();

  // ========== ALERTAS: TRABAJOS PRÓXIMOS A ENTREGAR ==========
  $queryAlertas = $db->query("
    SELECT 
      t.id,
      t.codigo,
      t.nombre,
      t.fecha_entrega_estimada,
      c.contacto_nombre as cliente,
      DATEDIFF(t.fecha_entrega_estimada, CURDATE()) as dias
    FROM prm_trabajos t
    INNER JOIN clientes c ON c.id = t.cliente_id
    WHERE t.estado NOT IN ('entregado', 'cancelado')
      AND t.fecha_entrega_estimada IS NOT NULL
      AND t.fecha_entrega_estimada <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)
    ORDER BY t.fecha_entrega_estimada ASC
    LIMIT 5
  ");

  $alertas = [];
  while ($row = $queryAlertas->fetch_assoc()) {
    $alertas[] = [
      'id' => (int)$row['id'],
      'codigo' => $row['codigo'],
      'nombre' => $row['nombre'],
      'cliente' => $row['cliente'],
      'fecha_entrega' => $row['fecha_entrega_estimada'],
      'dias' => (int)$row['dias']
    ];
  }

  // ========== CONSTRUIR RESPUESTA ==========
  $response = [
    'total' => (int)$stats['total'],
    'en_proceso' => (int)$stats['en_proceso'],
    'urgentes' => (int)$stats['prioridad_urgente'],
    'entregados' => (int)$stats['entregado'],
    'por_entregar' => (int)$stats['por_entregar'],
    'con_saldo' => (int)$stats['con_saldo'],
    'atrasados' => (int)$stats['atrasados'],
    'total_facturado' => (float)$stats['total_facturado'],
    'saldo_pendiente' => (float)$stats['saldo_pendiente'],
    'entregas_mes' => (int)$entregas['entregas_mes'],
    
    'por_estado' => [
      'pendiente' => (int)$stats['pendiente'],
      'en_proceso' => (int)$stats['en_proceso'],
      'homologacion' => (int)$stats['homologacion'],
      'finalizado' => (int)$stats['finalizado'],
      'entregado' => (int)$stats['entregado'],
      'cancelado' => (int)$stats['cancelado']
    ],
    
    'por_prioridad' => [
      'prioridad_baja' => (int)$stats['prioridad_baja'],
      'prioridad_normal' => (int)$stats['prioridad_normal'],
      'prioridad_alta' => (int)$stats['prioridad_alta'],
      'prioridad_urgente' => (int)$stats['prioridad_urgente']
    ],
    
    'alertas' => [
      'trabajos' => $alertas
    ]
  ];

  http_response_code(200);
  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/summary] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error',
    'detail' => $e->getMessage(),
    'total' => 0,
    'en_proceso' => 0,
    'urgentes' => 0,
    'entregados' => 0
  ]);
}