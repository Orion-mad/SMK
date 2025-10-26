<?php
// /api/parameters/mercadopago/test.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $modo = $input['modo'] ?? 'test';
  $accessToken = $input['access_token'] ?? '';

  if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Access token requerido']);
    exit;
  }

  // Endpoint de Mercado Pago para verificar credenciales
  $apiUrl = 'https://api.mercadopago.com/users/me';

  // Inicializar cURL
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'Error de conexión: ' . $curlError
    ]);
    exit;
  }

  $data = json_decode($response, true);

  if ($httpCode === 200 && isset($data['id'])) {
    // Conexión exitosa
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'user_id' => $data['id'],
      'email' => $data['email'] ?? 'N/A',
      'nickname' => $data['nickname'] ?? 'N/A',
      'site_id' => $data['site_id'] ?? 'N/A',
      'message' => 'Conexión exitosa con Mercado Pago'
    ], JSON_UNESCAPED_UNICODE);
  } else {
    // Error en la autenticación
    http_response_code(401);
    echo json_encode([
      'ok' => false,
      'error' => 'Credenciales inválidas o expiradas',
      'detail' => $data['message'] ?? 'Error desconocido',
      'http_code' => $httpCode
    ]);
  }

} catch (Throwable $e) {
  error_log('[mercadopago/test] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}