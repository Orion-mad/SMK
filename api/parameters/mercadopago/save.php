<?php
// /api/parameters/mercadopago/save.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/crud_helper.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $db = DB::get();

  // Verificar si existe la tabla, si no crearla
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_mercadopago'");
  if ($checkTable->num_rows === 0) {
    // Crear tabla
    $createTable = "CREATE TABLE `prm_mercadopago` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `prod_public_key` VARCHAR(255) NULL,
      `prod_access_token` VARCHAR(255) NULL,
      `prod_webhook_url` VARCHAR(500) NULL,
      `prod_success_url` VARCHAR(500) NULL,
      `prod_pending_url` VARCHAR(500) NULL,
      `prod_failure_url` VARCHAR(500) NULL,
      `prod_activo` TINYINT(1) DEFAULT 0,
      `test_public_key` VARCHAR(255) NULL,
      `test_access_token` VARCHAR(255) NULL,
      `test_activo` TINYINT(1) DEFAULT 1,
      `modo_activo` ENUM('test', 'produccion') DEFAULT 'test',
      `expiracion_minutos` INT DEFAULT 30,
      `max_intentos` INT DEFAULT 2,
      `statement_descriptor` VARCHAR(22) NULL,
      `email_notificaciones` VARCHAR(255) NULL,
      `auto_return` TINYINT(1) DEFAULT 0,
      `binary_mode` TINYINT(1) DEFAULT 0,
      `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$db->query($createTable)) {
      throw new Exception('Error al crear la tabla prm_mercadopago: ' . $db->error);
    }
  }

  // Verificar si ya existe un registro (solo debería haber uno)
  $checkExisting = $db->query("SELECT id FROM prm_mercadopago LIMIT 1");
  if ($checkExisting && $checkExisting->num_rows > 0) {
    $row = $checkExisting->fetch_assoc();
    $input['id'] = $row['id'];
  } else {
    $input['id'] = 0; // Nuevo registro
  }

  // Configuración para lcars_crud
  $cfg = [
    'table' => 'prm_mercadopago',
    'pk' => 'id',
    'fields' => [
      'id' => [
        'col' => 'id',
        'type' => 'int',
        'default' => 0
      ],
      'prod_public_key' => [
        'col' => 'prod_public_key',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'prod_access_token' => [
        'col' => 'prod_access_token',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'prod_webhook_url' => [
        'col' => 'prod_webhook_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'prod_success_url' => [
        'col' => 'prod_success_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'prod_pending_url' => [
        'col' => 'prod_pending_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'prod_failure_url' => [
        'col' => 'prod_failure_url',
        'type' => 'str',
        'nullable' => true,
        'max' => 500
      ],
      'prod_activo' => [
        'col' => 'prod_activo',
        'type' => 'int',
        'default' => 0
      ],
      'test_public_key' => [
        'col' => 'test_public_key',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'test_access_token' => [
        'col' => 'test_access_token',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'test_activo' => [
        'col' => 'test_activo',
        'type' => 'int',
        'default' => 1
      ],
      'modo_activo' => [
        'col' => 'modo_activo',
        'type' => 'set',
        'default' => 'test',
        'syn' => ['test', 'produccion']
      ],
      'expiracion_minutos' => [
        'col' => 'expiracion_minutos',
        'type' => 'int',
        'default' => 30
      ],
      'max_intentos' => [
        'col' => 'max_intentos',
        'type' => 'int',
        'default' => 2
      ],
      'statement_descriptor' => [
        'col' => 'statement_descriptor',
        'type' => 'str',
        'nullable' => true,
        'max' => 22
      ],
      'email_notificaciones' => [
        'col' => 'email_notificaciones',
        'type' => 'str',
        'nullable' => true,
        'max' => 255
      ],
      'auto_return' => [
        'col' => 'auto_return',
        'type' => 'int',
        'default' => 0
      ],
      'binary_mode' => [
        'col' => 'binary_mode',
        'type' => 'int',
        'default' => 0
      ]
    ]
  ];

  // Guardar usando lcars_save
  $result = lcars_save($cfg, $input);

  if (!$result['ok']) {
    http_response_code(400);
    echo json_encode($result);
    exit;
  }

  http_response_code(200);
  echo json_encode([
    'ok' => true,
    'id' => $result['id'],
    'message' => 'Configuración de Mercado Pago guardada correctamente'
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[mercadopago/save] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}