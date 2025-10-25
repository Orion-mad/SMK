<?php
// migrate_presupuestos.php
// Migración de smk.presupuestos -> orion.cli_presupuestos
// Usa mysqli (OO). Ajusta credenciales y hosts según tu entorno.

$host = 'localhost';
$user = 'root';
$pass = 'Miguel#1960';       // <-- cambia si hace falta
$port = 3306;
$db_smk   = 'smk';
$db_orion = 'orion';

$mysqli = new mysqli($host, $user, $pass, '', $port);
if ($mysqli->connect_errno) {
    die("Fallo conexión MySQL: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
}
$mysqli->set_charset('utf8mb4');

// -- Helper: normaliza/escapa strings (mysqli prepared statements se usan más abajo)
function map_estado($estado_origen) {
    // Mapeo pedido:
    // 'enviado' -> 'enviado'
    // 'aceptado' -> 'aprobado'
    // 'pago' -> 'cancelado'
    $e = strtolower(trim($estado_origen));
    switch ($e) {
        case 'enviado': return 'enviado';
        case 'aceptado': return 'aprobado';
        case 'pago': return 'cancelado';
        default: return 'borrador';
    }
}

function map_activo($activo_origen) {
    // origen: set('si','no') -> destino tinyint(1)
    $a = strtolower(trim($activo_origen));
    return ($a === 'si') ? 1 : 0;
}

// Preparamos: seleccionamos todos los registros de la tabla origen
$sql_sel = "SELECT * FROM `{$db_smk}`.`presupuestos`";
if (!$result = $mysqli->query($sql_sel)) {
    die("Error al leer presupuestos: {$mysqli->error}\n");
}

$total_rows = $result->num_rows;
echo "Registros a procesar: {$total_rows}\n";

if ($total_rows === 0) {
    exit("No hay registros en {$db_smk}.presupuestos\n");
}

// Empezamos transacción en la base destino
$mysqli->begin_transaction();

try {
    // Desactivar comprobación de claves foráneas para evitar errores FK (si lo prefieres quita estas líneas)
    if (!$mysqli->query("SET FOREIGN_KEY_CHECKS = 0")) {
        throw new Exception("No se pudo desactivar FOREIGN_KEY_CHECKS: " . $mysqli->error);
    }

    // Prepared statement para insertar en cli_presupuestos
    // Campos que rellenamos explícitamente: codigo, cliente_id, fecha_emision, fecha_vencimiento (deja NULL),
    // dias_validez (dejar default 30), estado, titulo, introduccion, condiciones,
    // moneda (default ARG), subtotal, iva_monto (0), iva_porc (21 por defecto en ddl),
    // total, tipo_cobro (default mensual), forma_pago NULL, version 1, orden 0, activo, creado_por NULL,
    // actualizado_por NULL, creado_en/actualizado_en usan default CURRENT_TIMESTAMP.
    $insert_sql = "
        INSERT INTO `{$db_orion}`.`cli_presupuestos` (
            codigo, cliente_id, fecha_emision, fecha_vencimiento, dias_validez, estado,
            titulo, introduccion, condiciones, observaciones, notas_internas,
            moneda, subtotal, descuento_porc, descuento_monto, iva_porc, iva_monto, total,
            tipo_cobro, forma_pago, version, presupuesto_original_id, orden, activo,
            creado_por, actualizado_por, aprobado_por, aprobado_en, enviado_en
        ) VALUES (
            ?, ?, ?, NULL, 30, ?,
            ?, ?, ?, NULL, NULL,
            'ARG', ?, 0.00, 0.00, 21.00, 0.00, ?,
            'mensual', NULL, 1, NULL, 0, ?,
            NULL, NULL, NULL, NULL, NULL
        )
    ";
    $stmt = $mysqli->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception("Error preparando insert: " . $mysqli->error);
    }

    $inserted = 0;
    $skipped = 0;
    while ($row = $result->fetch_assoc()) {
        // Mapeos solicitados:
        // fecha -> fecha_emision
        $fecha_emision = $row['fecha']; // asume formato 'YYYY-MM-DD' (campo date)
        // titulo -> titulo (ya existe)
        $titulo = $row['titulo'] ?? null;

        // cliente (origen es varchar) -> cliente_id (destino int)
        // No intentamos buscar coincidencias en clientes; colocamos el id numérico si detectamos uno
        // en el texto (por ejemplo "123 - Nombre") o ponemos el id del registro origen para trazabilidad.
        $cliente_id = null;
        $cliente_origen = trim($row['cliente'] ?? '');
        if ($cliente_origen === '') {
            // valor coherente: 0 (pero podría violar FK) -> usamos id de origen para rastrear
            $cliente_id = (int)$row['id'];
        } else {
            // si el texto empieza con número lo usamos, sino usamos id origen
            if (preg_match('/^(\d+)\b/', $cliente_origen, $m)) {
                $cliente_id = (int)$m[1];
            } else {
                // usar id del presupuesto como cliente_id "coherente"
                $cliente_id = (int)$row['id'];
            }
        }

        // monto -> subtotal y total
        $monto = (float)$row['monto'];
        $subtotal = $monto;
        $total = $monto;

        // desarrollo -> introduccion
        $introduccion = $row['desarrollo'] ?? null;

        // notas -> condiciones
        $condiciones = $row['notas'] ?? null;

        // activo set('si','no') -> tinyint
        $activo = map_activo($row['activo'] ?? 'si');

        // estado mapeado
        $estado = map_estado($row['estado'] ?? 'enviado');

        // generar codigo único (P-{id_origen}-{timestamp})
        $codigo = 'P-' . $row['id'] . '-' . time();

        // Bind params: ?, ?, ?, ?, ?, ?, ?, ?, ?, ? positions per prepared sql
        // order in VALUES: codigo, cliente_id, fecha_emision, estado, titulo, introduccion, condiciones, subtotal, total, activo
        // Note: adjust binding types: s = string, i = int, d = double
        $stmt->bind_param(
            'sisssssdid', 
            $codigo,        // s
            $cliente_id,    // i
            $fecha_emision, // s
            $estado,        // s
            $titulo,        // s
            $introduccion,  // s
            $condiciones,   // s
            $subtotal,      // d
            $total,         // d
            $activo         // i
        );

        if (!$stmt->execute()) {
            // Si falla en un registro, lo anotamos y seguimos (o lanzar excepción para rollback)
            // Aquí elegimos lanzar excepción para mantener la integridad y hacer rollback.
            throw new Exception("Error insertando id origen {$row['id']}: " . $stmt->error);
        } else {
            $inserted++;
        }
    }

    // Reactivar FK checks
    if (!$mysqli->query("SET FOREIGN_KEY_CHECKS = 1")) {
        throw new Exception("No se pudo reactivar FOREIGN_KEY_CHECKS: " . $mysqli->error);
    }

    $mysqli->commit();
    echo "Migración completada: Insertados = {$inserted}, Saltados = {$skipped}\n";

    $stmt->close();
    $result->free();
    $mysqli->close();
} catch (Exception $e) {
    $mysqli->rollback();
    // Intentar reactivar FK checks por seguridad
    @$mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
    $msg = $e->getMessage();
    die("ERROR: Transacción revertida. Detalle: {$msg}\n");
}
