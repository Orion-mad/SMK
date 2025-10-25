<?php
// /api/clientes/presupuestos/delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Verificar que el presupuesto existe
  $stmt = $db->prepare("SELECT id, estado FROM cli_presupuestos WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $presupuesto = $result->fetch_assoc();
  $stmt->close();

  if (!$presupuesto) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Presupuesto no encontrado']);
    exit;
  }

  // No permitir eliminar presupuestos aprobados (solo cancelar)
  if ($presupuesto['estado'] === 'aprobado') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se puede eliminar un presupuesto aprobado. Cancélelo primero.']);
    exit;
  }

  $db->begin_transaction();

  try {
    // El CASCADE en la FK ya eliminará los items e historial
    $stmt = $db->prepare("DELETE FROM cli_presupuestos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
      throw new Exception('No se pudo eliminar el presupuesto');
    }

    $db->commit();

    http_response_code(204); // No Content
    exit;

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[presupuestos/delete] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}