<?php
// /api/contable/servicios/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Verificar que las tablas existen
  $checkClientes = $db->query("SHOW TABLES LIKE 'clientes'");
  $checkServicios = $db->query("SHOW TABLES LIKE 'prm_servicios'");
  
  if ($checkClientes->num_rows === 0 || $checkServicios->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'Tablas necesarias no existen']);
    exit;
  }

  // Query para obtener clientes con servicios activos
  $sql = "SELECT 
            c.id AS cliente_id,
            c.codigo AS cliente_codigo,
            c.razon_social AS cliente_nombre,
            CONCAT(c.tipo_doc, ' ', c.nro_doc) AS cliente_doc,
            c.estado AS cliente_estado,
            s.id AS servicio_id,
            s.codigo AS servicio_codigo,
            s.nombre AS servicio_nombre,
            s.precio_usd,
            s.tipo_cobro,
            s.estado AS servicio_estado,
            (SELECT co.id 
             FROM cnt_cobros co 
             WHERE co.cliente_id = c.id 
               AND co.servicio_id = s.id 
             ORDER BY co.fecha_emision DESC 
             LIMIT 1) AS ultimo_cobro_id,
            (SELECT co.fecha_emision 
             FROM cnt_cobros co 
             WHERE co.cliente_id = c.id 
               AND co.servicio_id = s.id 
             ORDER BY co.fecha_emision DESC 
             LIMIT 1) AS ultimo_cobro_fecha,
            (SELECT co.estado 
             FROM cnt_cobros co 
             WHERE co.cliente_id = c.id 
               AND co.servicio_id = s.id 
             ORDER BY co.fecha_emision DESC 
             LIMIT 1) AS ultimo_cobro_estado
          FROM clientes c
          INNER JOIN prm_servicios s ON s.id = c.servicio
          WHERE s.estado = 'activo' 
            AND c.estado = 1";

  // Filtro de búsqueda
  $params = [];
  $types = '';
  
  if (isset($_GET['q']) && trim($_GET['q']) !== '') {
    $search = '%' . trim($_GET['q']) . '%';
    $sql .= " AND (c.razon_social LIKE ? OR c.nombre_fantasia LIKE ? OR s.nombre LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
  }

  $sql .= " ORDER BY c.razon_social ASC";

  $stmt = $db->prepare($sql);
  
  if ($types) {
    $stmt->bind_param($types, ...$params);
  }
  
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'cliente_id' => (int)$row['cliente_id'],
      'cliente_codigo' => $row['cliente_codigo'],
      'cliente_nombre' => $row['cliente_nombre'],
      'cliente_doc' => $row['cliente_doc'],
      'cliente_estado' => (int)$row['cliente_estado'],
      'servicio_id' => (int)$row['servicio_id'],
      'servicio_codigo' => $row['servicio_codigo'],
      'servicio_nombre' => $row['servicio_nombre'],
      'precio_usd' => (float)$row['precio_usd'],
      'tipo_cobro' => $row['tipo_cobro'],
      'servicio_estado' => $row['servicio_estado'],
      'ultimo_cobro_id' => $row['ultimo_cobro_id'] ? (int)$row['ultimo_cobro_id'] : null,
      'ultimo_cobro_fecha' => $row['ultimo_cobro_fecha'],
      'ultimo_cobro_estado' => $row['ultimo_cobro_estado']
    ];
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[contable/servicios/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}