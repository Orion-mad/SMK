<?php
// Configuración DSM
$DSM_HOST = "sysmika.org";
$DSM_PORT = 5001; // o 5001 si usás HTTPS
$DSM_USER = "admin"; // idealmente un usuario con pocos permisos
$DSM_PASS = "MiGuel60";
$USE_HTTPS = true; // true si usás 5001 con certificado válido

$base = ($USE_HTTPS ? "https" : "http") . "://$DSM_HOST:$DSM_PORT/webapi";

// 1. Login
$login_url = "$base/auth.cgi?api=SYNO.API.Auth&method=login&version=6"
           . "&account=" . urlencode($DSM_USER)
           . "&passwd=" . urlencode($DSM_PASS)
           . "&session=Core&format=sid";

$login = @file_get_contents($login_url);
if (!$login) {
    echo json_encode(["success" => false, "error" => "Conexión fallida"]); exit;
}
$loginData = json_decode($login, true);
if (empty($loginData['data']['sid'])) {
    echo json_encode(["success" => false, "error" => "Login DSM fallido"]); exit;
}
$sid = $loginData['data']['sid'];

// 2. Obtener datos de utilización
$url = "$base/entry.cgi?api=SYNO.Core.System.Utilization&method=get&version=1&_sid=$sid";
$data = @file_get_contents($url);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Error al obtener datos"]); exit;
}
echo $data;
?>