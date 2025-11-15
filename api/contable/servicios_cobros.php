<?php
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../cotizacion/funciones_cotizacion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

verificarAutenticacion();

$database = new Database();
$db = $database->getConnection();
$metodo = $_SERVER['REQUEST_METHOD'];
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

try {
    switch ($metodo) {
        case 'GET':
            if ($accion === 'servicios_activos') {
                obtenerServiciosActivos($db);
            } elseif ($accion === 'detalle_servicio') {
                obtenerDetalleServicio($db);
            } elseif ($accion === 'historial_cobros') {
                obtenerHistorialCobros($db);
            } else {
                throw new Exception('Acción no válida');
            }
            break;
            
        case 'POST':
            if ($accion === 'registrar_cobro') {
                registrarCobro($db);
            } elseif ($accion === 'facturar_arca') {
                facturarConArca($db);
            } elseif ($accion === 'generar_proforma') {
                generarProforma($db);
            } else {
                throw new Exception('Acción no válida');
            }
            break;
            
        case 'PUT':
            if ($accion === 'actualizar_cobro') {
                actualizarCobro($db);
            } else {
                throw new Exception('Acción no válida');
            }
            break;
            
        case 'DELETE':
            if ($accion === 'eliminar_cobro') {
                eliminarCobro($db);
            } else {
                throw new Exception('Acción no válida');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function obtenerServiciosActivos($db) {
    $query = "SELECT 
                s.id,
                s.cliente_id,
                c.nombre as cliente_nombre,
                c.razon_social,
                c.cuit,
                s.nombre_servicio,
                s.valor_dolares,
                s.tipo_cobro,
                s.fecha_inicio,
                s.fecha_vencimiento,
                s.estado,
                s.descripcion,
                COUNT(co.id) as cobros_realizados,
                MAX(co.fecha) as ultimo_cobro
              FROM servicios s
              INNER JOIN clientes c ON s.cliente_id = c.id
              LEFT JOIN cnt_cobros co ON s.id = co.servicio_id
              WHERE s.estado = 'activo'
              GROUP BY s.id, s.cliente_id, c.nombre, c.razon_social, c.cuit, 
                       s.nombre_servicio, s.valor_dolares, s.tipo_cobro, 
                       s.fecha_inicio, s.fecha_vencimiento, s.estado, s.descripcion
              ORDER BY c.nombre, s.nombre_servicio";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $servicios
    ]);
}

function obtenerDetalleServicio($db) {
    $servicio_id = isset($_GET['servicio_id']) ? $_GET['servicio_id'] : null;
    
    if (!$servicio_id) {
        throw new Exception('ID de servicio requerido');
    }
    
    $query = "SELECT 
                s.*,
                c.nombre as cliente_nombre,
                c.razon_social,
                c.cuit,
                c.email,
                c.telefono,
                c.direccion
              FROM servicios s
              INNER JOIN clientes c ON s.cliente_id = c.id
              WHERE s.id = :servicio_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':servicio_id', $servicio_id);
    $stmt->execute();
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicio) {
        throw new Exception('Servicio no encontrado');
    }
    
    // Obtener cotización actual
    $cotizacion = obtenerCotizacionActual($db);
    $servicio['cotizacion_actual'] = $cotizacion;
    $servicio['monto_pesos'] = $servicio['valor_dolares'] * $cotizacion;
    
    echo json_encode([
        'success' => true,
        'data' => $servicio
    ]);
}

function registrarCobro($db) {
    $datos = json_decode(file_get_contents('php://input'), true);
    
    // Validaciones
    $campos_requeridos = ['servicio_id', 'cliente_id', 'forma_pago', 'fecha'];
    foreach ($campos_requeridos as $campo) {
        if (!isset($datos[$campo]) || empty($datos[$campo])) {
            throw new Exception("El campo {$campo} es requerido");
        }
    }
    
    $db->beginTransaction();
    
    try {
        // Obtener información del servicio
        $query_servicio = "SELECT s.*, c.nombre as cliente_nombre 
                          FROM servicios s
                          INNER JOIN clientes c ON s.cliente_id = c.id
                          WHERE s.id = :servicio_id";
        $stmt = $db->prepare($query_servicio);
        $stmt->bindParam(':servicio_id', $datos['servicio_id']);
        $stmt->execute();
        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$servicio) {
            throw new Exception('Servicio no encontrado');
        }
        
        // Obtener cotización del día
        $cotizacion = obtenerCotizacionActual($db);
        $monto_pesos = $servicio['valor_dolares'] * $cotizacion;
        
        // Generar observaciones con el detalle del dólar
        $observaciones_auto = sprintf(
            "Monto en dólares: USD %.2f - Cotización del día: $%.2f x dólar",
            $servicio['valor_dolares'],
            $cotizacion
        );
        
        $observaciones_completas = $observaciones_auto;
        if (isset($datos['observaciones']) && !empty($datos['observaciones'])) {
            $observaciones_completas .= "\n" . $datos['observaciones'];
        }
        
        // Insertar el cobro
        $query = "INSERT INTO cnt_cobros (
                    fecha,
                    cliente_id,
                    servicio_id,
                    concepto,
                    monto_pesos,
                    monto_dolares,
                    cotizacion_dolar,
                    tipo_cobro,
                    forma_pago,
                    estado,
                    observaciones,
                    usuario_registro
                  ) VALUES (
                    :fecha,
                    :cliente_id,
                    :servicio_id,
                    :concepto,
                    :monto_pesos,
                    :monto_dolares,
                    :cotizacion_dolar,
                    :tipo_cobro,
                    :forma_pago,
                    :estado,
                    :observaciones,
                    :usuario_registro
                  )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha', $datos['fecha']);
        $stmt->bindParam(':cliente_id', $datos['cliente_id']);
        $stmt->bindParam(':servicio_id', $datos['servicio_id']);
        $concepto = "Servicio: " . $servicio['nombre_servicio'];
        $stmt->bindParam(':concepto', $concepto);
        $stmt->bindParam(':monto_pesos', $monto_pesos);
        $stmt->bindParam(':monto_dolares', $servicio['valor_dolares']);
        $stmt->bindParam(':cotizacion_dolar', $cotizacion);
        $stmt->bindParam(':tipo_cobro', $servicio['tipo_cobro']);
        $stmt->bindParam(':forma_pago', $datos['forma_pago']);
        $estado = isset($datos['estado']) ? $datos['estado'] : 'pendiente';
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':observaciones', $observaciones_completas);
        $usuario_id = $_SESSION['usuario_id'];
        $stmt->bindParam(':usuario_registro', $usuario_id);
        
        $stmt->execute();
        $cobro_id = $db->lastInsertId();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cobro registrado exitosamente',
            'data' => [
                'cobro_id' => $cobro_id,
                'monto_pesos' => $monto_pesos,
                'cotizacion' => $cotizacion
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function facturarConArca($db) {
    $datos = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($datos['cobro_id'])) {
        throw new Exception('ID de cobro requerido');
    }
    
    // TODO: Implementar integración con ARCA
    // Por ahora solo actualizamos el estado
    
    $query = "UPDATE cnt_cobros 
              SET estado = 'facturado',
                  fecha_actualizacion = CURRENT_TIMESTAMP
              WHERE id = :cobro_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':cobro_id', $datos['cobro_id']);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Facturación con ARCA programada (pendiente de implementación)'
    ]);
}

function generarProforma($db) {
    $datos = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($datos['cobro_id'])) {
        throw new Exception('ID de cobro requerido');
    }
    
    // Obtener datos del cobro
    $query = "SELECT 
                co.*,
                c.nombre as cliente_nombre,
                c.razon_social,
                c.cuit,
                c.direccion,
                s.nombre_servicio
              FROM cnt_cobros co
              INNER JOIN clientes c ON co.cliente_id = c.id
              LEFT JOIN servicios s ON co.servicio_id = s.id
              WHERE co.id = :cobro_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':cobro_id', $datos['cobro_id']);
    $stmt->execute();
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        throw new Exception('Cobro no encontrado');
    }
    
    // TODO: Generar PDF de proforma
    // Por ahora retornamos los datos
    
    echo json_encode([
        'success' => true,
        'message' => 'Proforma generada (pendiente generar PDF)',
        'data' => $cobro
    ]);
}

function obtenerHistorialCobros($db) {
    $servicio_id = isset($_GET['servicio_id']) ? $_GET['servicio_id'] : null;
    $cliente_id = isset($_GET['cliente_id']) ? $_GET['cliente_id'] : null;
    
    $query = "SELECT 
                co.*,
                c.nombre as cliente_nombre,
                s.nombre_servicio,
                u.nombre as usuario_nombre
              FROM cnt_cobros co
              INNER JOIN clientes c ON co.cliente_id = c.id
              LEFT JOIN servicios s ON co.servicio_id = s.id
              LEFT JOIN usuarios u ON co.usuario_registro = u.id
              WHERE 1=1";
    
    if ($servicio_id) {
        $query .= " AND co.servicio_id = :servicio_id";
    }
    
    if ($cliente_id) {
        $query .= " AND co.cliente_id = :cliente_id";
    }
    
    $query .= " ORDER BY co.fecha DESC, co.fecha_registro DESC";
    
    $stmt = $db->prepare($query);
    
    if ($servicio_id) {
        $stmt->bindParam(':servicio_id', $servicio_id);
    }
    
    if ($cliente_id) {
        $stmt->bindParam(':cliente_id', $cliente_id);
    }
    
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $cobros
    ]);
}

function actualizarCobro($db) {
    $datos = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($datos['id'])) {
        throw new Exception('ID de cobro requerido');
    }
    
    $campos_actualizables = [
        'fecha', 'forma_pago', 'estado', 'numero_factura', 
        'cae', 'vencimiento_cae', 'observaciones'
    ];
    
    $set_clauses = [];
    $parametros = [':id' => $datos['id']];
    
    foreach ($campos_actualizables as $campo) {
        if (isset($datos[$campo])) {
            $set_clauses[] = "{$campo} = :{$campo}";
            $parametros[":{$campo}"] = $datos[$campo];
        }
    }
    
    if (empty($set_clauses)) {
        throw new Exception('No hay campos para actualizar');
    }
    
    $query = "UPDATE cnt_cobros SET " . implode(', ', $set_clauses) . " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute($parametros);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cobro actualizado exitosamente'
    ]);
}

function eliminarCobro($db) {
    $cobro_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$cobro_id) {
        throw new Exception('ID de cobro requerido');
    }
    
    // Verificar que el cobro no esté facturado
    $query_check = "SELECT estado FROM cnt_cobros WHERE id = :id";
    $stmt = $db->prepare($query_check);
    $stmt->bindParam(':id', $cobro_id);
    $stmt->execute();
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        throw new Exception('Cobro no encontrado');
    }
    
    if ($cobro['estado'] === 'facturado') {
        throw new Exception('No se puede eliminar un cobro facturado');
    }
    
    $query = "DELETE FROM cnt_cobros WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $cobro_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cobro eliminado exitosamente'
    ]);
}