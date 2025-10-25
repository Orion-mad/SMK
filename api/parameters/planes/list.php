<?php
// /api/parameters/planes/list.php
header('Content-Type: application/json; charset=utf-8');

$DEBUG = isset($_GET['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/../../_php_errors.log');

try {
  // includes
  $incConn = realpath(__DIR__ . '/../../../inc/conect.php');
  $incList = realpath(__DIR__ . '/../../core/list_helper.php');
  if (!$incConn || !$incList) { http_response_code(500); echo json_encode(['error'=>'includes_not_found']); exit; }
  require_once $incConn;
  require_once $incList;

  // Config del listado (alias => expr SQL seguro)
  $cfg = [
    'table'  => 'prm_planes p',
    'select' => [
      'id'             => 'p.id',
      'codigo'         => 'p.codigo',
      'nombre'         => 'p.nombre',
      'descripcion'    => 'p.descripcion',
      'moneda'         => 'p.moneda',
      'precio_mensual' => 'p.precio_mensual',
      'precio_anual'   => 'p.precio_anual',
      'orden'          => 'p.orden',
      'activo'         => 'p.activo',
      'creado_en'      => 'p.creado_en',
      'actualizado_en' => 'p.actualizado_en',
    ],
    'searchable'   => ['codigo','nombre','descripcion'],
    'orderable'    => ['orden','id','codigo','nombre','precio_mensual','precio_anual'],
    'default_order'=> ['orden'=>'ASC', 'id'=>'ASC'],
    'numeric'      => ['id'=>'int','precio_mensual'=>'float','precio_anual'=>'float','orden'=>'int','activo'=>'int'],
    'filters'      => [
      // ?activo=1
      'activo' => ['col'=>'p.activo','type'=>'int'],
      // ?moneda=ARG (SET)
      'moneda' => ['col'=>'p.moneda','type'=>'str','in'=>['ARG','DOL','EUR']],
    ],
    'per_page'     => 100
  ];

  $result = lcars_list($cfg, $_GET);

  // Si el front actual espera solo array, devolvé items; si querés meta, descomentá abajo
  // echo json_encode($result); exit;
  echo json_encode($result['items']); // compat con tu UI actual
} catch (Throwable $e) {
  error_log('[planes/list] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}
