<?php
// /api/parameters/summary.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$DEBUG = isset($_GET['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../_php_errors.log');

try {
  $inc = realpath(__DIR__ . '/../../inc/conect.php');
  if (!$inc || !file_exists($inc)) {
    http_response_code(500);
    echo json_encode(['error' => 'inc_not_found', 'path' => $inc]);
    exit;
  }
  require_once $inc;

  $db = DB::get();

  // --- Resumen de PLANES ---
  $q1 = $db->query("
    SELECT 
      COUNT(*)                    AS total,
      SUM(activo = 1)             AS activos,
      AVG(precio_mensual)         AS pm_prom,
      AVG(precio_anual)           AS pa_prom
    FROM prm_planes
  ");
  $r1 = $q1->fetch_assoc() ?: ['total'=>0,'activos'=>0,'pm_prom'=>0,'pa_prom'=>0];

  $total    = (int)($r1['total']   ?? 0);
  $activos  = (int)($r1['activos'] ?? 0);
  $inactivos= $total - $activos;
  $pm_prom  = (float)($r1['pm_prom'] ?? 0);
  $pa_prom  = (float)($r1['pa_prom'] ?? 0);

  $moneda = 'ARG';
  if ($total > 0) {
    $qMon = $db->query("
      SELECT moneda 
      FROM prm_planes 
      GROUP BY moneda 
      ORDER BY COUNT(*) DESC, moneda ASC 
      LIMIT 1
    ");
    if ($qMon && ($rowMon = $qMon->fetch_assoc())) {
      $moneda = $rowMon['moneda'] ?: 'ARG';
    }
  }

  $q2 = $db->query("SELECT COUNT(*) AS total_features FROM prm_planes_features");
  $r2 = $q2->fetch_assoc() ?: ['total_features'=>0];
  $features = (int)($r2['total_features'] ?? 0);

  // --- Resumen de SERVICIOS ---
  $q3 = $db->query("
    SELECT 
      COUNT(*)                       AS total,
      SUM(estado = 'activo')         AS activos,
      SUM(estado = 'suspendido')     AS suspendidos,
      SUM(estado = 'cancelado')      AS cancelados,
      AVG(precio_usd)                AS precio_prom_usd,
      COUNT(DISTINCT plan_id)        AS planes_usados
    FROM prm_servicios
  ");
  $r3 = $q3->fetch_assoc() ?: [
    'total'=>0, 'activos'=>0, 'suspendidos'=>0, 'cancelados'=>0,
    'precio_prom_usd'=>0, 'planes_usados'=>0
  ];

  $srv_total      = (int)($r3['total'] ?? 0);
  $srv_activos    = (int)($r3['activos'] ?? 0);
  $srv_suspendidos= (int)($r3['suspendidos'] ?? 0);
  $srv_cancelados = (int)($r3['cancelados'] ?? 0);
  $srv_inactivos  = $srv_suspendidos + $srv_cancelados;
  $srv_precio_usd = (float)($r3['precio_prom_usd'] ?? 0);
  $srv_planes     = (int)($r3['planes_usados'] ?? 0);

  // Estimación aproximada en ARS (sin cotización real, solo ilustrativo)
  // En producción usarías tc_get() del moneda_helper con fecha actual
  $tc_aproximado = 1000; // cambiar por cotización real
  $srv_precio_ars_aprox = round($srv_precio_usd * $tc_aproximado, 2);

  $out = [
    'planes' => [
      'total'     => $total,
      'activos'   => $activos,
      'inactivos' => $inactivos,
      'pm_prom'   => $pm_prom,
      'pa_prom'   => $pa_prom,
      'moneda'    => $moneda ?: 'ARG',
      'features'  => $features,
    ],
    'servicios' => [
      'total'           => $srv_total,
      'activos'         => $srv_activos,
      'suspendidos'     => $srv_suspendidos,
      'cancelados'      => $srv_cancelados,
      'inactivos'       => $srv_inactivos,
      'precio_prom_usd' => $srv_precio_usd,
      'precio_ars_aprox'=> $srv_precio_ars_aprox,
      'planes_usados'   => $srv_planes,
      'moneda'          => 'DOL', // servicios están en USD
    ]
  ];

  http_response_code(200);
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
    
    
// EstimaciÓn aproximada en ARS (sin cotizaciÓn real, solo ilustrativo)
  // En producciÓn usarÍas tc_get() del moneda_helper con fecha actual
  $tc_aproximado = 1000; // cambiar por cotizaciÓn real
  $srv_precio_ars_aprox = round($srv_precio_usd * $tc_aproximado, 2);

  // --- Resumen de CONCEPTOS DE CAJA ---
  $q4 = $db->query("
    SELECT 
      COUNT(*)                     AS total,
      SUM(tipo_flujo = 'ingreso') AS ingresos,
      SUM(tipo_flujo = 'egreso')  AS egresos,
      SUM(activo = 1)             AS activos
    FROM prm_conceptos_caja
  ");
  $r4 = $q4 ? $q4->fetch_assoc() : ['total'=>0,'ingresos'=>0,'egresos'=>0,'activos'=>0];

  $conceptos_total    = (int)($r4['total'] ?? 0);
  $conceptos_ingresos = (int)($r4['ingresos'] ?? 0);
  $conceptos_egresos  = (int)($r4['egresos'] ?? 0);
  $conceptos_activos  = (int)($r4['activos'] ?? 0);

  // Conceptos por categoría (para gráfico)
  $q5 = $db->query("
    SELECT 
      cat.nombre AS categoria,
      cat.color,
      COUNT(*) AS total
    FROM prm_conceptos_caja c
    INNER JOIN prm_conceptos_categorias cat ON cat.id = c.categoria_id
    WHERE c.activo = 1
    GROUP BY cat.id, cat.nombre, cat.color
    ORDER BY total DESC
    LIMIT 5
  ");

  $conceptos_por_categoria = [];
  if ($q5) {
    while ($row = $q5->fetch_assoc()) {
      $conceptos_por_categoria[] = [
        'categoria' => $row['categoria'],
        'color' => $row['color'],
        'total' => (int)$row['total']
      ];
    }
  }

  $out = [
    'planes' => [
      'total'     => $total,
      'activos'   => $activos,
      'inactivos' => $inactivos,
      'pm_prom'   => $pm_prom,
      'pa_prom'   => $pa_prom,
      'moneda'    => $moneda ?: 'ARG',
      'features'  => $features,
    ],
    'servicios' => [
      'total'           => $srv_total,
      'activos'         => $srv_activos,
      'suspendidos'     => $srv_suspendidos,
      'cancelados'      => $srv_cancelados,
      'inactivos'       => $srv_inactivos,
      'precio_prom_usd' => $srv_precio_usd,
      'precio_ars_aprox'=> $srv_precio_ars_aprox,
      'planes_usados'   => $srv_planes,
      'moneda'          => 'DOL', // servicios estÁn en USD
    ],
    'conceptos' => [
      'total'            => $conceptos_total,
      'ingresos'         => $conceptos_ingresos,
      'egresos'          => $conceptos_egresos,
      'activos'          => $conceptos_activos,
      'por_categoria'    => $conceptos_por_categoria,
    ]
  ];    
    
    
    
    
} catch (Throwable $e) {
  error_log('[parameters/summary] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}