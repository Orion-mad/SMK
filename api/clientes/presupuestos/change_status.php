<?php
// /api/clientes/presupuestos/change_status.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $id = (int)($input['id'] ?? 0);
  $nuevoEstado = trim($input['estado'] ?? '');
  $comentario = trim($input['comentario'] ?? '');
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $estadosPermitidos = ['borrador', 'enviado', 'aprobado', 'rechazado', 'vencido', 'cancelado'];
  if (!in_array($nuevoEstado, $estadosPermitidos)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Estado inválido']);
    exit;
  }

  $db = DB::get();

  // Obtener presupuesto actual
  $stmt = $db->prepare("SELECT estado FROM cli_presupuestos WHERE id = ? LIMIT 1");
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

  $estadoAnterior = $presupuesto['estado'];

  // Validaciones de transición de estado
  if ($estadoAnterior === $nuevoEstado) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El presupuesto ya tiene ese estado']);
    exit;
  }

  // No permitir cambiar estado desde aprobado (solo a cancelado)
  if ($estadoAnterior === 'aprobado' && $nuevoEstado !== 'cancelado') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Un presupuesto aprobado solo puede cancelarse']);
    exit;
  }

  $db->begin_transaction();

  try {
    // Actualizar estado
    $stmt = $db->prepare("UPDATE cli_presupuestos SET estado = ? WHERE id = ?");
    $stmt->bind_param('si', $nuevoEstado, $id);
    $stmt->execute();
    $stmt->close();

    // Actualizar campos especiales según el estado
    if ($nuevoEstado === 'enviado') {
      $stmt = $db->prepare("UPDATE cli_presupuestos SET enviado_en = NOW() WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
    }

    if ($nuevoEstado === 'aprobado') {
      // TODO: obtener usuario actual de sesión
      $stmt = $db->prepare("UPDATE cli_presupuestos SET aprobado_en = NOW() WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
    }

    // Registrar en historial
    $stmt = $db->prepare("
      INSERT INTO cli_presupuestos_historial (presupuesto_id, estado_anterior, estado_nuevo, comentario)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isss', $id, $estadoAnterior, $nuevoEstado, $comentario);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    http_response_code(200);
    echo json_encode(['ok' => true, 'estado' => $nuevoEstado], JSON_UNESCAPED_UNICODE);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[presupuestos/change_status] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}