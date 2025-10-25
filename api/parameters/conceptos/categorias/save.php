<?php
// /api/parameters/conceptos/categorias/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';
require_once __DIR__ . '/../../../core/crud_helper.php';
require_once __DIR__ . '/../../../core/codigo_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Autogenerar código si no viene o está vacío (solo en alta)
  lcars_autocodigo($db, 'prm_conceptos_categorias', $input, 'codigo', 'id', 'CATG-');

  $cfg = [
    'table' => 'prm_conceptos_categorias',
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
        'max' => 100
      ],
      'descripcion' => [
        'col' => 'descripcion',
        'type' => 'str',
        'nullable' => true
      ],
      'tipo_flujo' => [
        'col' => 'tipo_flujo',
        'type' => 'set',
        'default' => 'ambos',
        'sin' => ['ingreso', 'egreso', 'ambos']
      ],
      'color' => [
        'col' => 'color',
        'type' => 'str',
        'default' => '#6c757d',
        'max' => 7
      ],
      'icono' => [
        'col' => 'icono',
        'type' => 'str',
        'default' => 'bi-tag',
        'max' => 50
      ],
      'activo' => [
        'col' => 'activo',
        'type' => 'int',
        'default' => 1
      ],
      'orden' => [
        'col' => 'orden',
        'type' => 'int',
        'default' => 0
      ]
    ]
  ];

  // Validar formato de color hex
  if (isset($input['color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $input['color'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Color debe ser formato hex (#RRGGBB)']);
    exit;
  }

  $result = lcars_save($cfg, $input);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/categorias/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}