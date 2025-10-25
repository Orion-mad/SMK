<?php
// /api/clientes/presupuestos/test_save_real.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST SAVE REAL ===\n\n";

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/codigo_helper.php';

$db = DB::get();

// Obtener un cliente activo
$q = $db->query("SELECT id FROM clientes WHERE estado = 1 LIMIT 1");
if ($q->num_rows === 0) {
    echo "ERROR: No hay clientes activos\n";
    exit;
}
$cliente = $q->fetch_assoc();
$cliente_id = (int)$cliente['id'];

echo "Cliente encontrado: ID $cliente_id\n\n";

// Simular datos del frontend
$input = [
    'id' => 0,
    'codigo' => '',
    'cliente_id' => $cliente_id,
    'titulo' => 'Presupuesto de Prueba',
    'fecha_emision' => date('Y-m-d'),
    'dias_validez' => 30,
    'fecha_vencimiento' => null,
    'estado' => 'borrador',
    'introduccion' => 'Texto de prueba',
    'condiciones' => null,
    'observaciones' => null,
    'notas_internas' => null,
    'moneda' => 'ARG',  // ✓ Correcto según ENUM
    'subtotal' => 1000.00,
    'descuento_porc' => 0,
    'descuento_monto' => 0,
    'iva_porc' => 21,
    'iva_monto' => 210,
    'total' => 1210,
    'tipo_cobro' => 'mensual',
    'forma_pago' => null,
    'orden' => 0,
    'activo' => 1,
    'items' => [
        [
            'orden' => 1,
            'tipo' => 'item',
            'descripcion' => 'Item de prueba',
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'subtotal' => 1000,
            'descuento_porc' => 0,
            'descuento_monto' => 0,
            'subtotal_con_desc' => 1000,
            'iva_porc' => 21,
            'iva_monto' => 210,
            'total' => 1210,
            'activo' => 1
        ]
    ]
];

echo "Datos de entrada preparados\n\n";

// Autogenerar código
echo "Generando código...\n";
lcars_autocodigo($db, 'cli_presupuestos', $input, 'codigo', 'id');
echo "Código generado: " . $input['codigo'] . "\n\n";

// Extraer variables
$id = (int)($input['id'] ?? 0);
$codigo = trim($input['codigo'] ?? '');
$titulo = trim($input['titulo'] ?? '');
$fecha_emision = $input['fecha_emision'];
$fecha_vencimiento = !empty($input['fecha_vencimiento']) ? $input['fecha_vencimiento'] : null;
$dias_validez = (int)$input['dias_validez'];
$estado = $input['estado'];
$introduccion = !empty($input['introduccion']) ? $input['introduccion'] : null;
$condiciones = !empty($input['condiciones']) ? $input['condiciones'] : null;
$observaciones = !empty($input['observaciones']) ? $input['observaciones'] : null;
$notas_internas = !empty($input['notas_internas']) ? $input['notas_internas'] : null;
$moneda = $input['moneda'];
$subtotal = (float)$input['subtotal'];
$descuento_porc = (float)$input['descuento_porc'];
$descuento_monto = (float)$input['descuento_monto'];
$iva_porc = (float)$input['iva_porc'];
$iva_monto = (float)$input['iva_monto'];
$total = (float)$input['total'];
$tipo_cobro = $input['tipo_cobro'];
$forma_pago = !empty($input['forma_pago']) ? $input['forma_pago'] : null;
$orden = (int)$input['orden'];
$activo = (int)$input['activo'];

echo "Verificando código único...\n";
if (lcars_codigo_existe($db, 'cli_presupuestos', $codigo, $id)) {
    echo "ERROR: El código ya existe\n";
    exit;
}
echo "Código disponible\n\n";

echo "Iniciando transacción...\n";
$db->begin_transaction();

try {
    echo "Construyendo INSERT dinámico...\n";
    
    // Construir arrays de columnas y valores
    $cols = ['codigo', 'cliente_id', 'fecha_emision', 'dias_validez', 'estado', 'titulo', 
             'moneda', 'subtotal', 'descuento_porc', 'descuento_monto', 'iva_porc', 
             'iva_monto', 'total', 'tipo_cobro', 'orden', 'activo'];
    $types = 'sisssissdddddsii';
    $vals = [$codigo, $cliente_id, $fecha_emision, $dias_validez, $estado, $titulo, 
             $moneda, $subtotal, $descuento_porc, $descuento_monto, $iva_porc, 
             $iva_monto, $total, $tipo_cobro, $orden, $activo];
    
    // Agregar campos opcionales solo si tienen valor
    if ($fecha_vencimiento !== null) {
        $cols[] = 'fecha_vencimiento';
        $types .= 's';
        $vals[] = $fecha_vencimiento;
    }
    if ($introduccion !== null) {
        $cols[] = 'introduccion';
        $types .= 's';
        $vals[] = $introduccion;
    }
    if ($condiciones !== null) {
        $cols[] = 'condiciones';
        $types .= 's';
        $vals[] = $condiciones;
    }
    if ($observaciones !== null) {
        $cols[] = 'observaciones';
        $types .= 's';
        $vals[] = $observaciones;
    }
    if ($notas_internas !== null) {
        $cols[] = 'notas_internas';
        $types .= 's';
        $vals[] = $notas_internas;
    }
    if ($forma_pago !== null) {
        $cols[] = 'forma_pago';
        $types .= 's';
        $vals[] = $forma_pago;
    }
    
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colsList = implode(',', $cols);
    
    echo "Columnas: $colsList\n";
    echo "Tipos: $types\n";
    echo "Valores: " . count($vals) . "\n\n";
    
    echo "Preparando INSERT...\n";
    $sql = "INSERT INTO cli_presupuestos ($colsList) VALUES ($placeholders)";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        echo "ERROR prepare: " . $db->error . "\n";
        exit;
    }
    
    echo "Binding parameters...\n";
    $stmt->bind_param($types, ...$vals);
    
    echo "Ejecutando INSERT...\n";
    if (!$stmt->execute()) {
        echo "ERROR execute: " . $stmt->error . "\n";
        $db->rollback();
        exit;
    }
    
    $id = $db->insert_id;
    echo "✓ Presupuesto insertado, ID: $id\n\n";
    $stmt->close();
    
    // Insertar items
    echo "Insertando items...\n";
    $stmtItem = $db->prepare("
        INSERT INTO cli_presupuestos_items (
          presupuesto_id, orden, tipo, descripcion, descripcion_corta,
          servicio_id, cantidad, unidad, precio_unitario, subtotal,
          descuento_porc, descuento_monto, subtotal_con_desc,
          iva_porc, iva_monto, total, activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmtItem) {
        echo "ERROR prepare items: " . $db->error . "\n";
        $db->rollback();
        exit;
    }
    
    foreach ($input['items'] as $idx => $item) {
        $itemOrden = (int)($item['orden'] ?? $idx + 1);
        $itemTipo = $item['tipo'] ?? 'item';
        $itemDesc = $item['descripcion'] ?? '';
        $itemDescCorta = $item['descripcion_corta'] ?? null;
        $itemServicioId = !empty($item['servicio_id']) ? (int)$item['servicio_id'] : null;
        $itemCantidad = (float)($item['cantidad'] ?? 1);
        $itemUnidad = $item['unidad'] ?? 'unidad';
        $itemPrecio = (float)($item['precio_unitario'] ?? 0);
        $itemSubtotal = (float)($item['subtotal'] ?? 0);
        $itemDescPorc = (float)($item['descuento_porc'] ?? 0);
        $itemDescMonto = (float)($item['descuento_monto'] ?? 0);
        $itemSubtotalDesc = (float)($item['subtotal_con_desc'] ?? 0);
        $itemIvaPorc = (float)($item['iva_porc'] ?? 21);
        $itemIvaMonto = (float)($item['iva_monto'] ?? 0);
        $itemTotal = (float)($item['total'] ?? 0);
        $itemActivo = isset($item['activo']) ? (int)$item['activo'] : 1;

        echo "  - Item $itemOrden: $itemDesc\n";
        
        $stmtItem->bind_param(
          'iisssidsdddddddii',
          $id, $itemOrden, $itemTipo, $itemDesc, $itemDescCorta,
          $itemServicioId, $itemCantidad, $itemUnidad, $itemPrecio, $itemSubtotal,
          $itemDescPorc, $itemDescMonto, $itemSubtotalDesc,
          $itemIvaPorc, $itemIvaMonto, $itemTotal, $itemActivo
        );
        
        if (!$stmtItem->execute()) {
            echo "ERROR execute item: " . $stmtItem->error . "\n";
            $db->rollback();
            exit;
        }
    }
    
    $stmtItem->close();
    echo "✓ Items insertados\n\n";
    
    $db->commit();
    echo "✓ Transacción completada\n\n";
    
    // Limpiar test
    echo "Limpiando datos de prueba...\n";
    $db->query("DELETE FROM cli_presupuestos_items WHERE presupuesto_id = $id");
    $db->query("DELETE FROM cli_presupuestos WHERE id = $id");
    echo "✓ Test limpiado\n\n";
    
    echo "=== TEST EXITOSO ===\n";
    echo "El save.php debería funcionar correctamente.\n";
    
} catch (Throwable $e) {
    $db->rollback();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}