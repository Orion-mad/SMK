<?php
// /api/trabajos/from_presupuesto.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';
require_once __DIR__ . '/../core/codigo_helper.php';
try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $presupuestoId = (int)($input['presupuesto_id'] ?? 0);
  
  if ($presupuestoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'presupuesto_id requerido']);
    exit;
  }

  $db = DB::get();

  // Obtener datos del presupuesto
  $stmt = $db->prepare("
    SELECT 
      p.*,
      c.contacto_nombre AS cliente_nombre
    FROM cli_presupuestos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    WHERE p.id = ?
    LIMIT 1
  ");
  
  $stmt->bind_param('i', $presupuestoId);
  $stmt->execute();
  $presupuesto = $stmt->get_result()->fetch_assoc();
  
  if (!$presupuesto) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Presupuesto no encontrado']);
    exit;
  }

  // Validar que el presupuesto esté aprobado
  if ($presupuesto['estado'] !== 'aprobado') {
    http_response_code(400);
    echo json_encode([
      'ok' => false, 
      'error' => 'Solo se pueden pasar a producción presupuestos aprobados',
      'estado_actual' => $presupuesto['estado']
    ]);
    exit;
  }

  // Verificar si ya existe un trabajo para este presupuesto
  $stmt_check = $db->prepare("SELECT id FROM prm_trabajos WHERE presupuesto_id = ? LIMIT 1");
  $stmt_check->bind_param('i', $presupuestoId);
  $stmt_check->execute();
  $trabajoExistente = $stmt_check->get_result()->fetch_assoc();
  
  if ($trabajoExistente) {
    http_response_code(409);
    echo json_encode([
      'ok' => false, 
      'error' => 'Ya existe un trabajo para este presupuesto',
      'trabajo_id' => $trabajoExistente['id']
    ]);
    exit;
  }

  // Generar código para el trabajo
  $codigo = lcars_codigo_next($db, 'prm_trabajos','codigo', 'TRB');

  // Calcular fecha de entrega estimada (por defecto 30 días desde hoy)
  $diasEstimados = (int)($input['dias_estimados'] ?? 30);
  $fechaEntregaEstimada = date('Y-m-d', strtotime("+{$diasEstimados} days"));

  // Crear el trabajo
  $db->begin_transaction();

  try {
    $stmt_insert = $db->prepare("
      INSERT INTO prm_trabajos (
        codigo, nombre, descripcion,
        cliente_id, presupuesto_id, servicio_id,
        fecha_ingreso,
        estado, prioridad,
        total, moneda, saldo,
        observaciones
      ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'pendiente', ?, ?, ?, ?, ?)
    ");

    $nombre = "Trabajo desde Presupuesto {$presupuesto['codigo']}";
    $descripcion = $presupuesto['introduccion'] ?? $presupuesto['condiciones'];
    $clienteId = (int)$presupuesto['cliente_id'];
    $presupuestoId = (int)$presupuesto['id'];
    $servicioId =  null;
    $prioridad = $input['prioridad'] ?? 'normal';
    $total = (float)$presupuesto['total'];
    $moneda = $presupuesto['moneda'] ?? 'ARS';
    $observaciones = "Generado automáticamente desde presupuesto {$presupuesto['codigo']}";

    $stmt_insert->bind_param(
      'sssiiissdss',
      $codigo, $nombre, $descripcion,
      $clienteId, $presupuestoId, $servicioId, $prioridad,
      $total, $moneda, $total, $observaciones
    );

    if (!$stmt_insert->execute()) {
      throw new Exception('Error al crear el trabajo: ' . $stmt_insert->error);
    }

    $trabajoId = $db->insert_id;

//echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $trabajoId ]);exit;
    // Actualizar el presupuesto para marcarlo como "en producción"
    $stmt_update = $db->prepare("
      UPDATE cli_presupuestos 
      SET estado = 'en_produccion',
          actualizado_en = CURRENT_TIMESTAMP
      WHERE id = ?
    ");
    $stmt_update->bind_param('i', $presupuestoId);
    $stmt_update->execute();

    $db->commit();

    http_response_code(201);
    echo json_encode([
      'ok' => true,
      'trabajo_id' => $trabajoId,
      'codigo' => $codigo,
      'message' => 'Trabajo creado exitosamente desde presupuesto'
    ], JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[trabajos/from_presupuesto] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}