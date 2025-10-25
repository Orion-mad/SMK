<?php
// /api/contable/trabajos/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $db = DB::get();
  
  // Construir query - usar la vista v_trabajos_resumen que ya tiene los cálculos
  $where = ['1=1'];
  $params = [];
  $types = '';

  // Filtro por estado
  if (!empty($_GET['estado'])) {
    $where[] = 'estado = ?';
    $params[] = $_GET['estado'];
    $types .= 's';
  } else {
    // Por defecto, mostrar solo finalizados y entregados
    $where[] = 'estado IN ("en_proceso", "homologacion")';
  }

  // Filtro por búsqueda
  if (!empty($_GET['q'])) {
    $q = '%' . $_GET['q'] . '%';
    $where[] = '(codigo LIKE ? OR nombre LIKE ? OR cliente_nombre LIKE ? OR cliente_razon_social LIKE ?)';
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $types .= 'ssss';
  }

  $whereClause = implode(' AND ', $where);

  $sql = "SELECT 
            id,
            codigo,
            nombre,
            estado,
            prioridad,
            cliente_nombre,
            cliente_razon_social,
            servicio_nombre,
            fecha_ingreso,
            fecha_entrega_estimada,
            fecha_entrega_real,
            dias_para_entrega,
            total,
            moneda,
            total_pagado,
            saldo,
            porc_saldo,
            requiere_homologacion,
            homologacion_estado,
            creado_en,
            actualizado_en
          FROM v_trabajos_resumen
          WHERE {$whereClause}
          ORDER BY saldo DESC, fecha_ingreso DESC";

  $stmt = $db->prepare($sql);
  
  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  
  $stmt->execute();
  $result = $stmt->get_result();
  
  $data = [];
  while ($row = $result->fetch_assoc()) {
    // Necesitamos obtener también datos del cliente para el formulario
    $clienteQuery = $db->prepare("SELECT tipo_doc, nro_doc FROM clientes WHERE 
                                   razon_social = ? OR contacto_nombre = ? LIMIT 1");
    $clienteQuery->bind_param('ss', $row['cliente_razon_social'], $row['cliente_nombre']);
    $clienteQuery->execute();
    $clienteData = $clienteQuery->get_result()->fetch_assoc();
    $clienteQuery->close();

    $data[] = [
      'id'                    => (int)$row['id'],
      'codigo'                => $row['codigo'],
      'nombre'                => $row['nombre'],
      'estado'                => $row['estado'],
      'prioridad'             => $row['prioridad'],
      'cliente_nombre'        => $row['cliente_nombre'],
      'cliente_razon_social'  => $row['cliente_razon_social'],
      'cliente_tipo_doc'      => $clienteData['tipo_doc'] ?? null,
      'cliente_doc'           => $clienteData['nro_doc'] ?? null,
      'servicio_nombre'       => $row['servicio_nombre'],
      'fecha_ingreso'         => $row['fecha_ingreso'],
      'fecha_entrega_estimada'=> $row['fecha_entrega_estimada'],
      'fecha_entrega_real'    => $row['fecha_entrega_real'],
      'dias_para_entrega'     => $row['dias_para_entrega'],
      'total'                 => (float)$row['total'],
      'moneda'                => $row['moneda'],
      'total_pagado'          => (float)$row['total_pagado'],
      'saldo'                 => (float)$row['saldo'],
      'porc_saldo'            => (float)$row['porc_saldo'],
      'requiere_homologacion' => (bool)$row['requiere_homologacion'],
      'homologacion_estado'   => $row['homologacion_estado'],
      'creado_en'             => $row['creado_en'],
      'actualizado_en'        => $row['actualizado_en'],
    ];
  }

  http_response_code(200);
  echo json_encode(['items' => $data,'query'=>$whereClause], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[contable/trabajos/list] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}