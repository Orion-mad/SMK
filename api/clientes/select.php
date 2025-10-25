<?php
// /api/clientes/select.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';

try {
  $db = DB::get();

  $sql = "SELECT 
            id, 
            codigo, 
            contacto_nombre, 
            razon_social,
            tipo_doc,
            nro_doc,
            email,
            telefono
          FROM clientes WHERE estado = 1
          ORDER BY 
            CASE 
              WHEN razon_social IS NOT NULL AND razon_social != '' THEN razon_social
              ELSE contacto_nombre
            END ASC";

  $result = $db->query($sql);
  $data = [];

  while ($row = $result->fetch_assoc()) {
    // Nombre para mostrar: priorizar razón social, sino contacto
    $nombre_display = !empty($row['razon_social']) 
      ? $row['razon_social'] 
      : $row['contacto_nombre'];

    $data[] = [
      'id'              => (int)$row['id'],
      'codigo'          => $row['codigo'],
      'contacto_nombre' => $row['contacto_nombre'],
      'razon_social'    => $row['razon_social'],
      'nombre_display'  => $nombre_display, // Para usar en combos
      'tipo_doc'        => $row['tipo_doc'] ?? null,
      'nro_doc'         => $row['nro_doc'] ?? null,
      'email'           => $row['email'],
      'telefono'        => $row['telefono']
    ];
  }

  http_response_code(200);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[clientes/select] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}