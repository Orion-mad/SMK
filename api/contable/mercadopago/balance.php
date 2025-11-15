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
    echo json_encode(['ok' => false, 'error' => 'No hay configuración de Mercado Pago']);
    exit;
  }

  $modo = $config['modo_activo'];
  $accessToken = $modo === 'produccion' ? $config['prod_access_token'] : $config['test_access_token'];

  if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No hay access token configurado']);
    exit;
  }

  // PASO 1: Obtener información del usuario autenticado
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadopago.com/users/me',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10
  ]);

  $userResponse = curl_exec($ch);
  $httpCodeUser = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($httpCodeUser !== 200) {
    throw new Exception('Error consultando usuario de Mercado Pago. Código HTTP: ' . $httpCodeUser . ' - ' . $curlError);
  }

  $userData = json_decode($userResponse, true);
  
  if (!isset($userData['id'])) {
    throw new Exception('No se pudo obtener el ID de usuario de Mercado Pago');
  }

  // PASO 2: Consultar movimientos recientes (últimos 30 días)
  // MercadoPago ya no tiene endpoint público de balance, solo de movimientos
  $fechaDesde = date('Y-m-d\T00:00:00.000-00:00', strtotime('-30 days'));
  $fechaHasta = date('Y-m-d\T23:59:59.999-00:00');
  
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/search?' . http_build_query([
      'sort' => 'date_created',
      'criteria' => 'desc',
      'range' => 'date_created',
      'begin_date' => $fechaDesde,
      'end_date' => $fechaHasta,
      'limit' => 100
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 15
  ]);

  $paymentsResponse = curl_exec($ch);
  $httpCodePayments = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErrorPayments = curl_error($ch);
  curl_close($ch);

  $paymentsData = null;
  if ($httpCodePayments === 200) {
    $paymentsData = json_decode($paymentsResponse, true);
  }

  // PASO 3: Calcular estadísticas desde la BD local
  $totalesQuery = $db->query("
    SELECT 
      COUNT(*) as total_transacciones,
      COUNT(CASE WHEN status = 'approved' THEN 1 END) as total_aprobadas,
      COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pendientes,
      COUNT(CASE WHEN status = 'rejected' THEN 1 END) as total_rechazadas,
      SUM(CASE WHEN status = 'approved' THEN transaction_amount ELSE 0 END) as monto_aprobado,
      SUM(CASE WHEN status = 'approved' THEN net_amount ELSE 0 END) as monto_neto,
      SUM(CASE WHEN status = 'approved' THEN fee_amount ELSE 0 END) as total_comisiones,
      SUM(CASE WHEN status = 'pending' THEN transaction_amount ELSE 0 END) as monto_pendiente,
      SUM(CASE WHEN status = 'rejected' THEN transaction_amount ELSE 0 END) as monto_rechazado,
      MAX(date_created) as ultima_transaccion
    FROM mp_movimientos
    WHERE live_mode = " . ($modo === 'produccion' ? '1' : '0')
  );

  $totales = $totalesQuery->fetch_assoc();

  // PASO 4: Calcular balance estimado (desde BD local)
  $balanceQuery = $db->query("
    SELECT 
      SUM(CASE 
        WHEN status = 'approved' THEN net_amount
        ELSE 0 
      END) as balance_estimado,
      SUM(CASE 

        WHEN status = 'approved' AND money_release_date > NOW() THEN net_amount
        ELSE 0 
      END) as balance_retenido,
      SUM(CASE 
        WHEN status = 'approved' AND (money_release_date IS NULL OR money_release_date <= NOW()) THEN net_amount
        ELSE 0 
      END) as balance_disponible
    FROM mp_movimientos
    WHERE live_mode = " . ($modo === 'produccion' ? '1' : '0')
  );

  $balance = $balanceQuery->fetch_assoc();

  // PASO 5: Estadísticas por método de pago
  $metodosPagoQuery = $db->query("
    SELECT 
      payment_method_id,
      COUNT(*) as cantidad,
      SUM(transaction_amount) as monto_total
    FROM mp_movimientos
    WHERE status = 'approved' 
      AND live_mode = " . ($modo === 'produccion' ? '1' : '0') . "
    GROUP BY payment_method_id
    ORDER BY monto_total DESC
    LIMIT 5
  ");

  $metodosPago = [];
  while ($row = $metodosPagoQuery->fetch_assoc()) {
    $metodosPago[] = $row;
  }

  // PASO 6: Últimas transacciones
  $ultimasQuery = $db->query("
    SELECT 
      payment_id,
      status,
      status_detail,
      transaction_amount,
      net_amount,
      fee_amount,
      payment_method_id,
      payment_type_id,
      payer_email,
      date_created,
      date_approved,
      external_reference
    FROM mp_movimientos
    WHERE live_mode = " . ($modo === 'produccion' ? '1' : '0') . "
    ORDER BY date_created DESC
    LIMIT 10
  ");

  $ultimas = [];
  while ($row = $ultimasQuery->fetch_assoc()) {
    $ultimas[] = $row;
  }

  // Construir respuesta completa
  $result = [
    'ok' => true,
    'modo' => $modo,
    'ambiente' => $modo === 'produccion' ? 'Producción (Pagos reales)' : 'Test (Sandbox)',
    'usuario' => [
      'id' => $userData['id'],
      'email' => $userData['email'] ?? null,
      'nickname' => $userData['nickname'] ?? null,
      'nombre' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
      'tipo_cuenta' => $userData['site_id'] ?? null
    ],
    'balance_estimado' => [
      'total' => round((float)($balance['balance_estimado'] ?? 0), 2),
      'disponible' => round((float)($balance['balance_disponible'] ?? 0), 2),
      'retenido' => round((float)($balance['balance_retenido'] ?? 0), 2),
      'moneda' => 'ARS',
      'nota' => 'Calculado desde la base de datos local'
    ],
    'estadisticas' => [
      'total_transacciones' => (int)($totales['total_transacciones'] ?? 0),
      'total_aprobadas' => (int)($totales['total_aprobadas'] ?? 0),
      'total_pendientes' => (int)($totales['total_pendientes'] ?? 0),
      'total_rechazadas' => (int)($totales['total_rechazadas'] ?? 0),
      'monto_aprobado' => round((float)($totales['monto_aprobado'] ?? 0), 2),
      'monto_neto' => round((float)($totales['monto_neto'] ?? 0), 2),
      'total_comisiones' => round((float)($totales['total_comisiones'] ?? 0), 2),
      'monto_pendiente' => round((float)($totales['monto_pendiente'] ?? 0), 2),
      'monto_rechazado' => round((float)($totales['monto_rechazado'] ?? 0), 2),
      'ultima_transaccion' => $totales['ultima_transaccion'] ?? null
    ],
    'metodos_pago_top' => $metodosPago,
    'ultimas_transacciones' => $ultimas,
    'pagos_api' => $paymentsData ? [
      'total_resultados' => $paymentsData['paging']['total'] ?? 0,
      'limite_consultado' => $paymentsData['paging']['limit'] ?? 100,
      'cantidad_retornada' => count($paymentsData['results'] ?? [])
    ] : null,
    'fecha_consulta' => date('Y-m-d H:i:s')
  ];

  // Registrar operación exitosa en log
  $logStmt = $db->prepare("
    INSERT INTO mp_api_log 
    (operacion, metodo, endpoint, response_code, exitoso, ambiente)
    VALUES ('consultar_balance', 'GET', 'https://api.mercadopago.com/users/me', ?, 1, ?)
  ");
  $logStmt->bind_param('is', $httpCodeUser, $modo);
  $logStmt->execute();

  http_response_code(200);
  echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  error_log('[mercadopago/balance] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false, 
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
  ], JSON_UNESCAPED_UNICODE);
}