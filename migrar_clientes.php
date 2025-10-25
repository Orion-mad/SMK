<?php
/**
 * Migración desde smk.clientes (clientes (4).sql) -> orion.clientes (clientes (3).sql)
 * - Respeta el id de origen.
 * - Mapea campos principales y preserva/transforma datos razonablemente.
 * - INSERT ... ON DUPLICATE KEY UPDATE (no sobrescribe id).
 *
 * Uso: php migrar_clientes.php
 * Antes: haz backup de la BD destino.
 */

// CONFIG - ajusta según tu entorno
$cfg = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'Miguel#1960',
    'src_db' => 'smk',    // donde está clientes (4)
    'dst_db' => 'orion',  // donde está clientes (3)
    'table_src' => 'clientes',
    'table_dst' => 'clientes',
    'batch_size' => 500
];

function log_msg($msg) { echo date('[Y-m-d H:i:s] ') . $msg . PHP_EOL; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Conexiones
$src = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['src_db']);
$dst = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['dst_db']);

if ($src->connect_error) { die("Conexión origen fallida: " . $src->connect_error); }
if ($dst->connect_error) { die("Conexión destino fallida: " . $dst->connect_error); }

$src->set_charset('utf8mb4');
$dst->set_charset('utf8mb4');

try {
    log_msg("Inicio migración: {$cfg['src_db']}.{$cfg['table_src']} -> {$cfg['dst_db']}.{$cfg['table_dst']}");

    // Desactivar FK checks en la sesión destino
    $dst->query("SET SESSION FOREIGN_KEY_CHECKS=0");

    // Iniciar transacción en destino
    $dst->begin_transaction();

    // Leer desde origen (si volumen grande usar MYSQLI_USE_RESULT)
    $src_sql = "SELECT * FROM `{$cfg['table_src']}` ORDER BY `id` ASC";
    $src_res = $src->query($src_sql, MYSQLI_USE_RESULT);

    $total = 0; $inserted = 0; $updated = 0; $errors = 0;

    // Preparamos la plantilla INSERT en destino con las columnas que vamos a poblar
    // Observa que incluimos `id` para respetar el id origen.
    $dst_cols = [
        'id','codigo','razon_social','nombre_fantasia','tipo_doc','nro_doc','iva_cond','iibb_cond','iibb_nro',
        'inicio_act','email','telefono','celular','web','contacto_nombre','contacto_email','contacto_tel',
        'direccion','direccion2','localidad','provincia','pais','cp','moneda_preferida','servicio','condicion_venta',
        'plazo_pago_dias','tope_credito','obs','estado','created_at','updated_at'
    ];
    $cols_list = implode(',', array_map(function($c){ return '`'.$c.'`'; }, $dst_cols));
    $placeholders = implode(',', array_fill(0, count($dst_cols), '?'));

    // Construimos ON DUPLICATE KEY UPDATE (no actualizamos id)
    $updates = [];
    foreach ($dst_cols as $c) {
        if ($c === 'id') continue;
        $updates[] = "`$c` = VALUES(`$c`)";
    }
    $update_sql = ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);

    $insert_sql = "INSERT INTO `{$cfg['dst_db']}`.`{$cfg['table_dst']}` ($cols_list) VALUES ($placeholders) $update_sql";
    $stmt = $dst->prepare($insert_sql);
    if (! $stmt) throw new Exception("No se pudo preparar statement destino: " . $dst->error);

    // Para bind_param necesitamos tipos y referencias => construiremos por fila
    while ($row = $src_res->fetch_assoc()) {
        $total++;

        // --- MAPEOS y transformaciones desde origen ($row) hacia variables destino ---
        // Origen fields (según clientes (4).sql): id, ingreso, nombre, apellido, empresa, cuit, direccion, localidad,
        // pais, cp, telefono, movil, whatsapp, mail, mail2, proveedor, medio_pago, servicio, monto, aviso, detalles, concepto, activo

        // ID (preservar)
        $id = (isset($row['id']) ? (int)$row['id'] : null);

        // codigo: genere uno si no existe (por ejemplo 'C' + id). Puedes cambiar la lógica si prefieres otro código.
        $codigo = 'C' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

        // razon_social: preferir empresa, sino concatenar nombre + apellido
        $razon_social = '';
        if (!empty($row['empresa'])) {
            $razon_social = $row['empresa'];
        } else {
            $n = isset($row['nombre']) ? trim($row['nombre']) : '';
            $a = isset($row['apellido']) ? trim($row['apellido']) : '';
            $razon_social = trim($n . ' ' . $a);
            if ($razon_social === '') $razon_social = 'Cliente ' . $codigo;
        }

        // nombre_fantasia: usar empresa si existe, sino null
        $nombre_fantasia = (!empty($row['empresa']) ? $row['empresa'] : null);

        // tipo_doc / nro_doc: si cuit no vacío, consideramos CUIT; sino dejamos nro_doc vacío
        $cuit = isset($row['cuit']) ? trim($row['cuit']) : '';
        $tipo_doc = ($cuit !== '' ? 'CUIT' : 'DNI');
        $nro_doc = ($cuit !== '' ? $cuit : null);

        // iva_cond: dejar default 'CF' (consumidor final) si no se puede deducir
        $iva_cond = 'CF';

        // iibb_cond / iibb_nro - valores por defecto
        $iibb_cond = 'No inscripto';
        $iibb_nro = null;

        // inicio_act: usar 'ingreso' si válida, sino null
        $inicio_act = (isset($row['ingreso']) && $row['ingreso'] !== '0000-00-00' && $row['ingreso'] !== '' ? $row['ingreso'] : null);

        // emails
        $email = (!empty($row['mail']) ? $row['mail'] : ( !empty($row['mail2']) ? $row['mail2'] : null ));
        $contacto_email = null;

        // teléfonos
        $telefono = (!empty($row['telefono']) ? $row['telefono'] : null);
        // celular preferimos 'movil' y si empty usamos 'whatsapp'
        $celular = (!empty($row['movil']) ? $row['movil'] : ( !empty($row['whatsapp']) ? $row['whatsapp'] : null ));

        $n2 = isset($row['nombre']) ? trim($row['nombre']) : '';
        $a2 = isset($row['apellido']) ? trim($row['apellido']) : '';
        $contacto_nombre = trim($n2 . ' ' . $a2);
        
        $web = null;
        $contacto_tel = null;

        // direcciones y localidad
        $direccion = (!empty($row['direccion']) ? $row['direccion'] : null);
        $direccion2 = null;
        $localidad = (!empty($row['localidad']) ? $row['localidad'] : null);
        $provincia = null;
        // normalizar pais a formato Titlecase 'Argentina' si detectamos 'ARGENTINA' o similar
        $pais_src = isset($row['pais']) ? trim($row['pais']) : '';
        if ($pais_src !== '') {
            $pais = (strcasecmp($pais_src, 'ARGENTINA') === 0 ? 'Argentina' : $pais_src);
        } else {
            $pais = 'Argentina';
        }

        $cp = (!empty($row['cp']) ? $row['cp'] : null);

        // moneda preferida default ARG
        $moneda_preferida = 'ARG';

        // servicio: mapeamos directamente si existe (ten en cuenta unsigned int mismatch)
        $servicio = (isset($row['servicio']) && $row['servicio'] !== '' ? (int)$row['servicio'] : null);

        // condicion_venta default
        $condicion_venta = 'CONTADO';
        $plazo_pago_dias = 0;
        $tope_credito = null;

        // obs: pegamos detalles + concepto
        $obs_parts = [];
        if (!empty($row['detalles'])) $obs_parts[] = $row['detalles'];
        if (!empty($row['concepto'])) $obs_parts[] = 'Concepto: ' . $row['concepto'];
        $obs = count($obs_parts) ? implode("\n", $obs_parts) : null;

        // estado: en destino 1 = activo, 0 = inactivo
        $activo = isset($row['activo']) ? strtolower(trim($row['activo'])) : '';
        $estado = ($activo === 'si' ? 1 : 0);

        // created_at: usar inicio_act si existe, sino current timestamp (NULL dejará default current_timestamp en la tabla si la columna lo define)
        $created_at = $inicio_act; // puede ser null

        $updated_at = null;

        // --- Bind y execute ---
        // Note: prepare statement ya hecho; ahora bind params en el orden de $dst_cols
        $vals = [
            $id, $codigo, $razon_social, $nombre_fantasia, $tipo_doc, $nro_doc, $iva_cond, $iibb_cond, $iibb_nro,
            $inicio_act, $email, $telefono, $celular, $web, $contacto_nombre, $contacto_email, $contacto_tel,
            $direccion, $direccion2, $localidad, $provincia, $pais, $cp, $moneda_preferida, $servicio, $condicion_venta,
            $plazo_pago_dias, $tope_credito, $obs, $estado, $created_at, $updated_at
        ];

        // Calcular tipos para bind_param: i -> integer, d -> double, s -> string
        $types = '';
        foreach ($vals as $v) {
            if (is_int($v)) {
                $types .= 'i';
            } elseif (is_float($v) || is_double($v)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        // mysqli bind_param necesita referencias
        $bind_names = [];
        $bind_names[] = &$types;
        for ($i=0; $i<count($vals); $i++) {
            $bind_names[] = &$vals[$i];
        }

        // bind y execute
        try {
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
            $ok = $stmt->execute();
            if ($ok) {
                // affected_rows: 1 insert, 2 update with changed values, 0 duplicate with identical values
                $ar = $stmt->affected_rows;
                if ($ar == 1) $inserted++;
                elseif ($ar == 2) $updated++;
            } else {
                $errors++;
                log_msg("ERROR execute en fila $total (id_src=" . (isset($row['id']) ? $row['id'] : 'n/a') . "): " . $stmt->error);
            }
        } catch (Exception $ex) {
            $errors++;
            log_msg("EXCEPTION fila $total (id_src=" . (isset($row['id']) ? $row['id'] : 'n/a') . "): " . $ex->getMessage());
        }

        // Si quieres commits por lotes, descomenta/ajusta el bloque siguiente
        // if ($total % $cfg['batch_size'] === 0) {
        //     $dst->commit();
        //     $dst->begin_transaction();
        //     log_msg(\"Commit parcial en fila $total\");
        // }
    }

    // cerrar resultado no bufferizado
    $src_res->close();

    // Ajustar AUTO_INCREMENT en destino para evitar colisiones futuras
    $res = $dst->query("SELECT MAX(id) AS m FROM `{$cfg['dst_db']}`.`{$cfg['table_dst']}`");
    if ($res) {
        $r = $res->fetch_assoc();
        $max = (int)(isset($r['m']) ? $r['m'] : 0);
        $next = $max + 1;
        if ($next > 1) {
            if (! $dst->query("ALTER TABLE `{$cfg['dst_db']}`.`{$cfg['table_dst']}` AUTO_INCREMENT = $next")) {
                log_msg("Advertencia: no se pudo ajustar AUTO_INCREMENT: " . $dst->error);
            }
        }
        $res->close();
    }

    // Commit global
    $dst->commit();
    // Reactivar FK checks
    $dst->query("SET SESSION FOREIGN_KEY_CHECKS=1");

    log_msg("Migración completa. Procesados: $total. Insertados: $inserted. Actualizados: $updated. Errores: $errors");

} catch (Exception $e) {
    log_msg("ERROR general: " . $e->getMessage());
    // Intentar rollback
    if ($dst && $dst->connect_errno === 0) {
        try { $dst->rollback(); } catch (Exception $ex) {}
        $dst->query("SET SESSION FOREIGN_KEY_CHECKS=1");
    }
    exit(1);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if ($src) $src->close();
    if ($dst) $dst->close();
}
