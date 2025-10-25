<?php
// /api/contable/cobros/migrar-trabajos.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

/**
 * SCRIPT DE MIGRACIÓN
 * Migra pagos de prm_trabajos_pagos a cnt_cobros
 * 
 * Estrategia:
 * - Agrupa pagos por trabajo_id
 * - Crea un cobro por cada grupo
 * - Vincula items con trabajo_pago_id
 */

try {
  $db = DB::get();
  
  // Parámetros
  $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
  
  $resultado = [
    'dry_run' => $dryRun,
    'procesados' => 0,
    'errores' => 0,
    'cobros_creados' => 0,
    'items_creados' => 0,
    'detalles' => []
  ];

  // Obtener pagos que NO están vinculados a ningún cobro
  $sql = "
    SELECT 
      tp.*,
      t.codigo AS trabajo_codigo,
      t.nombre AS trabajo_nombre,
      t.cliente_id,
      t.total AS trabajo_total,
      c.razon_social AS cliente_nombre,
      c.contacto_nombre AS cliente_apellido
    FROM prm_trabajos_pagos tp
    INNER JOIN prm_trabajos t ON t.id = tp.trabajo_id
    LEFT JOIN clientes c ON c.id = t.cliente_id
    WHERE tp.estado IN ('pendiente', 'confirmado')
      AND NOT EXISTS (
        SELECT 1 FROM cnt_cobros_items ci 
        WHERE ci.trabajo_pago_id = tp.id
      )
    ORDER BY tp.fecha_pago ASC, tp.id ASC
  ";
  
  if ($limit > 0) {
    $sql .= " LIMIT $limit";
  }

  $result = $db->query($sql);
  
  if (!$result || $result->num_rows === 0) {
    echo json_encode([
      'ok' => true,
      'message' => 'No hay pagos pendientes de migrar',
      'resultado' => $resultado
    ]);
    exit;
  }

  // Procesar cada pago individualmente
  while ($row = $result->fetch_assoc()) {
    try {
      $resultado['procesados']++;
      
      if (!$dryRun) {
        $db->begin_transaction();
      }
      
      $pago = [
        'id' => (int)$row['id'],
        'trabajo_id' => (int)$row['trabajo_id'],
        'trabajo_codigo' => $row['trabajo_codigo'],
        'trabajo_nombre' => $row['trabajo_nombre'],
        'cliente_id' => $row['cliente_id'] ? (int)$row['cliente_id'] : null,
        'cliente_nombre' => trim(($row['cliente_nombre'] ?? '') . ' ' . ($row['cliente_apellido'] ?? '')),
        'fecha_pago' => $row['fecha_pago'],
        'monto' => (float)$row['monto'],
        'moneda' => $row['moneda'],
        'medio_pago' => $row['medio_pago'],
        'referencia' => $row['referencia'],
        'comprobante_tipo' => $row['comprobante_tipo'],
        'comprobante_numero' => $row['comprobante_numero'],
        'observaciones' => $row['observaciones']
      ];
      
      // Generar código para el cobro
      $codigoData = ['codigo' => ''];
      if (!$dryRun) {
        lcars_autocodigo($db, 'cnt_cobros', $codigoData, 'codigo', 'id');
      } else {
        $codigoData['codigo'] = 'DRY-' . str_pad((string)$pago['id'], 8, '0', STR_PAD_LEFT);
      }
      
      // Concepto del cobro
      $concepto = "Pago trabajo {$pago['trabajo_codigo']}";
      if ($pago['trabajo_nombre']) {
        $concepto .= " - {$pago['trabajo_nombre']}";
      }
      if ($pago['referencia']) {
        $concepto .= " (Ref: {$pago['referencia']})";
      }
      if (strlen($concepto) > 200) {
        $concepto = substr($concepto, 0, 197) . '...';
      }
      
      $subtotal = $pago['monto'];
      
      $cobroData = [
        'codigo' => $codigoData['codigo'],
        'numero_factura' => $pago['comprobante_numero'],
        'cliente_id' => $pago['cliente_id'],
        'tipo' => 'trabajo',
        'concepto' => $concepto,
        'trabajo_id' => $pago['trabajo_id'],
        'servicio_id' => null,
        'subtotal' => $subtotal,
        'descuento' => 0,
        'impuestos' => 0,
        'total' => $subtotal,
        'moneda' => $pago['moneda'],
        'fecha_emision' => $pago['fecha_pago'],
        'fecha_vencimiento' => null,
        'estado' => 'pagado', // Ya está pagado
        'monto_pagado' => $subtotal,
        'saldo' => 0,
        'observaciones' => $pago['observaciones'] ? 
          "Migrado desde prm_trabajos_pagos\n{$pago['observaciones']}" : 
          'Migrado desde prm_trabajos_pagos',
        'activo' => 1,
        'orden' => 0
      ];
      
      $cobroId = null;
      
      if (!$dryRun) {
        // Insertar cobro
        $campos = array_keys($cobroData);
        $valores = array_values($cobroData);
        $placeholders = array_fill(0, count($valores), '?');
        
        $sqlInsert = "INSERT INTO cnt_cobros (" . implode(', ', $campos) . ") 
                      VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $db->prepare($sqlInsert);
        $tipos = str_repeat('s', count($valores));
        $stmt->bind_param($tipos, ...$valores);
        $stmt->execute();
        
        $cobroId = (int)$db->insert_id;
      } else {
        $cobroId = 900000 + $resultado['procesados']; // Fake ID para dry run
      }
      
      // Crear UN SOLO item vinculado al pago
      $itemDesc = "Pago trabajo {$pago['trabajo_codigo']}";
      if ($pago['medio_pago']) {
        $itemDesc .= " - {$pago['medio_pago']}";
      }
      if ($pago['referencia']) {
        $itemDesc .= " (Ref: {$pago['referencia']})";
      }
      
      if (!$dryRun) {
        $stmtItem = $db->prepare("
          INSERT INTO cnt_cobros_items 
          (cobro_id, descripcion, cantidad, precio_unitario, subtotal, alicuota_iva, monto_iva, trabajo_pago_id, orden)
          VALUES (?, ?, 1, ?, ?, 0, 0, ?, 1)
        ");
        
        $stmtItem->bind_param(
          'isddi',
          $cobroId,
          $itemDesc,
          $pago['monto'],
          $pago['monto'],
          $pago['id']
        );
        
        $stmtItem->execute();
        $resultado['items_creados']++;
      } else {
        $resultado['items_creados']++;
      }
      
      if (!$dryRun) {
        $db->commit();
        $resultado['cobros_creados']++;
      } else {
        $resultado['cobros_creados']++;
      }
      
      $resultado['detalles'][] = [
        'pago_id' => $pago['id'],
        'trabajo_id' => $pago['trabajo_id'],
        'trabajo_codigo' => $pago['trabajo_codigo'],
        'cobro_codigo' => $codigoData['codigo'],
        'cobro_id' => $cobroId,
        'monto' => $subtotal,
        'moneda' => $pago['moneda'],
        'medio_pago' => $pago['medio_pago'],
        'fecha_pago' => $pago['fecha_pago'],
        'status' => 'ok'
      ];
      
    } catch (Exception $e) {
      if (!$dryRun) {
        $db->rollback();
      }
      
      $resultado['errores']++;
      $resultado['detalles'][] = [
        'pago_id' => $row['id'],
        'trabajo_id' => $row['trabajo_id'],
        'trabajo_codigo' => $row['trabajo_codigo'],
        'status' => 'error',
        'error' => $e->getMessage()
      ];
    }
  }

  http_response_code(200);
  echo json_encode([
    'ok' => true,
    'message' => $dryRun 
      ? 'Simulación completada (no se guardó nada)' 
      : 'Migración completada exitosamente',
    'resultado' => $resultado
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  error_log('[migrar-trabajos] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}