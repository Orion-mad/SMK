<?php
// /api/core/moneda_cotizacion.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
try{
    function pesos_a_usd() {

        $url = "https://dolarapi.com/v1/dolares/oficial";
        $json = @file_get_contents($url);
        if ($json === false) {
            return null; // o podrías lanzar un error
        }
        $data = json_decode($json, true);
        return $data['venta'];
        echo json_encode(['cotizacion' => pesos_a_usd()]);
        exit;
    }
    
    

} catch (Throwable $e) {
  error_log('[core/moneda_cotizacion] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}