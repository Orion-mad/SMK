<?php
// /api/trabajos/select.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $db = DB::get();

  $sql = "SELECT id, codigo, nombre, cliente_id, estado 
          FROM prm_trabajos 
          WHERE estado IN ('en_proceso', 'finalizado', 'entregado','homologacion','pendiente')
          ORDER BY fecha_ingreso DESC, codigo DESC
          LIMIT 100";

  $result = $db->query($sql);
  $data = [];

  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'id' => (int)$row['id'],
      'codigo' => $row['codigo'],
      'nombre' => $row['nombre'],
      'cliente_id' => $row['cliente_id'] ? (int)$row['cliente_id'] : null,
      'estado' => $row['estado']
    ];
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/select] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}