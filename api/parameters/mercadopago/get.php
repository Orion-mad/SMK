<?php
// /api/parameters/mercadopago/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Verificar si existe la tabla
  $checkTable = $db->query("SHOW TABLES LIKE 'prm_mercadopago'");
  if ($checkTable->num_rows === 0) {
    // Tabla no existe, retornar valores por defecto
    http_response_code(200);
    echo json_encode([
      'prod_public_key' => '',
      'prod_access_token' => '',
      'prod_webhook_url' => '',
      'prod_success_url' => '',
      'prod_pending_url' => '',
      'prod_failure_url' => '',
      'prod_activo' => 0,
      'test_public_key' => '',
      'test_access_token' => '',
      'test_activo' => 1,
      'modo_activo' => 'test',
      'expiracion_minutos' => 30,
      'max_intentos' => 2,
      'statement_descriptor' => '',
      'email_notificaciones' => '',
      'auto_return' => 0,
      'binary_mode' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Obtener configuración
  $sql = "SELECT * FROM prm_mercadopago ORDER BY id DESC LIMIT 1";
  $result = $db->query($sql);
  
  if (!$result || $result->num_rows === 0) {
    // No hay configuración, retornar valores por defecto
    http_response_code(200);
    echo json_encode([
      'prod_public_key' => '',
      'prod_access_token' => '',
      'prod_webhook_url' => '',
      'prod_success_url' => '',
      'prod_pending_url' => '',
      'prod_failure_url' => '',
      'prod_activo' => 0,
      'test_public_key' => '',
      'test_access_token' => '',
      'test_activo' => 1,
      'modo_activo' => 'test',
      'expiracion_minutos' => 30,
      'max_intentos' => 2,
      'statement_descriptor' => '',
      'email_notificaciones' => '',
      'auto_return' => 0,
      'binary_mode' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $data = $result->fetch_assoc();

  // Convertir tipos
  $data['id'] = (int)$data['id'];
  $data['prod_activo'] = (int)$data['prod_activo'];
  $data['test_activo'] = (int)$data['test_activo'];
  $data['expiracion_minutos'] = (int)$data['expiracion_minutos'];
  $data['max_intentos'] = (int)$data['max_intentos'];
  $data['auto_return'] = (int)$data['auto_return'];
  $data['binary_mode'] = (int)$data['binary_mode'];

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[mercadopago/get] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}