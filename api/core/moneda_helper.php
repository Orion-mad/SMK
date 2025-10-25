<?php
// mysqli-OO asumido en $mysqli (o injéctalo)
function tc_get(mysqli $db, string $moneda, ?string $fecha = null): float {
    $mon = in_array($moneda, ['ARG','DOL','EUR']) ? $moneda : 'ARG';
    $fechaRef = $fecha ?: date('Y-m-d');
    $sql = "SELECT valor_ars 
              FROM tipos_cambio 
             WHERE moneda = ? AND fecha <= ? 
          ORDER BY fecha DESC 
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $mon, $fechaRef);
    $stmt->execute();
    $stmt->bind_result($valor);
    if ($stmt->fetch()) { $stmt->close(); return (float)$valor; }
    $stmt->close();

    // Fallback: último disponible
    $sql = "SELECT valor_ars FROM tipos_cambio WHERE moneda = ? ORDER BY fecha DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $mon);
    $stmt->execute();
    $stmt->bind_result($valor2);
    if ($stmt->fetch()) { $stmt->close(); return (float)$valor2; }
    $stmt->close();

    return ($mon === 'ARG') ? 1.0 : 0.0; // si no hay datos
}

function cotizar_a_ars(mysqli $db, string $moneda, float $monto, ?string $fecha = null): float {
    if ($monto <= 0) return 0.0;
    $tc = tc_get($db, $moneda, $fecha);
    return round($monto * $tc, 2);
}

function convertir(mysqli $db, string $desde, string $hacia, float $monto, ?string $fecha=null): float {
    if ($monto <= 0) return 0.0;
    if ($desde === $hacia) return round($monto, 2);
    $ars = cotizar_a_ars($db, $desde, $monto, $fecha);
    if ($hacia === 'ARG') return $ars;
    // ARS -> otra moneda
    $tcHacia = tc_get($db, $hacia, $fecha);
    return $tcHacia > 0 ? round($ars / $tcHacia, 2) : 0.0;
}
