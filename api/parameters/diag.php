<?php
header('Content-Type: application/json; charset=utf-8');

$resp = [
  'php' => PHP_VERSION,
  'extensions' => [
    'mysqli'  => extension_loaded('mysqli'),
    'mysqlnd' => extension_loaded('mysqlnd'),
  ],
];

$inc = realpath(__DIR__ . '/../../inc/conect.php'); // desde /api/parameters/ a /api/inc/conect.php
$resp['inc_path']   = $inc;
$resp['inc_exists'] = $inc && file_exists($inc);

if ($resp['inc_exists']) {
  require_once $inc;
  $resp['mysqli_ok'] = isset($mysqli) && ($mysqli instanceof mysqli);
  if ($resp['mysqli_ok']) {
    $resp['mysql_server_info'] = $mysqli->server_info;
    $resp['mysql_host_info']   = $mysqli->host_info;

    // Tablas
    $check = function($t) use ($mysqli) {
      $q = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($t)."'");
      return $q && $q->num_rows > 0;
    };
    $resp['tables'] = [
      'prm_planes'           => $check('prm_planes'),
      'prm_planes_features'  => $check('prm_planes_features'),
    ];

    // Conteos (si existen)
    if ($resp['tables']['prm_planes']) {
      $c = $mysqli->query("SELECT COUNT(*) c FROM prm_planes");
      $resp['planes_count'] = $c ? (int)$c->fetch_assoc()['c'] : null;
    }
  }
}

echo json_encode($resp);
