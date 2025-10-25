<?php
// /api/parameters/planes/select.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  $sql = "SELECT id, codigo, nombre, precio_mensual, precio_anual, moneda 
          FROM prm_planes 
          WHERE activo = 1 
          ORDER BY orden ASC, nombre ASC";

  $result = $db->query($sql);
  $data = [];

  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'id'             => (int)$row['id'],
      'codigo'         => $row['codigo'],
      'nombre'         => $row['nombre'],
      'precio_mensual' => (float)$row['precio_mensual'],
      'precio_anual'   => (float)$row['precio_anual'],
      'moneda'         => $row['moneda']
    ];
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[planes/select] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}