<?php
// /api/parameters/conceptos/entidades/search.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';

try {
  $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  
  if (!$tipo || !in_array($tipo, ['cliente', 'proveedor', 'empleado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'tipo inválido o búsqueda no soportada']);
    exit;
  }

  if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
  }

  $db = DB::get();
  $data = [];
  $searchTerm = '%' . $db->real_escape_string($q) . '%';
  
  switch ($tipo) {
    case 'cliente':
      $stmt = $db->prepare("
        SELECT 
          id, 
          CONCAT(nombre, ' ', apellido) AS nombre,
          email
        FROM clientes 
        WHERE (nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)
          AND activo = 1 
        ORDER BY apellido, nombre
        LIMIT 20
      ");
      $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $data[] = [
          'id' => (int)$row['id'],
          'nombre' => $row['nombre'],
          'email' => $row['email'],
          'tipo' => 'cliente'
        ];
      }
      break;
      
    case 'proveedor':
      $stmt = $db->prepare("
        SELECT 
          id, 
          razon_social AS nombre,
          cuit
        FROM proveedores 
        WHERE (razon_social LIKE ? OR cuit LIKE ?)
          AND activo = 1 
        ORDER BY razon_social
        LIMIT 20
      ");
      $stmt->bind_param('ss', $searchTerm, $searchTerm);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $data[] = [
          'id' => (int)$row['id'],
          'nombre' => $row['nombre'],
          'cuit' => $row['cuit'],
          'tipo' => 'proveedor'
        ];
      }
      break;
      
    case 'empleado':
      $stmt = $db->prepare("
        SELECT 
          id, 
          CONCAT(nombre, ' ', apellido) AS nombre,
          legajo
        FROM empleados 
        WHERE (nombre LIKE ? OR apellido LIKE ? OR legajo LIKE ?)
          AND activo = 1 
        ORDER BY apellido, nombre
        LIMIT 20
      ");
      $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $data[] = [
          'id' => (int)$row['id'],
          'nombre' => $row['nombre'],
          'legajo' => $row['legajo'],
          'tipo' => 'empleado'
        ];
      }
      break;
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[conceptos/entidades/search] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}