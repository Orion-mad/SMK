<?php
// /api/clientes/presupuestos/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  // Verificar si existe la tabla cli_presupuestos
  $checkTable = $db->query("SHOW TABLES LIKE 'cli_presupuestos'");
  if ($checkTable->num_rows === 0) {
    // Si no existe la tabla, devolver datos vacíos
    http_response_code(200);
    echo json_encode([
      'total' => 0,
      'borrador' => 0,
      'enviado' => 0,
      'aprobado' => 0,
      'rechazado' => 0,
      'vencido' => 0,
      'cancelado' => 0,
      'por_vencer' => 0,
      'monto_total_arg' => 0,
      'monto_total_dol' => 0,
      'monto_total_eur' => 0,
      'proximos_vencer' => [],
      'ultimos' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Resumen general de presupuestos
  $q1 = $db->query("
    SELECT 
      COUNT(*) AS total,
      SUM(estado = 'borrador') AS borrador,
      SUM(estado = 'enviado') AS enviado,
      SUM(estado = 'aprobado') AS aprobado,
      SUM(estado = 'rechazado') AS rechazado,
      SUM(estado = 'vencido') AS vencido,
      SUM(estado = 'cancelado') AS cancelado
    FROM cli_presupuestos
    WHERE activo = 1
  ");
  
  $r1 = $q1->fetch_assoc() ?: [
    'total' => 0, 'borrador' => 0, 'enviado' => 0, 
    'aprobado' => 0, 'rechazado' => 0, 'vencido' => 0, 'cancelado' => 0
  ];

  $total = (int)($r1['total'] ?? 0);
  $borrador = (int)($r1['borrador'] ?? 0);
  $enviado = (int)($r1['enviado'] ?? 0);
  $aprobado = (int)($r1['aprobado'] ?? 0);
  $rechazado = (int)($r1['rechazado'] ?? 0);
  $vencido = (int)($r1['vencido'] ?? 0);
  $cancelado = (int)($r1['cancelado'] ?? 0);

  // Monto total por moneda (solo aprobados)
  $q2 = $db->query("
    SELECT 
      moneda,
      SUM(total) AS monto_total
    FROM cli_presupuestos
    WHERE estado = 'aprobado' AND activo = 1
    GROUP BY moneda
  ");

  $montos = [];
  while ($row = $q2->fetch_assoc()) {
    $montos[$row['moneda']] = (float)$row['monto_total'];
  }

  $monto_total_arg = $montos['ARG'] ?? 0;
  $monto_total_dol = $montos['DOL'] ?? 0;
  $monto_total_eur = $montos['EUR'] ?? 0;

  // Presupuestos por vencer (próximos 15 días, estado enviado)
  $q3 = $db->query("
    SELECT 
      p.id,
      p.codigo,
      p.fecha_vencimiento,
      p.total,
      p.moneda,
      CASE 
        WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
        ELSE c.contacto_nombre
      END AS cliente_nombre,
      DATEDIFF(p.fecha_vencimiento, CURDATE()) AS dias_restantes
    FROM cli_presupuestos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    WHERE p.estado = 'enviado' 
      AND p.activo = 1
      AND p.fecha_vencimiento IS NOT NULL
      AND p.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
    ORDER BY p.fecha_vencimiento ASC
    LIMIT 5
  ");

  $proximos_vencer = [];
  while ($row = $q3->fetch_assoc()) {
    $proximos_vencer[] = [
      'id' => (int)$row['id'],
      'codigo' => $row['codigo'],
      'cliente_nombre' => $row['cliente_nombre'],
      'fecha_vencimiento' => $row['fecha_vencimiento'],
      'total' => (float)$row['total'],
      'moneda' => $row['moneda'],
      'dias_restantes' => (int)$row['dias_restantes']
    ];
  }

  $por_vencer = count($proximos_vencer);

  // Últimos presupuestos creados
  $q4 = $db->query("
    SELECT 
      p.id,
      p.codigo,
      p.estado,
      p.total,
      p.moneda,
      CASE 
        WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
        ELSE c.contacto_nombre
      END AS cliente_nombre,
      p.creado_en
    FROM cli_presupuestos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    WHERE p.activo = 1
    ORDER BY p.creado_en DESC
    LIMIT 5
  ");

  $ultimos = [];
  while ($row = $q4->fetch_assoc()) {
    $ultimos[] = [
      'id' => (int)$row['id'],
      'codigo' => $row['codigo'],
      'cliente_nombre' => $row['cliente_nombre'],
      'estado' => $row['estado'],
      'total' => (float)$row['total'],
      'moneda' => $row['moneda'],
      'creado_en' => $row['creado_en']
    ];
  }

  $out = [
    'total' => $total,
    'borrador' => $borrador,
    'enviado' => $enviado,
    'aprobado' => $aprobado,
    'rechazado' => $rechazado,
    'vencido' => $vencido,
    'cancelado' => $cancelado,
    'por_vencer' => $por_vencer,
    'monto_total_arg' => $monto_total_arg,
    'monto_total_dol' => $monto_total_dol,
    'monto_total_eur' => $monto_total_eur,
    'proximos_vencer' => $proximos_vencer,
    'ultimos' => $ultimos
  ];

  http_response_code(200);
  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[presupuestos/summary] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}