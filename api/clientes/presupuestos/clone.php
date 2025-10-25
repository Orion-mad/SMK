<?php
// /api/clientes/presupuestos/clone.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $id = (int)($input['id'] ?? 0);
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
  }

  $db = DB::get();

  // Obtener presupuesto original
  $stmt = $db->prepare("SELECT * FROM cli_presupuestos WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $original = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$original) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Presupuesto no encontrado']);
    exit;
  }

  // Obtener items originales
  $stmt = $db->prepare("SELECT * FROM cli_presupuestos_items WHERE presupuesto_id = ? ORDER BY orden ASC");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $items = [];
  while ($row = $result->fetch_assoc()) {
    $items[] = $row;
  }
  $stmt->close();

  $db->begin_transaction();

  try {
    // Generar nuevo código
    $nuevoCodigo = lcars_codigo_next($db, 'cli_presupuestos', 'codigo');

    // Preparar datos para INSERT dinámico
    $titulo = $original['titulo'] . ' (Copia)';
    $fechaEmision = date('Y-m-d');
    $fechaVenc = null;
    $introduccion = !empty($original['introduccion']) ? $original['introduccion'] : null;
    $condiciones = !empty($original['condiciones']) ? $original['condiciones'] : null;
    $observaciones = !empty($original['observaciones']) ? $original['observaciones'] : null;
    $notas_internas = !empty($original['notas_internas']) ? $original['notas_internas'] : null;
    $forma_pago = !empty($original['forma_pago']) ? $original['forma_pago'] : null;

    // Construir INSERT dinámico
    $cols = ['codigo', 'cliente_id', 'fecha_emision', 'dias_validez', 'estado', 'titulo',
             'moneda', 'subtotal', 'descuento_porc', 'descuento_monto', 'iva_porc',
             'iva_monto', 'total', 'tipo_cobro', 'version', 'presupuesto_original_id', 'orden', 'activo'];
    $types = 'sissssssdddddsiiii';
    $vals = [$nuevoCodigo, $original['cliente_id'], $fechaEmision, $original['dias_validez'],
             'borrador', $titulo, $original['moneda'], $original['subtotal'],
             $original['descuento_porc'], $original['descuento_monto'], $original['iva_porc'],
             $original['iva_monto'], $original['total'], $original['tipo_cobro'],
             $original['version'], $id, $original['orden'], $original['activo']];

    // Agregar campos opcionales
    if ($fechaVenc !== null) {
      $cols[] = 'fecha_vencimiento';
      $types .= 's';
      $vals[] = $fechaVenc;
    }
    if ($introduccion !== null) {
      $cols[] = 'introduccion';
      $types .= 's';
      $vals[] = $introduccion;
    }
    if ($condiciones !== null) {
      $cols[] = 'condiciones';
      $types .= 's';
      $vals[] = $condiciones;
    }
    if ($observaciones !== null) {
      $cols[] = 'observaciones';
      $types .= 's';
      $vals[] = $observaciones;
    }
    if ($notas_internas !== null) {
      $cols[] = 'notas_internas';
      $types .= 's';
      $vals[] = $notas_internas;
    }
    if ($forma_pago !== null) {
      $cols[] = 'forma_pago';
      $types .= 's';
      $vals[] = $forma_pago;
    }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colsList = implode(',', $cols);

    $sql = "INSERT INTO cli_presupuestos ($colsList) VALUES ($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $nuevoId = $db->insert_id;
    $stmt->close();

    // Clonar items
    $stmtItem = $db->prepare("
      INSERT INTO cli_presupuestos_items (
        presupuesto_id, orden, tipo, descripcion, descripcion_corta,
        servicio_id, cantidad, unidad, precio_unitario, subtotal,
        descuento_porc, descuento_monto, subtotal_con_desc,
        iva_porc, iva_monto, total, activo
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
      $stmtItem->bind_param(
        'iisssidsddddddddi',
        $nuevoId,
        $item['orden'],
        $item['tipo'],
        $item['descripcion'],
        $item['descripcion_corta'],
        $item['servicio_id'],
        $item['cantidad'],
        $item['unidad'],
        $item['precio_unitario'],
        $item['subtotal'],
        $item['descuento_porc'],
        $item['descuento_monto'],
        $item['subtotal_con_desc'],
        $item['iva_porc'],
        $item['iva_monto'],
        $item['total'],
        $item['activo']
      );
      $stmtItem->execute();
    }
    $stmtItem->close();

    $db->commit();

    http_response_code(200);
    echo json_encode(['ok' => true, 'id' => $nuevoId, 'codigo' => $nuevoCodigo], JSON_UNESCAPED_UNICODE);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[presupuestos/clone] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}