<?php
// /api/clientes/clientes/save.php
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
    'table' => 'clientes',
    'pk' => 'id',
    'unique' => ['codigo', 'uk_doc' => ['tipo_doc', 'nro_doc']],
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
        'max' => 30
      ],
      'razon_social' => [
        'col' => 'razon_social',
        'type' => 'str',
        'required' => true,
        'max' => 160
      ],
      'nombre_fantasia' => [
        'col' => 'nombre_fantasia',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'tipo_doc' => [
        'col' => 'tipo_doc',
        'type' => 'set',
        'default' => 'CUIT',
        'syn' => ['CUIT', 'DNI', 'CUIL', 'PAS']
      ],
      'nro_doc' => [
        'col' => 'nro_doc',
        'type' => 'str',
        'required' => true,
        'max' => 20
      ],
      'iva_cond' => [
        'col' => 'iva_cond',
        'type' => 'set',
        'default' => 'CF',
        'syn' => ['RI', 'RNI', 'MT', 'EX', 'CF', 'NC']
      ],
      'iibb_cond' => [
        'col' => 'iibb_cond',
        'type' => 'set',
        'nullable' => true,
        'syn' => ['Local', 'CM', 'Exento', 'No inscripto']
      ],
      'iibb_nro' => [
        'col' => 'iibb_nro',
        'type' => 'str',
        'nullable' => true,
        'max' => 30
      ],
      'inicio_act' => [
        'col' => 'inicio_act',
        'type' => 'str',
        'nullable' => true
      ],
      'email' => [
        'col' => 'email',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'telefono' => [
        'col' => 'telefono',
        'type' => 'str',
        'nullable' => true,
        'max' => 40
      ],
      'celular' => [
        'col' => 'celular',
        'type' => 'str',
        'nullable' => true,
        'max' => 40
      ],
      'web' => [
        'col' => 'web',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'contacto_nombre' => [
        'col' => 'contacto_nombre',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'contacto_email' => [
        'col' => 'contacto_email',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'contacto_tel' => [
        'col' => 'contacto_tel',
        'type' => 'str',
        'nullable' => true,
        'max' => 40
      ],
      'direccion' => [
        'col' => 'direccion',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'direccion2' => [
        'col' => 'direccion2',
        'type' => 'str',
        'nullable' => true,
        'max' => 160
      ],
      'localidad' => [
        'col' => 'localidad',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'provincia' => [
        'col' => 'provincia',
        'type' => 'str',
        'nullable' => true,
        'max' => 120
      ],
      'pais' => [
        'col' => 'pais',
        'type' => 'str',
        'default' => 'Argentina',
        'max' => 120
      ],
      'cp' => [
        'col' => 'cp',
        'type' => 'str',
        'nullable' => true,
        'max' => 10
      ],
      'moneda_preferida' => [
        'col' => 'moneda_preferida',
        'type' => 'set',
        'default' => 'ARG',
        'syn' => ['ARG', 'DOL', 'EUR']
      ],
      'servicio' => [
        'col' => 'servicio',
        'type' => 'int',
        'nullable' => true
      ],
      'condicion_venta' => [
        'col' => 'condicion_venta',
        'type' => 'set',
        'default' => 'CONTADO',
        'syn' => ['CONTADO', 'CTA_CTE', 'MIXTA']
      ],
      'plazo_pago_dias' => [
        'col' => 'plazo_pago_dias',
        'type' => 'int',
        'default' => 0
      ],
      'tope_credito' => [
        'col' => 'tope_credito',
        'type' => 'float',
        'nullable' => true
      ],
      'obs' => [
        'col' => 'obs',
        'type' => 'str',
        'nullable' => true
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'int',
        'default' => 1
      ]
    ]
  ];

  // Verificar que el servicio existe si se proporciona
  $servicioId = isset($input['servicio']) ? (int)$input['servicio'] : null;
  if ($servicioId > 0) {
    $stmt = $db->prepare("SELECT id FROM prm_servicios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $servicioId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      $stmt->close();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'El servicio seleccionado no existe']);
      exit;
    }
    $stmt->close();
  }

//echo json_encode(['ok' => false, 'cfg' => $cfg, 'input' => $input]);die;
  $result = lcars_save($cfg, $input);

  // Debug: si falla, agregar más info
  if (!$result['ok']) {
    error_log('[clientes/clientes/save] lcars_save failed: ' . json_encode($result));
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/clientes/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}