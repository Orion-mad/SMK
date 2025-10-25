<?php
// /api/clientes/dominios/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  // Verificar si existe la tabla dominios
  $checkTable = $db->query("SHOW TABLES LIKE 'dominios'");
  if ($checkTable->num_rows === 0) {
    // Si no existe la tabla, devolver datos vacíos
    http_response_code(200);
    echo json_encode([
      'total' => 0,
      'activos' => 0,
      'inactivos' => 0,
      'con_ssl' => 0,
      'por_vencer' => 0,
      'vencidos' => 0,
      'proximos_vencer' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Resumen general de dominios
  // estado ENUM: 'activo','suspendido','vencido','transferencia','cancelado'
  $q1 = $db->query("
    SELECT 
      COUNT(*) AS total,
      COUNT(CASE WHEN estado = 'activo' THEN 1 END) AS activos,
      COUNT(CASE WHEN estado != 'activo' THEN 1 END) AS inactivos,
      COUNT(CASE WHEN ssl_activo = 1 THEN 1 END) AS con_ssl
    FROM dominios
  ");
  
  $r1 = $q1->fetch_assoc() ?: ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'con_ssl' => 0];

  $total = (int)($r1['total'] ?? 0);
  $activos = (int)($r1['activos'] ?? 0);
  $inactivos = (int)($r1['inactivos'] ?? 0);
  $con_ssl = (int)($r1['con_ssl'] ?? 0);

  // Dominios por vencer (próximos 30 días)
  $q2 = $db->query("
    SELECT COUNT(*) AS por_vencer
    FROM dominios
    WHERE estado = 'activo'
      AND fecha_vencimiento IS NOT NULL
      AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  ");
  
  $r2 = $q2->fetch_assoc();
  $por_vencer = (int)($r2['por_vencer'] ?? 0);

  // Dominios vencidos
  $q3 = $db->query("
    SELECT COUNT(*) AS vencidos
    FROM dominios
    WHERE estado = 'vencido'
      OR (estado = 'activo' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE())
  ");
  
  $r3 = $q3->fetch_assoc();
  $vencidos = (int)($r3['vencidos'] ?? 0);

  // Próximos a vencer (para alertas)
  $q4 = $db->query("
    SELECT 
      d.id,
      d.dominio,
      d.fecha_vencimiento,
      CASE 
        WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
        ELSE c.contacto_nombre
      END AS cliente_nombre,
      DATEDIFF(d.fecha_vencimiento, CURDATE()) AS dias_restantes
    FROM dominios d
    LEFT JOIN clientes c ON c.id = d.cliente_id
    WHERE d.estado = 'activo'
      AND d.fecha_vencimiento IS NOT NULL
      AND d.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY d.fecha_vencimiento ASC
    LIMIT 5
  ");

  $proximos_vencer = [];
  while ($row = $q4->fetch_assoc()) {
    $proximos_vencer[] = [
      'id' => (int)$row['id'],
      'dominio' => $row['dominio'],
      'cliente_nombre' => $row['cliente_nombre'] ?? 'Sin cliente',
      'fecha_vencimiento' => $row['fecha_vencimiento'],
      'dias_restantes' => (int)$row['dias_restantes']
    ];
  }

  $out = [
    'total' => $total,
    'activos' => $activos,
    'inactivos' => $inactivos,
    'con_ssl' => $con_ssl,
    'por_vencer' => $por_vencer,
    'vencidos' => $vencidos,
    'proximos_vencer' => $proximos_vencer
  ];

  http_response_code(200);
  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[dominios/summary] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}