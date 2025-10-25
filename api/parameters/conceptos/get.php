<?php
// /api/parameters/planes/get.php
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

  // Plan (sin get_result)
  $q = $db->prepare("SELECT id, codigo, nombre, descripcion, moneda, precio_mensual, precio_anual, 
                            orden, activo, creado_en, actualizado_en
                     FROM prm_planes WHERE id=? LIMIT 1");
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

  $q->bind_result($rid, $rcodigo, $rnombre, $rdesc, $rmoneda, $rpm, $rpa, $rorden, $ractivo, $rcreado, $ract);
  $q->fetch();
  
  $plan = [
    'id'              => (int)$rid,
    'codigo'          => (string)$rcodigo,
    'nombre'          => (string)$rnombre,
    'descripcion'     => $rdesc === null ? null : (string)$rdesc,
    'moneda'          => (string)$rmoneda,
    'precio_mensual'  => (float)$rpm,
    'precio_anual'    => (float)$rpa,
    'orden'           => (int)$rorden,
    'activo'          => (int)$ractivo,
    'creado_en'       => (string)$rcreado,
    'actualizado_en'  => (string)$ract,
    'features'        => [],
  ];
  $q->free_result();
  $q->close();

  // Features del plan
  $f = $db->prepare("SELECT id, plan_id, titulo, valor, unidad, orden, activo
                     FROM prm_planes_features
                     WHERE plan_id=?
                     ORDER BY orden ASC, id ASC");
  $f->bind_param('i', $id);
  $f->execute();
  $f->store_result();
  $f->bind_result($fid, $fplan, $ftitulo, $fvalor, $funidad, $forden, $factivo);
  
  while ($f->fetch()) {
    $plan['features'][] = [
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
  echo json_encode($plan, JSON_UNESCAPED_UNICODE);
  
} catch (Throwable $e) {
  error_log('[planes/get] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}