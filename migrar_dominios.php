<?php
/**
 * migrar_dominios_fix.php
 * Copia smk.dominios -> orion.dominios (mapeo solicitado)
 *
 * Reglas:
 * - cliente, plan, servicio -> 1
 * - dom_extra descartado
 * - evitar duplicados por dominio en destino
 */

$host = 'localhost';
$port = 3306;
$user = 'root';   // ajustar
$pass = 'Miguel#1960';       // ajustar
$charset = 'utf8mb4';

$dbSource = 'smk';
$dbTarget = 'orion';

$dryRun = false;      // true = simula, no inserta. Cambiar a false para ejecutar.
$batchSize = 200;

$dsn = "mysql:host=$host;port=$port;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Conectado a MySQL en $host:$port\n";

    // SELECT del origen
    $sqlSelect = "SELECT id, dominio, ip, proveedor, cliente, ingreso, creado, plan, servicio, dom_extra, usuario, claves, extra, activo
                  FROM {$dbSource}.dominios
                  ORDER BY id";
    $stmtSrc = $pdo->query($sqlSelect);

    // INSERT a destino: declarar exactamente las columnas (sin id)
    $insertSql = "INSERT INTO {$dbTarget}.dominios
    (codigo, cliente_id, plan_id, dominio, tipo_dominio, proveedor_hosting, servidor, panel_control, url_panel, usuario_hosting,
     password_hosting, registrador, fecha_registro, fecha_vencimiento, ns1, ns2, ns3, ns4, ip_principal, ssl_activo,
     ssl_tipo, ssl_vencimiento, estado, renovacion_auto, orden, observaciones, detalles, creado_por, actualizado_por, creado_en, actualizado_en)
    VALUES
    (:codigo, :cliente_id, :plan_id, :dominio, :tipo_dominio, :proveedor_hosting, :servidor, :panel_control, :url_panel, :usuario_hosting,
     :password_hosting, :registrador, :fecha_registro, :fecha_vencimiento, :ns1, :ns2, :ns3, :ns4, :ip_principal, :ssl_activo,
     :ssl_tipo, :ssl_vencimiento, :estado, :renovacion_auto, :orden, :observaciones, :detalles, :creado_por, :actualizado_por, :creado_en, :actualizado_en)";

    $insertStmt = $pdo->prepare($insertSql);

    // check duplicado por dominio
    $checkSql = "SELECT id FROM {$dbTarget}.dominios WHERE dominio = :dominio LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);

    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    $count = $inserted = $skipped = $errors = 0;

    while ($row = $stmtSrc->fetch()) {
        $count++;

        $id_src = $row['id'];
        $dominio = trim($row['dominio']);
        if ($dominio === '') {
            echo "[SKIP] id={$id_src} sin dominio\n";
            $skipped++;
            continue;
        }

        // evitar duplicados
        $checkStmt->execute([':dominio' => $dominio]);
        if ($checkStmt->fetchColumn()) {
            echo "[SKIP DUP] id={$id_src} dominio={$dominio} ya existe\n";
            $skipped++;
            continue;
        }

        // Mapear valores
        $codigo = 'dom-' . $id_src . '-' . time();
        $cliente_id = $row['cliente'];     // solicitado
        $plan_id = 1;        // solicitado
        $tipo_dominio = 'principal';
        $proveedor_hosting = $row['proveedor'] !== '' ? $row['proveedor'] : null;
        $servidor = null;
        $panel_control = null;
        $url_panel = null;
        $usuario_hosting = $row['usuario'] !== '' ? $row['usuario'] : null;
        $password_hosting = $row['claves'] !== '' ? $row['claves'] : null;
        $registrador = null;

        $fecha_registro = null;
        if (!empty($row['ingreso']) && $row['ingreso'] !== '0000-00-00') {
            $fecha_registro = $row['ingreso'];
        }

        $fecha_vencimiento = null;
        $ns1 = $ns2 = $ns3 = $ns4 = null;
        $ip_principal = $row['ip'] !== '' ? $row['ip'] : null;
        $ssl_activo = 0;
        $ssl_tipo = null;
        $ssl_vencimiento = null;
        $estado = (strtolower(trim($row['activo'])) === 'si') ? 'activo' : 'suspendido';
        $renovacion_auto = 0;
        $orden = 0;
        $observaciones = $row['extra'] !== '' ? $row['extra'] : null;
        $detalles = null;
        $creado_por = null;
        $actualizado_por = null;

        // creacion timestamps
        if (!empty($row['creado']) && $row['creado'] !== '0000-00-00') {
            $creado_en = $row['creado'] . ' 00:00:00';
        } else {
            $creado_en = date('Y-m-d H:i:s');
        }
        $actualizado_en = null; // dejar por defecto -> column tiene ON UPDATE CURRENT_TIMESTAMP

        if ($dryRun) {
            echo "[DRY] id={$id_src} dominio={$dominio} -> preparado para insertar\n";
            $inserted++;
        } else {
            try {
                $insertStmt->execute([
                    ':codigo' => $codigo,
                    ':cliente_id' => $cliente_id,
                    ':plan_id' => $plan_id,
                    ':dominio' => $dominio,
                    ':tipo_dominio' => $tipo_dominio,
                    ':proveedor_hosting' => $proveedor_hosting,
                    ':servidor' => $servidor,
                    ':panel_control' => $panel_control,
                    ':url_panel' => $url_panel,
                    ':usuario_hosting' => $usuario_hosting,
                    ':password_hosting' => $password_hosting,
                    ':registrador' => $registrador,
                    ':fecha_registro' => $fecha_registro,
                    ':fecha_vencimiento' => $fecha_vencimiento,
                    ':ns1' => $ns1,
                    ':ns2' => $ns2,
                    ':ns3' => $ns3,
                    ':ns4' => $ns4,
                    ':ip_principal' => $ip_principal,
                    ':ssl_activo' => $ssl_activo,
                    ':ssl_tipo' => $ssl_tipo,
                    ':ssl_vencimiento' => $ssl_vencimiento,
                    ':estado' => $estado,
                    ':renovacion_auto' => $renovacion_auto,
                    ':orden' => $orden,
                    ':observaciones' => $observaciones,
                    ':detalles' => $detalles,
                    ':creado_por' => $creado_por,
                    ':actualizado_por' => $actualizado_por,
                    ':creado_en' => $creado_en,
                    ':actualizado_en' => $actualizado_en,
                ]);
                $inserted++;
                echo "[OK] id={$id_src} dominio={$dominio} insertado\n";
            } catch (Exception $e) {
                $errors++;
                echo "[ERROR] id={$id_src} dominio={$dominio} -> " . $e->getMessage() . "\n";
            }
        }

        // commit por lotes
        if (!$dryRun && ($inserted % $batchSize) === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "[COMMIT parcial] inserted={$inserted}\n";
        }
    }

    if (!$dryRun) {
        $pdo->commit();
        echo "[COMMIT final] inserted={$inserted}\n";
    }

    echo "\nResumen: procesados={$count}, insertados={$inserted}, omitidos={$skipped}, errores={$errors}\n";

} catch (PDOException $ex) {
    echo "Falla de conexión o SQL: " . $ex->getMessage() . PHP_EOL;
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transacción revertida.\n";
    }
    exit(1);
}
