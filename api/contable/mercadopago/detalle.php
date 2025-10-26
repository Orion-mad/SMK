<?php
// /api/contable/mercadopago/detalle.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $id = $_GET['id'] ?? '';
  
  if (empty($id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID de transacción requerido']);
    exit;
  }

  $db = DB::get();
  
  // Obtener configuración de MP
  $sql = "SELECT * FROM prm_mercadopago ORDER BY id DESC LIMIT 1";
  $result = $db->query($sql);
  
  if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No hay configuración de Mercado Pago']);
    exit;
  }

  $config = $result->fetch_assoc();
  
  // Determinar credenciales
  $modoActivo = $config['modo_activo'] ?? 'test';
  $accessToken = ($modoActivo === 'produccion') 
    ? $config['prod_access_token'] 
    : $config['test_access_token'];

  if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "No hay access token configurado"]);
    exit;
  }

  // Si el ID contiene '-fee' o '-refund', extraer el ID real
  $realId = preg_replace('/-fee$|-refund$/', '', $id);

  // Consultar detalle del pago
  $apiUrl = 'https://api.mercadopago.com/v1/payments/' . $realId;

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 15
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    throw new Exception('Error de conexión: ' . $curlError);
  }

  if ($httpCode !== 200) {
    throw new Exception('Error al obtener detalle (HTTP ' . $httpCode . ')');
  }

  $payment = json_decode($response, true);

  if (!$payment) {
    throw new Exception('Respuesta inválida de Mercado Pago');
  }

  // Construir objeto de transacción
  $transaction = [
    'id' => $payment['id'],
    'date_created' => $payment['date_created'] ?? null,
    'date_approved' => $payment['date_approved'] ?? null,
    'date_last_updated' => $payment['date_last_updated'] ?? null,
    'description' => $payment['description'] ?? '',
    'statement_descriptor' => $payment['statement_descriptor'] ?? '',
    'status' => $payment['status'] ?? '',
    'status_detail' => $payment['status_detail'] ?? '',
    'type' => 'payment',
    'operation_type' => $payment['operation_type'] ?? null,
    'total_amount' => (float)($payment['transaction_amount'] ?? 0),
    'net_amount' => (float)($payment['transaction_details']['net_received_amount'] ?? 0),
    'currency_id' => $payment['currency_id'] ?? 'ARS',
    'external_reference' => $payment['external_reference'] ?? null,
    
    // Pagador
    'payer_email' => $payment['payer']['email'] ?? null,
    'payer_name' => ($payment['payer']['first_name'] ?? '') . ' ' . ($payment['payer']['last_name'] ?? ''),
    'payer_identification' => $payment['payer']['identification'] ?? null,
    
    // Método de pago
    'payment_method' => [
      'id' => $payment['payment_method_id'] ?? null,
      'type' => $payment['payment_type_id'] ?? null,
      'issuer_id' => $payment['issuer_id'] ?? null
    ],
    
    // Detalles de transacción
    'transaction_details' => [
      'net_received_amount' => (float)($payment['transaction_details']['net_received_amount'] ?? 0),
      'total_paid_amount' => (float)($payment['transaction_details']['total_paid_amount'] ?? 0),
      'overpaid_amount' => (float)($payment['transaction_details']['overpaid_amount'] ?? 0),
      'installment_amount' => (float)($payment['transaction_details']['installment_amount'] ?? 0)
    ],
    
    // Comisiones
    'fee_details' => $payment['fee_details'] ?? [],
    
    // Reembolsos
    'refunds' => $payment['refunds'] ?? []
  ];

  http_response_code(200);
  echo json_encode([
    'ok' => true,
    'transaction' => $transaction,
    'modo' => $modoActivo
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[mercadopago/detalle] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}