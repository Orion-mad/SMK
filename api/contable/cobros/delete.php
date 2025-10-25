<?php
// /api/contable/cobros/delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Verificar si existe
  $stmt = $db->prepare("SELECT id, estado FROM cnt_cobros WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $cobro = $stmt->get_result()->fetch_assoc();

  if (!$cobro) {
    //http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Cobro no encontrado']);
    exit;
  }

  // Verificar si tiene pagos registrados
  $stmtPagos = $db->prepare("SELECT COUNT(*) as total FROM cnt_cobros_pagos WHERE cobro_id = ?");
  $stmtPagos->bind_param('i', $id);
  $stmtPagos->execute();
  $pagos = $stmtPagos->get_result()->fetch_assoc();

  if ($pagos['total'] > 0) {
    //http_response_code(400);
    echo json_encode([
      'ok' => false, 
      'error' => 'No se puede eliminar un cobro con pagos registrados. Primero elimine los pagos.'
    ]);
    exit;
  }

  // Eliminar (cascade eliminará items automáticamente)
  $stmtDel = $db->prepare("DELETE FROM cnt_cobros WHERE id = ?");
  $stmtDel->bind_param('i', $id);
  $stmtDel->execute();

  http_response_code(204);
  exit;

} catch (Throwable $e) {
  error_log('[cobros/delete] ' . $e->getMessage());
  //http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}