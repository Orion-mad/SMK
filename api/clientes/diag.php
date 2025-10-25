<?php
// /api/clientes/presupuestos/check_enum.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

$db = DB::get();
$db->query("UPDATE cli_presupuestos SET estado = 'aprobado' WHERE estado = 'cancelado'");
