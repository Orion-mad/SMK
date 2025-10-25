<?php
// /api/parameters/medios_pago/save.php
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

  // Autogenerar código si no viene o está vacío (solo en alta)
  lcars_autocodigo($db, 'prm_medios_pago', $input, 'codigo', 'id');

  $cfg = [
    'table' => 'prm_medios_pago',
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
        'max' => 120
      ],
      'activo' => [
        'col' => 'activo',
        'type' => 'int',
        'default' => 1
      ],
      'notas' => [
        'col' => 'notas',
        'type' => 'str',
        'nullable' => true
      ],
      'orden' => [
        'col' => 'orden',
        'type' => 'int',
        'default' => 0
      ]
    ]
  ];

  $result = lcars_save($cfg, $input);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[medios_pago/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}