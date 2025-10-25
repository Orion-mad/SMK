<?php
// /api/clientes/dominios/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Autogenerar código solo si es un INSERT (id=0) y código está vacío
  if (empty($input['id']) || $input['id'] == 0) {
    lcars_autocodigo($db, 'clientes', $input, 'codigo', 'id');
  }

  $cfg = [
    'table' => 'dominios',
    'pk' => 'id',
    'unique' => ['codigo', 'dominio'],
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
      'cliente_id' => [
        'col' => 'cliente_id',
        'type' => 'int',
        'required' => true
      ],
      'plan_id' => [
        'col' => 'plan_id',
        'type' => 'int',
        'nullable' => true
      ],
      'dominio' => [
        'col' => 'dominio',
        'type' => 'str',
        'required' => true,
        'max' => 255
      ],
      'tipo_dominio' => [
        'col' => 'tipo_dominio',
        'type' => 'set',
        'default' => 'principal',
        'in' => ['PRINCIPAL', 'ADICIONAL', 'SUBDOMINIO', 'REDIRECCION']
      ],
      'proveedor_hosting' => [
        'col' => 'proveedor_hosting',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'servidor' => [
        'col' => 'servidor',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'panel_control' => [
        'col' => 'panel_control',
        'type' => 'str',
        'nullable' => true,
        'max' => 60
      ],
      'url_panel' => [
        'col' => 'url_panel',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'usuario_hosting' => [
        'col' => 'usuario_hosting',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'password_hosting' => [
        'col' => 'password_hosting',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'registrador' => [
        'col' => 'registrador',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'fecha_registro' => [
        'col' => 'fecha_registro',
        'type' => 'str',
        'nullable' => true
      ],
      'fecha_vencimiento' => [
        'col' => 'fecha_vencimiento',
        'type' => 'str',
        'nullable' => true
      ],
      'ns1' => [
        'col' => 'ns1',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'ns2' => [
        'col' => 'ns2',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'ns3' => [
        'col' => 'ns3',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'ns4' => [
        'col' => 'ns4',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'ip_principal' => [
        'col' => 'ip_principal',
        'type' => 'str',
        'nullable' => true,
        'max' => 45
      ],
      'ssl_activo' => [
        'col' => 'ssl_activo',
        'type' => 'int',
        'default' => 0
      ],
      'ssl_tipo' => [
        'col' => 'ssl_tipo',
        'type' => 'str',
        'nullable' => true,
        'max' => 30
      ],
      'ssl_vencimiento' => [
        'col' => 'ssl_vencimiento',
        'type' => 'str',
        'nullable' => true
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'str',
        'default' => 'activo'
      ],
      'renovacion_auto' => [
        'col' => 'renovacion_auto',
        'type' => 'int',
        'default' => 0
      ],
      'orden' => [
        'col' => 'orden',
        'type' => 'int',
        'default' => 0
      ],
      'observaciones' => [
        'col' => 'observaciones',
        'type' => 'str',
        'nullable' => true
      ],
      'detalles' => [
        'col' => 'detalles',
        'type' => 'str',
        'nullable' => true
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

  // Verificar que el plan existe (si se especifica)
  $planId = isset($input['plan_id']) ? (int)$input['plan_id'] : null;
  if ($planId && $planId > 0) {
    $stmt = $db->prepare("SELECT id FROM prm_planes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El plan seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

  // Validar tipo_dominio
  $tiposValidos = ['principal', 'adicional', 'subdominio', 'redireccion'];
  if (isset($input['tipo_dominio']) && !in_array($input['tipo_dominio'], $tiposValidos)) {
    $input['tipo_dominio'] = 'principal';
  }

  // Validar estado
  $estadosValidos = ['activo', 'suspendido', 'vencido', 'transferencia', 'cancelado'];
  if (isset($input['estado']) && !in_array($input['estado'], $estadosValidos)) {
    $input['estado'] = 'activo';
  }

  $result = lcars_save($cfg, $input);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/dominios/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}