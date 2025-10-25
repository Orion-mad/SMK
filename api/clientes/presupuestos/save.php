<?php
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

  $db = DB::get();
  
  $cliente_id = (int)($input['cliente_id'] ?? 0);
  if ($cliente_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'cliente_id requerido']);
    exit;
  }

  // Verificar que el cliente existe y está activo
  $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? AND estado = 1 LIMIT 1");
  $stmt->bind_param('i', $cliente_id);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El cliente no existe o está inactivo']);
    exit;
  }
  $stmt->close();

  // Autogenerar código solo si es INSERT y código está vacío
  $id = (int)($input['id'] ?? 0);
  if ($id === 0) {
    lcars_autocodigo($db, 'cli_presupuestos', $input, 'codigo', 'id');
  }

  $codigo = trim($input['codigo'] ?? '');
  $titulo = trim($input['titulo'] ?? '');
  $fecha_emision = $input['fecha_emision'] ?? date('Y-m-d');
  $fecha_vencimiento = !empty($input['fecha_vencimiento']) ? $input['fecha_vencimiento'] : null;
  $dias_validez = (int)($input['dias_validez'] ?? 30);
  $estado = $input['estado'] ?? 'borrador';
  
  $introduccion = !empty($input['introduccion']) ? $input['introduccion'] : null;
  $condiciones = !empty($input['condiciones']) ? $input['condiciones'] : null;
  $observaciones = !empty($input['observaciones']) ? $input['observaciones'] : null;
  $notas_internas = !empty($input['notas_internas']) ? $input['notas_internas'] : null;
  
  $moneda = $input['moneda'] ?? 'ARG';
  $subtotal = (float)($input['subtotal'] ?? 0);
  $descuento_porc = (float)($input['descuento_porc'] ?? 0);
  $descuento_monto = (float)($input['descuento_monto'] ?? 0);
  $iva_porc = (float)($input['iva_porc'] ?? 21);
  $iva_monto = (float)($input['iva_monto'] ?? 0);
  $total = (float)($input['total'] ?? 0);
  
  $tipo_cobro = $input['tipo_cobro'] ?? 'mensual';
  $forma_pago = !empty($input['forma_pago']) ? $input['forma_pago'] : null;
  $orden = (int)($input['orden'] ?? 0);
  $activo = isset($input['activo']) ? (int)$input['activo'] : 1;

  // Verificar código único
  if (lcars_codigo_existe($db, 'cli_presupuestos', $codigo, $id)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'El código ya existe']);
    exit;
  }

  $db->begin_transaction();

  try {
    if ($id > 0) {
      // UPDATE - usar SET dinámico para evitar problemas con NULL
      $updates = [
        'codigo = ?',
        'cliente_id = ?',
        'fecha_vencimiento = ?',
        'fecha_emision = ?',
        'dias_validez = ?',
        'estado = ?',
        'titulo = ?',
        'moneda = ?',
        'subtotal = ?',
        'descuento_porc = ?',
        'descuento_monto = ?',
        'iva_porc = ?',
        'iva_monto = ?',
        'total = ?',
        'tipo_cobro = ?',
        'orden = ?',
        'activo = ?'
      ];
      
      $types = 'sisssssssdddddsii';
      $vals = [
        $codigo, $cliente_id, $fecha_vencimiento, $fecha_emision, $dias_validez, $estado, $titulo,
        $moneda, $subtotal, $descuento_porc, $descuento_monto, $iva_porc,
        $iva_monto, $total, $tipo_cobro, $orden, $activo
      ];
      
      // Agregar campos opcionales
      if ($introduccion !== null) {
        $updates[] = 'introduccion = ?';
        $types .= 's';
        $vals[] = $introduccion;
      }
      if ($condiciones !== null) {
        $updates[] = 'condiciones = ?';
        $types .= 's';
        $vals[] = $condiciones;
      }
      if ($observaciones !== null) {
        $updates[] = 'observaciones = ?';
        $types .= 's';
        $vals[] = $observaciones;
      }
      if ($notas_internas !== null) {
        $updates[] = 'notas_internas = ?';
        $types .= 's';
        $vals[] = $notas_internas;
      }
      if ($forma_pago !== null) {
        $updates[] = 'forma_pago = ?';
        $types .= 's';
        $vals[] = $forma_pago;
      }
      
      // Agregar ID al final
      $types .= 'i';
      $vals[] = $id;
      $setClause = implode(', ', $updates);

        
      $sql = "UPDATE cli_presupuestos SET $setClause WHERE id = ?";
      
      $stmt = $db->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();
      $stmt->close();
//print_r($vals);echo $setClause;exit;
     
    } else {
      // INSERT dinámico (solo columnas con valor)
      $cols = ['codigo', 'cliente_id', 'fecha_emision', 'dias_validez', 'estado', 'titulo', 
               'moneda', 'subtotal', 'descuento_porc', 'descuento_monto', 'iva_porc', 
               'iva_monto', 'total', 'tipo_cobro', 'orden', 'activo'];
      $types = 'sissssssdddddsii';
      $vals = [$codigo, $cliente_id, $fecha_emision, $dias_validez, $estado, $titulo, 
               $moneda, $subtotal, $descuento_porc, $descuento_monto, $iva_porc, 
               $iva_monto, $total, $tipo_cobro, $orden, $activo];
      
      // Agregar campos opcionales solo si tienen valor
      if ($fecha_vencimiento !== null) {
        $cols[] = 'fecha_vencimiento';
        $types .= 's';
        $vals[] = $fecha_vencimiento;
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
      $id = $db->insert_id;
      $stmt->close();
    }

    // Procesar items
    if (isset($input['items']) && is_array($input['items'])) {
      // Eliminar items existentes
      $stmt = $db->prepare("DELETE FROM cli_presupuestos_items WHERE presupuesto_id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();

      // Insertar nuevos items
      if (count($input['items']) > 0) {
        $stmtItem = $db->prepare("
          INSERT INTO cli_presupuestos_items (
            presupuesto_id, orden, tipo, descripcion, descripcion_corta,
            servicio_id, cantidad, unidad, precio_unitario, subtotal,
            descuento_porc, descuento_monto, subtotal_con_desc,
            iva_porc, iva_monto, total, activo
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($input['items'] as $idx => $item) {
          $itemOrden = (int)($item['orden'] ?? $idx + 1);
          $itemTipo = $item['tipo'] ?? 'item';
          $itemDesc = $item['descripcion'] ?? '';
          $itemDescCorta = !empty($item['descripcion_corta']) ? $item['descripcion_corta'] : null;
          $itemServicioId = !empty($item['servicio_id']) ? (int)$item['servicio_id'] : null;
          $itemCantidad = (float)($item['cantidad'] ?? 1);
          $itemUnidad = $item['unidad'] ?? 'unidad';
          $itemPrecio = (float)($item['precio_unitario'] ?? 0);
          $itemSubtotal = (float)($item['subtotal'] ?? 0);
          $itemDescPorc = (float)($item['descuento_porc'] ?? 0);
          $itemDescMonto = (float)($item['descuento_monto'] ?? 0);
          $itemSubtotalDesc = (float)($item['subtotal_con_desc'] ?? 0);
          $itemIvaPorc = (float)($item['iva_porc'] ?? 21);
          $itemIvaMonto = (float)($item['iva_monto'] ?? 0);
          $itemTotal = (float)($item['total'] ?? 0);
          $itemActivo = isset($item['activo']) ? (int)$item['activo'] : 1;

          $stmtItem->bind_param(
            'iisssidsdddddddii',
            $id, $itemOrden, $itemTipo, $itemDesc, $itemDescCorta,
            $itemServicioId, $itemCantidad, $itemUnidad, $itemPrecio, $itemSubtotal,
            $itemDescPorc, $itemDescMonto, $itemSubtotalDesc,
            $itemIvaPorc, $itemIvaMonto, $itemTotal, $itemActivo
          );
          
          $stmtItem->execute();
        }
        
        $stmtItem->close();
      }
    }

    $db->commit();

    http_response_code(200);
    echo json_encode(['ok' => true, 'id' => $id, 'codigo' => $codigo], JSON_UNESCAPED_UNICODE);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[presupuestos/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}