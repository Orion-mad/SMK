<?php
// /api/parameters/planes/delete.php
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
  require_once $incConn;
  require_once $incCrud;

  $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id_requerido']); exit; }

  $cfg = [
    'table'   => 'prm_planes',
    'pk'      => 'id',
    'cascade' => [
      "DELETE FROM prm_planes_features WHERE plan_id=?"
    ]
  ];

  $out = lcars_delete($cfg, $id);
  if ($out['ok']) { /* 204 ya seteado en helper */ exit; }
  echo json_encode($out);
} catch (Throwable $e) {
  try { DB::get()->rollback(); } catch(Throwable $ignored) {}
  error_log('[planes/delete] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['error'=>'server_exception','detail'=>$e->getMessage()]);
}
