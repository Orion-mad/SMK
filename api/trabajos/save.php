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
   // $input['saldo'] = $input['total'] ?? 0;
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
        'syn' => ['pendiente','en_proceso','homologacion','finalizado','entregado','cancelado']
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
      'medio_pago' => [
        'col' => 'medio_pago',
        'type' => 'str',
        'nullable' => true,
        'max' => 50
      ],
      'saldo' => [
        'col' => 'saldo',
        'type' => 'float',
        'default' => 0.0
      ],
      'requiere_homologacion' => [
        'col' => 'requiere_homologacion',
        'type' => 'int',
        'default' => 0
      ],
      'homologacion_url' => [
        'col' => 'homologacion_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
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
        'max' => 255
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
      ],
      'observaciones' => [
        'col' => 'observaciones',
        'type' => 'str',
        'nullable' => true
      ],
      'archivos_path' => [
        'col' => 'archivos_path',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'orden' => [
        'col' => 'orden',
        'type' => 'int',
        'default' => 0
      ]
    ]
  ];

  // Verificar que el cliente existe
  $clienteId = (int)($input['cliente_id'] ?? 0);
  if ($clienteId > 0) {
    $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El cliente seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  // Si viene desde un presupuesto, validar
  if (isset($input['presupuesto_id']) && $input['presupuesto_id'] > 0) {
    $stmt = $db->prepare("SELECT id, total, cliente_id FROM cli_presupuestos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $input['presupuesto_id']);
    $stmt->execute();
    $presup = $stmt->get_result()->fetch_assoc();
    
    if (!$presup) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El presupuesto no existe']);
      exit;
    }
    
    // Validar que el cliente del trabajo coincida con el del presupuesto
    if ($presup['cliente_id'] != $clienteId) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El cliente no coincide con el del presupuesto']);
      exit;
    }
  }

  $result = lcars_save($cfg, $input);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}