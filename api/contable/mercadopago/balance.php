<?php
// /api/contable/mercadopago/balance.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();

  // Obtener configuración de MP
  $config = $db->query("SELECT * FROM prm_mercadopago ORDER BY id DESC LIMIT 1")->fetch_assoc();
  
  if (!$config) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay configuración de Mercado Pago']);
    exit;
  }

  $modo = $config['modo_activo'];
  $accessToken = $modo === 'produccion' ? $config['prod_access_token'] : $config['test_access_token'];

  if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay access token configurado']);
    exit;
  }

  // Consultar saldo a la API de Mercado Pago
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadopago.com/v1/users/me',
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
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception('Error consultando API de Mercado Pago');
  }

  $userData = json_decode($response, true);

  // Obtener balance
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadopago.com/v1/users/' . $userData['id'] . '/mercadopago_account/balance',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10
  ]);

  $balanceResponse = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception('Error consultando balance de Mercado Pago');
  }

  $balanceData = json_decode($balanceResponse, true);

  // Calcular totales desde la BD local
  $totalesQuery = $db->query("
    SELECT 
      SUM(CASE WHEN tipo = 'ingreso' THEN monto_neto ELSE 0 END) as total_ingresos,
      SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END) as total_gastos
    FROM mp_movimientos
    WHERE estado = 'approved'
  ");

  $totales = $totalesQuery->fetch_assoc();

  $result = [
    'saldo_disponible' => (float)($balanceData['available_balance'] ?? 0),
    'saldo_bloqueado' => (float)($balanceData['unavailable_balance'] ?? 0),
    'total_ingresos' => (float)($totales['total_ingresos'] ?? 0),
    'total_gastos' => (float)($totales['total_gastos'] ?? 0)
  ];

  http_response_code(200);
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[mercadopago/balance] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}