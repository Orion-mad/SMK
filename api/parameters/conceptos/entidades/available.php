<?php
// /api/parameters/conceptos/entidades/available.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../inc/conect.php';

try {
  $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
  
  if (!$tipo || !in_array($tipo, ['cliente', 'proveedor', 'empleado', 'otro'])) {
    http_response_code(400);
    echo json_encode(['error' => 'tipo inválido', 'valid' => ['cliente', 'proveedor', 'empleado', 'otro']]);
    exit;
  }

  $db = DB::get();
  $data = [];
  
  switch ($tipo) {
    case 'cliente':
      // Buscar en tabla clientes
      $result = $db->query("
        SELECT 
          id, 
          CONCAT(nombre, ' ', apellido) AS nombre,
          email,
          activo
        FROM clientes 
        WHERE activo = 1 
        ORDER BY apellido, nombre
        LIMIT 100
      ");
      
      if ($result) {
        while ($row = $result->fetch_assoc()) {
          $data[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'email' => $row['email'],
            'tipo' => 'cliente'
          ];
        }
      }
      break;
      
    case 'proveedor':
      // Buscar en tabla proveedores
      $result = $db->query("
        SELECT 
          id, 
          razon_social AS nombre,
          cuit,
          activo
        FROM proveedores 
        WHERE activo = 1