<?php
// /api/parameters/planes/save.php
header('Content-Type: application/json; charset=utf-8');

$DEBUG = isset($_GET['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../../_php_errors.log');
try {
  $incConn = realpath(__DIR__ . '/../../../inc/conect.php');
  $incCrud = realpath(__DIR__ . '/../../core/crud_helper.php');
  if (!$incConn || !$incCrud) { http_response_code(500); echo json_encode(['error'=>'includes_not_found']); exit; }
  //require_once __DIR__ . '/../../core/codigo_helper.php';    
  require_once $incConn;
  require_once $incCrud;

  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  if (!is_array($in)) { http_response_code(400); echo json_encode(['error'=>'json_invalido']); exit; }
  $db = DB::get();    

  // Config de PLanes
  $cfg = [
    'table' => 'prm_planes',
    'pk'    => 'id',
    'fields'=> [
      'codigo'         => ['col'=>'codigo','type'=>'str','required'=>true,'max'=>64],
      'nombre'         => ['col'=>'nombre','type'=>'str','required'=>true,'max'=>128],
      'descripcion'    => ['col'=>'descripcion','type'=>'str','nullable'=>true,'max'=>500],
      // SET('ARG','DOL','EUR') con sinónimos
      'moneda'         => ['col'=>'moneda','type'=>'set','in'=>['ARG','DOL','EUR'], 'default'=>'ARG',
                           'syn'=>['ARS'=>'ARG','USD'=>'DOL','EURO'=>'EUR','PESO'=>'ARG','PESOS'=>'ARG','DOLAR'=>'DOL']],
      'precio_mensual' => ['col'=>'precio_mensual','type'=>'float','default'=>0],
      'precio_anual'   => ['col'=>'precio_anual','type'=>'float','default'=>0],
      'orden'          => ['col'=>'orden','type'=>'int','default'=>0],
      'activo'         => ['col'=>'activo','type'=>'bool','default'=>1],
    ],
    'unique'=> ['codigo'],

    // Children → reemplazar features
    'children'=> [
      'collection_key' => 'features',
      'replace'        => true,
      'table'          => 'prm_planes_features',
      'fk'             => 'plan_id',
      'fields'         => [
        'titulo' => ['col'=>'titulo','type'=>'str','required'=>true,'max'=>128],
        'valor'  => ['col'=>'valor','type'=>'str','nullable'=>true,'max'=>64],
        'unidad' => ['col'=>'unidad','type'=>'str','nullable'=>true,'max'=>24],
        'orden'  => ['col'=>'orden','type'=>'int','default'=>0],
        'activo' => ['col'=>'activo','type'=>'bool','default'=>1],
      ],
    ],
  ];

  $out = lcars_save($cfg, $in);
  echo json_encode($out);
} catch (Throwable $e) {
  try { DB::get()->rollback(); } catch(Throwable $ignored) {}
  error_log('[planes/save] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}
