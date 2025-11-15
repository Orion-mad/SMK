<?php
// /api/trabajos/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';
require_once __DIR__ . '/../core/crud_helper.php';
require_once __DIR__ . '/../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Autogenerar código si no viene (solo en alta)
  lcars_autocodigo($db, 'prm_trabajos', $input, 'codigo', 'id', 'TRB');
  
  // Calcular saldo inicial = total
  if (!isset($input['id']) || $input['id'] === 0) {
    $input['saldo'] = $input['total'] ?? 0;
  }

  $cfg = [
    'table' => 'prm_trabajos',
    'pk' => 'id',
    'unique' => ['codigo'],
    'fields' => [
      'id' => [
        'col' => 'id',
        'type' => 'int',
        'default' => 0
      ],
      'codigo' => [
        'col' => 'codigo',
        'type' => 'str',
        'required' => true,
        'max' => 32
      ],
      'nombre' => [
        'col' => 'nombre',
        'type' => 'str',
        'required' => true,
        'max' => 200
      ],
      'descripcion' => [
        'col' => 'descripcion',
        'type' => 'str',
        'nullable' => true
      ],
      'cliente_id' => [
        'col' => 'cliente_id',
        'type' => 'int',
        'required' => true
      ],
      'presupuesto_id' => [
        'col' => 'presupuesto_id',
        'type' => 'int',
        'nullable' => true
      ],
      'servicio_id' => [
        'col' => 'servicio_id',
        'type' => 'int',
        'nullable' => true
      ],
      'fecha_ingreso' => [
        'col' => 'fecha_ingreso',
        'type' => 'str',
        'required' => true
      ],
      'fecha_entrega_estimada' => [
        'col' => 'fecha_entrega_estimada',
        'type' => 'str',
        'nullable' => true
      ],
      'fecha_entrega_real' => [
        'col' => 'fecha_entrega_real',
        'type' => 'str',
        'nullable' => true
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'set',
        'default' => 'pendiente',
        'syn' => ['pendiente','en_proceso','finalizado','entregado','cancelado']
      ],
      'prioridad' => [
        'col' => 'prioridad',
        'type' => 'set',
        'default' => 'normal',
        'syn' => ['baja','normal','alta','urgente']
      ],
      'total' => [
        'col' => 'total',
        'type' => 'float',
        'default' => 0.0
      ],
      'moneda' => [
        'col' => 'moneda',
        'type' => 'str',
        'default' => 'ARS',
        'max' => 3
      ],
      'saldo' => [
        'col' => 'saldo',
        'type' => 'float',
        'default' => 0.0
      ],
      'medio_pago' => [
        'col' => 'medio_pago',
        'type' => 'str',
        'default' => 'Efectivo'
      ],
      'observaciones' => [
        'col' => 'observaciones',
        'type' => 'str',
        'nullable' => true
      ],
      // Campos de homologación
      'requiere_homologacion' => [
        'col' => 'requiere_homologacion',
        'type' => 'int',
        'default' => 0
      ],
      'homologacion_url' => [
        'col' => 'homologacion_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'homologacion_usuario' => [
        'col' => 'homologacion_usuario',
        'type' => 'str',
        'nullable' => true,
        'max' => 100
      ],
      'homologacion_password' => [
        'col' => 'homologacion_password',
        'type' => 'str',
        'nullable' => true,
        'max' => 100
      ],
      'homologacion_notas' => [
        'col' => 'homologacion_notas',
        'type' => 'str',
        'nullable' => true
      ],
      'homologacion_estado' => [
        'col' => 'homologacion_estado',
        'type' => 'set',
        'nullable' => true,
        'syn' => ['pendiente','en_proceso','aprobado','rechazado']
      ]
    ]
  ];

  // Guardar trabajo
  $result = lcars_save($cfg, $input);
  
  if (!$result['ok']) {
    echo json_encode($result);
    exit;
  }

  $trabajoId = (int)$result['id'];
  $esNuevo = $result['created'] ?? false;

  // =====================================================
  // CREAR COBRO AUTOMÁTICAMENTE
  // =====================================================
  // Solo si es nuevo O si es editado pero no tiene cobro
  
  $debeCrearCobro = false;
  
  if ($esNuevo) {
    $debeCrearCobro = true;
  } else {
    // Verificar si ya existe un cobro para este trabajo
    $stmt = $db->prepare("
      SELECT id FROM cnt_cobros 
      WHERE trabajo_id = ? AND tipo = 'trabajo' 
      LIMIT 1
    ");
    $stmt->bind_param('i', $trabajoId);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
      $debeCrearCobro = true;
    }
    $stmt->close();
  }
    
  // anulado por que ya exite en contable/cobros un funcion par apasar a cobros
  $debeCrearCobro = false;

  if ($debeCrearCobro) {
    // Obtener datos del trabajo recién guardado
    $stmt = $db->prepare("
      SELECT t.*, c.razon_social, c.contacto_nombre
      FROM prm_trabajos t
      LEFT JOIN clientes c ON c.id = t.cliente_id
      WHERE t.id = ?
      LIMIT 1
    ");
    $stmt->bind_param('i', $trabajoId);
    $stmt->execute();
    $trabajo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($trabajo) {
      // Generar código para el cobro
      $anio = date('Y');
      $qCodigo = $db->query("
        SELECT MAX(CAST(SUBSTRING(codigo, 10) AS UNSIGNED)) as ultimo
        FROM cnt_cobros 
        WHERE codigo LIKE 'COB-{$anio}-%'
      ");
      $rCodigo = $qCodigo->fetch_assoc();
      $ultimo = (int)($rCodigo['ultimo'] ?? 0);
      $siguiente = $ultimo + 1;
      $codigoCobro = sprintf('COB-%s-%05d', $anio, $siguiente);

      // Concepto del cobro
      $concepto = "Cobro trabajo {$trabajo['codigo']}";
      if ($trabajo['nombre']) {
        $concepto .= " - {$trabajo['nombre']}";
      }
      if (strlen($concepto) > 200) {
        $concepto = substr($concepto, 0, 197) . '...';
      }

      // Datos del cobro
      $subtotal = (float)$trabajo['total'];
      $total = $subtotal;
      $moneda = $trabajo['moneda'] ?? 'ARS';
      $fechaEmision = $trabajo['fecha_ingreso'] ?? date('Y-m-d');
      $clienteNombre = $trabajo['razon_social'] ?? $trabajo['contacto_nombre'] ?? 'Cliente';

      // Insertar en cnt_cobros
      $stmtCobro = $db->prepare("
        INSERT INTO cnt_cobros (
          codigo, cliente_id, tipo, concepto, trabajo_id,
          subtotal, descuento, impuestos, total, moneda,
          fecha_emision, estado, monto_pagado, saldo,
          observaciones, activo, orden
        ) VALUES (?, ?, 'trabajo', ?, ?, ?, 0, 0, ?, ?, ?, 'pendiente', 0, ?, ?, 1, 0)
      ");

      $observaciones = "Cobro generado automáticamente desde trabajo {$trabajo['codigo']}";

      $stmtCobro->bind_param(
        'sisiddsds',
        $codigoCobro,
        $trabajo['cliente_id'],
        $concepto,
        $trabajoId,
        $subtotal,
        $total,
        $moneda,
        $fechaEmision,
        $total,
        $observaciones
      );

      if ($stmtCobro->execute()) {
        $cobroId = (int)$db->insert_id;
        $stmtCobro->close();

        // Insertar item en cnt_cobros_items
        $itemDesc = $trabajo['nombre'] ?? "Trabajo {$trabajo['codigo']}";
        if (strlen($itemDesc) > 200) {
          $itemDesc = substr($itemDesc, 0, 197) . '...';
        }

        $stmtItem = $db->prepare("
          INSERT INTO cnt_cobros_items (
            cobro_id, descripcion, cantidad, precio_unitario,
            subtotal, alicuota_iva, monto_iva, orden
          ) VALUES (?, ?, 1, ?, ?, 0, 0, 1)
        ");

        $stmtItem->bind_param(
          'isdd',
          $cobroId,
          $itemDesc,
          $subtotal,
          $subtotal
        );

        $stmtItem->execute();
        $stmtItem->close();

        // Agregar info del cobro creado a la respuesta
        $result['cobro_creado'] = true;
        $result['cobro_id'] = $cobroId;
        $result['cobro_codigo'] = $codigoCobro;
      } else {
        error_log('[trabajos/save] Error creando cobro: ' . $stmtCobro->error);
        $stmtCobro->close();
      }
    }
  }

  http_response_code($result['created'] ? 201 : 200);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/save] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}