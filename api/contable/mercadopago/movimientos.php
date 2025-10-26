<?php
// /api/contable/mercadopago/movimientos.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Parámetros
  $tipo = $_GET['tipo'] ?? 'ingresos'; // ingresos o egresos
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
  $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
  $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d'); // Hoy
  $search = $_GET['q'] ?? '';

  // Obtener configuración de MP
  $sql = "SELECT * FROM prm_mercadopago ORDER BY id DESC LIMIT 1";
  $result = $db->query($sql);
  
  if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
      'ok' => false,
      'error' => 'No hay configuración de Mercado Pago'
    ]);
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
    echo json_encode([
      'ok' => false,
      'error' => "No hay access token configurado para el modo {$modoActivo}"
    ]);
    exit;
  }

  // Construir URL de búsqueda de pagos
  $offset = ($page - 1) * $limit;
  
  $apiUrl = 'https://api.mercadopago.com/v1/payments/search?'
    . 'begin_date=' . urlencode($fechaDesde . 'T00:00:00.000-00:00')
    . '&end_date=' . urlencode($fechaHasta . 'T23:59:59.999-00:00')
    . '&offset=' . $offset
    . '&limit=' . $limit
    . '&sort=date_created'
    . '&criteria=desc';

  // Filtrar por tipo
  if ($tipo === 'ingresos') {
    $apiUrl .= '&status=approved'; // Solo pagos aprobados como ingresos
  }

  // Búsqueda adicional
  if (!empty($search)) {
    $apiUrl .= '&external_reference=' . urlencode($search);
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    throw new Exception('Error de conexión: ' . $curlError);
  }

  if ($httpCode !== 200) {
    throw new Exception('Error al obtener movimientos (HTTP ' . $httpCode . ')');
  }

  $data = json_decode($response, true);

  if (!$data) {
    throw new Exception('Respuesta inválida de Mercado Pago');
  }

  $results = $data['results'] ?? [];
  $paging = $data['paging'] ?? [];
  $total = $paging['total'] ?? 0;
  $totalPages = ceil($total / $limit);

  // Procesar resultados según tipo
  $items = [];
  $totalMonto = 0;

  foreach ($results as $payment) {
    $status = $payment['status'] ?? '';
    $transactionAmount = (float)($payment['transaction_amount'] ?? 0);
    $netAmount = (float)($payment['transaction_details']['net_received_amount'] ?? $transactionAmount);
    
    // Filtrar según tipo
    if ($tipo === 'ingresos' && $status === 'approved' && $transactionAmount > 0) {
      $items[] = [
        'id' => $payment['id'],
        'date_created' => $payment['date_created'] ?? null,
        'description' => $payment['description'] ?? '',
        'statement_descriptor' => $payment['statement_descriptor'] ?? '',
        'type' => 'payment',
        'status' => $status,
        'total_amount' => $transactionAmount,
        'net_amount' => $netAmount,
        'currency_id' => $payment['currency_id'] ?? 'ARS',
        'payer_email' => $payment['payer']['email'] ?? null,
        'payment_method_id' => $payment['payment_method_id'] ?? null,
        'operation_type' => $payment['operation_type'] ?? null
      ];
      $totalMonto += $netAmount;
      
    } elseif ($tipo === 'egresos') {
      // Para egresos, buscar reembolsos y comisiones
      $fees = (float)($payment['fee_details'][0]['amount'] ?? 0);
      
      if ($fees > 0) {
        $items[] = [
          'id' => $payment['id'] . '-fee',
          'date_created' => $payment['date_created'] ?? null,
          'description' => 'Comisión MP - ' . ($payment['description'] ?? ''),
          'statement_descriptor' => 'Comisión',
          'type' => 'fee',
          'status' => $status,
          'total_amount' => -$fees,
          'net_amount' => -$fees,
          'currency_id' => $payment['currency_id'] ?? 'ARS',
          'payer_email' => null,
          'payment_method_id' => $payment['payment_method_id'] ?? null,
          'operation_type' => 'fee'
        ];
        $totalMonto += $fees;
      }

      // Reembolsos
      if (isset($payment['refunds']) && !empty($payment['refunds'])) {
        foreach ($payment['refunds'] as $refund) {
          $refundAmount = (float)($refund['amount'] ?? 0);
          if ($refundAmount > 0) {
            $items[] = [
              'id' => $refund['id'],
              'date_created' => $refund['date_created'] ?? null,
              'description' => 'Reembolso - ' . ($payment['description'] ?? ''),
              'statement_descriptor' => 'Reembolso',
              'type' => 'refund',
              'status' => $refund['status'] ?? 'approved',
              'total_amount' => -$refundAmount,
              'net_amount' => -$refundAmount,
              'currency_id' => $payment['currency_id'] ?? 'ARS',
              'payer_email' => $payment['payer']['email'] ?? null,
              'payment_method_id' => null,
              'operation_type' => 'refund'
            ];
            $totalMonto += $refundAmount;
          }
        }
      }
    }
  }

  http_response_code(200);
  echo json_encode([
    'ok' => true,
    'items' => $items,
    'summary' => [
      'total' => $totalMonto,
      'count' => count($items),
      'currency' => 'ARS'
    ],
    'pagination' => [
      'page' => $page,
      'limit' => $limit,
      'total' => $total,
      'total_pages' => $totalPages
    ],
    'tipo' => $tipo,
    'modo' => $modoActivo
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[mercadopago/movimientos] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}