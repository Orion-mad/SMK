<?php
// api/util/codigo_next.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';
require_once __DIR__ . '/codigo_helper.php'; // ajustá si tu árbol difiere

try {
  $db     = DB::get();
  $tabla  = $_GET['tabla']  ?? $_POST['tabla']  ?? '';
  $campo  = $_GET['campo']  ?? $_POST['campo']  ?? 'codigo';
  $width  = (int)($_GET['digits'] ?? $_POST['digits'] ?? 7);

  if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla))  throw new RuntimeException('Tabla inválida');
  if (!preg_match('/^[A-Za-z0-9_]+$/', $campo))  throw new RuntimeException('Campo inválido');
  if ($width < 3 || $width > 9) $width = 7;

  // (Opcional) lista blanca:
  // $permitidas = ['servicios','planes','clientes'];
  // if (!in_array($tabla, $permitidas, true)) throw new RuntimeException('Tabla no permitida');

  $codigo = lcars_generar_codigo($db, $tabla, $campo, $width);
  echo json_encode(['ok'=>true, 'codigo'=>$codigo], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
