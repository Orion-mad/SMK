<?php
// /api/parameters/servicios/get.php
header('Content-Type: application/json; charset=utf-8');

// DEBUG opcional: ?debug=1
$DEBUG = isset($_GET['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../../_php_errors.log');

try {
  // include usando tu clase DB::get()
  $inc = realpath(__DIR__ . '/../../../inc/conect.php');
  if (!$inc || !file_exists($inc)) { 
    http_response_code(500); 
    echo json_encode(['error'=>'inc_not_found','path'=>$inc]); 
    exit; 
  }
  require_once $inc;
  $db = DB::get();

  // id requerido
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'id_requerido']); 
    exit; 
  }

  // Servicio con datos del plan
  $q = $db->prepare("SELECT 
                      s.id, s.codigo, s.nombre, s.plan_id, s.descripcion,
                      s.precio_usd, s.tipo_cobro, s.fecha_inicio, s.estado,
                      s.orden, s.observaciones, s.creado_en, s.actualizado_en,
                      p.codigo AS plan_codigo,
                      p.nombre AS plan_nombre,
                      p.precio_mensual AS plan_costo_mensual,
                      p.precio_anual AS plan_costo_anual,
                      p.moneda AS plan_moneda
                    FROM prm_servicios s
                    INNER JOIN prm_planes p ON p.id = s.plan_id
                    WHERE s.id = ? LIMIT 1");
  $q->bind_param('i', $id);
  $q->execute();
  $q->store_result();
  
  if ($q->num_rows === 0) { 
    $q->free_result(); 
    $q->close(); 
    http_response_code(404); 
    echo json_encode(['error'=>'no_encontrado']); 
    exit; 
  }

  $q->bind_result(
    $sid, $scodigo, $snombre, $splan_id, $sdesc,
    $sprecio, $stipo, $sfecha, $sestado,
    $sorden, $sobs, $screado, $sact,
    $pcodigo, $pnombre, $pmensual, $panual, $pmoneda
  );
  $q->fetch();
  
  $servicio = [
    'id'                    => (int)$sid,
    'codigo'                => (string)$scodigo,
    'nombre'                => (string)$snombre,
    'plan_id'               => (int)$splan_id,
    'descripcion'           => $sdesc === null ? null : (string)$sdesc,
    'precio_usd'            => (float)$sprecio,
    'tipo_cobro'            => (string)$stipo,
    'fecha_inicio'          => $sfecha,
    'estado'                => (string)$sestado,
    'orden'                 => (int)$sorden,
    'observaciones'         => $sobs === null ? null : (string)$sobs,
    'creado_en'             => (string)$screado,
    'actualizado_en'        => (string)$sact,
    'plan_codigo'           => (string)$pcodigo,
    'plan_nombre'           => (string)$pnombre,
    'plan_costo_mensual'    => (float)$pmensual,
    'plan_costo_anual'      => (float)$panual,
    'plan_moneda'           => (string)$pmoneda,
    'features'              => [],
  ];
  $q->free_result();
  $q->close();

  // Features del plan (heredadas)
  $f = $db->prepare("SELECT id, plan_id, titulo, valor, unidad, orden, activo
                     FROM prm_planes_features
                     WHERE plan_id = ? AND activo = 1
                     ORDER BY orden ASC, id ASC");
  $f->bind_param('i', $servicio['plan_id']);
  $f->execute();
  $f->store_result();
  $f->bind_result($fid, $fplan, $ftitulo, $fvalor, $funidad, $forden, $factivo);
  
  while ($f->fetch()) {
    $servicio['features'][] = [
      'id'     => (int)$fid,
      'titulo' => (string)$ftitulo,
      'valor'  => $fvalor === null ? null : (string)$fvalor,
      'unidad' => $funidad === null ? null : (string)$funidad,
      'orden'  => (int)$forden,
      'activo' => (int)$factivo,
    ];
  }
  $f->free_result();
  $f->close();

  http_response_code(200);
  echo json_encode($servicio, JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  error_log('[servicios/get] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}