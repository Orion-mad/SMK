<?php
// Devuelve cuántos ARS vale 1 unidad de la MONEDA Orion ('ARG','DOL','EUR').
function tc_provider_frankfurter(string $moneda): ?float {
    $map = ['ARG'=>'ARS','DOL'=>'USD','EUR'=>'EUR'];
    $from = $map[$moneda] ?? 'ARS';
    if ($from === 'ARS') return 1.0;

    $url = "https://api.frankfurter.dev/latest?from={$from}&to=ARS";
    $ctx = stream_context_create(['http'=>['timeout'=>5]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;

    $data = json_decode($json, true);
    return isset($data['rates']['ARS']) ? (float)$data['rates']['ARS'] : null;
}
