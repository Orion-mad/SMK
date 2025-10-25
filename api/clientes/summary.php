<?php
// /api/clientes/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $db = DB::get();

  // Resumen general de clientes
  // estado = 1 (activo), estado = 0 (inactivo)
  $q1 = $db->query("
    SELECT 
      COUNT(*) AS total,
      COUNT(CASE WHEN estado = 1 THEN 1 END) AS activos,
      COUNT(CASE WHEN estado = 0 THEN 1 END) AS inactivos
    FROM clientes
  ");
  
  if (!$q1) {
    throw new Exception("Error en query clientes: " . $db->error);
  }
  
  $r1 = $q1->fetch_assoc();
  if (!$r1) {
    throw new Exception("No se pudieron obtener datos de clientes");
  }

  $total = (int)($r1['total'] ?? 0);
  $activos = (int)($r1['activos'] ?? 0);
  $inactivos = (int)($r1['inactivos'] ?? 0);

  // Clientes registrados en los últimos 30 días
  $q2 = $db->query("
    SELECT COUNT(*) AS recientes
    FROM clientes
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  ");
  
  $r2 = $q2 ? $q2->fetch_assoc() : null;
  $recientes = (int)($r2['recientes'] ?? 0);

  // Clientes con servicios asignados
  $q3 = $db->query("
    SELECT COUNT(*) AS con_servicios
    FROM clientes
    WHERE servicio IS NOT NULL AND servicio != ''
  ");
  
  $r3 = $q3 ? $q3->fetch_assoc() : null;
  $con_servicios = (int)($r3['con_servicios'] ?? 0);

  // Emails únicos
  $q4 = $db->query("
    SELECT COUNT(DISTINCT email) AS emails_unicos
    FROM clientes
    WHERE email IS NOT NULL AND email != ''
  ");
  
  $r4 = $q4 ? $q4->fetch_assoc() : null;
  $emails_unicos = (int)($r4['emails_unicos'] ?? 0);

  // Empresas (clientes con razón social)
  $q5 = $db->query("
    SELECT COUNT(*) AS empresas
    FROM clientes
    WHERE razon_social IS NOT NULL AND razon_social != ''
  ");
  
  $r5 = $q5 ? $q5->fetch_assoc() : null;
  $empresas = (int)($r5['empresas'] ?? 0);

  $out = [
    'total' => $total,
    'activos' => $activos,
    'inactivos' => $inactivos,
    'recientes' => $recientes,
    'con_servicios' => $con_servicios,
    'emails_unicos' => $emails_unicos,
    'empresas' => $empresas
  ];

  http_response_code(200);
  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/summary] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
  ]);
}