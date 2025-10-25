<?php
// /api/parameters/servicios/save.php
declare(strict_types=1);

// DEBUG ACTIVADO
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../_php_errors.log');

header('Content-Type: application/json; charset=utf-8');

try {
  echo json_encode(['debug' => 'Inicio del script', 'time' => date('Y-m-d H:i:s')]) . "\n";
  
  // Verificar archivos requeridos
  $inc = realpath(__DIR__ . '/../../../inc/conect.php');
  $crud = realpath(__DIR__ . '/../../core/crud_helper.php');
  $codigo = realpath(__DIR__ . '/../../core/codigo_helper.php');
  
  echo json_encode([
    'debug' => 'Verificando archivos',
    'inc' => ['path' => $inc, 'exists' => file_exists($inc)],
    'crud' => ['path' => $crud, 'exists' => file_exists($crud)],
    'codigo' => ['path' => $codigo, 'exists' => file_exists($codigo)]
  ]) . "\n";
  
  if (!$inc || !file_exists($inc)) {
    throw new Exception('No se encontró conect.php en: ' . __DIR__ . '/../../inc/conect.php');
  }
  
  if (!$crud || !file_exists($crud)) {
    throw new Exception('No se encontró crud_helper.php en: ' . __DIR__ . '/../core/crud_helper.php');
  }
  
  if (!$codigo || !file_exists($codigo)) {
    throw new Exception('No se encontró codigo_helper.php en: ' . __DIR__ . '/../core/codigo_helper.php');
  }
  
  require_once $inc;
  require_once $crud;
  require_once $codigo;
  
  echo json_encode(['debug' => 'Archivos incluidos correctamente']) . "\n";
  
  $input = json_decode(file_get_contents('php://input'), true);
  
  echo json_encode(['debug' => 'Input recibido', 'data' => $input]) . "\n";
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido o vacío']);
    exit;
  }

  $db = DB::get();
  
  echo json_encode(['debug' => 'Conexión DB obtenida']) . "\n";

  // Autogenerar código si no viene o está vacío (solo en alta)
  lcars_autocodigo($db, 'prm_servicios', $input, 'codigo', 'id','SRV');

  echo json_encode(['debug' => 'Código generado/verificado', 'codigo' => $input['codigo']]) . "\n";

  $cfg = [
    'table' => 'prm_servicios',
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
      'plan_id' => [
        'col' => 'plan_id',
        'type' => 'int',
        'required' => true
      ],
      'descripcion' => [
        'col' => 'descripcion',
        'type' => 'str',
        'nullable' => true
      ],
      'precio_usd' => [
        'col' => 'precio_usd',
        'type' => 'float',
        'default' => 0.0
      ],
      'tipo_cobro' => [
        'col' => 'tipo_cobro',
        'type' => 'set',
        'default' => 'mensual',
        'sin' => ['mensual', 'anual']
      ],
      'fecha_inicio' => [
        'col' => 'fecha_inicio',
        'type' => 'str',
        'nullable' => true
      ],
      'estado' => [
        'col' => 'estado',
        'type' => 'set',
        'default' => 'activo',
        'sin' => ['activo', 'suspendido', 'cancelado']
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
      ]
    ]
  ];

  // Verificar que el plan existe antes de guardar
  $planId = (int)($input['plan_id'] ?? 0);
  
  echo json_encode(['debug' => 'Verificando plan', 'plan_id' => $planId]) . "\n";
  
  if ($planId > 0) {
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
    
    echo json_encode(['debug' => 'Plan existe, continuando...']) . "\n";
  }

  echo json_encode(['debug' => 'Llamando a lcars_save...']) . "\n";
  
  $result = lcars_save($cfg, $input);
  
  echo json_encode(['debug' => 'lcars_save completado', 'result' => $result]) . "\n";
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[servicios/save] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'ok' => false, 
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'trace' => $e->getTraceAsString()
  ]);
}