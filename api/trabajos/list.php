<?php
// /api/trabajos/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/conect.php';
require_once __DIR__ . '/../core/list_helper.php';

try {
  $db = DB::get();
  
  // Preparar parámetros para filtros especiales que lcars_list no maneja
  $customParams = $_GET;
  
  // Filtro de saldo pendiente: lo manejamos manualmente
  $hasSaldoFilter = isset($_GET['saldo_pendiente']) && $_GET['saldo_pendiente'] == '1';
  
  // Filtros de fecha: los manejamos manualmente
  $fechaDesde = !empty($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
  $fechaHasta = !empty($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
  
  // Remover del array para que lcars_list no los procese
  unset($customParams['saldo_pendiente']);
  unset($customParams['fecha_desde']);
  unset($customParams['fecha_hasta']);
  /*
  $cfg = [
    'table' => 'prm_trabajos t 
                INNER JOIN clientes c ON c.id = t.cliente_id
                LEFT JOIN prm_servicios s ON s.id = t.servicio_id
                LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id',
    'select' => [
      'id'                    => 't.id',
      'codigo'                => 't.codigo',
      'nombre'                => 't.nombre',
      'descripcion'           => 't.descripcion',
      'cliente_id'            => 't.cliente_id',
      'cliente_nombre'        => 'c.contacto_nombre',
      'cliente_razon_social'  => 'c.razon_social',
      'presupuesto_id'        => 't.presupuesto_id',
      'presupuesto_codigo'    => 'p.codigo',
      'servicio_id'           => 't.servicio_id',
      'servicio_nombre'       => 's.nombre',
      'fecha_ingreso'         => 't.fecha_ingreso',
      'fecha_entrega_estimada'=> 't.fecha_entrega_estimada',
      'fecha_entrega_real'    => 't.fecha_entrega_real',
      'estado'                => 't.estado',
      'prioridad'             => 't.prioridad',
      'total'                 => 't.total',
      'moneda'                => 't.moneda',
      'medio_pago'            => 't.medio_pago',
      'saldo'                 => 't.saldo',
      'requiere_homologacion' => 't.requiere_homologacion',
      'homologacion_estado'   => 't.homologacion_estado',
      'orden'                 => 't.orden',
      'creado_en'             => 't.creado_en',
      'actualizado_en'        => 't.actualizado_en',
    ],
    'orderable' => ['id', 'codigo', 'nombre', 'fecha_ingreso', 'fecha_entrega_estimada', 'total', 'saldo', 'orden'],
    'default_order' => ['fecha_ingreso' => 'DESC', 'id' => 'DESC'],
    'searchable' => ['codigo', 'nombre', 'cliente_nombre', 'cliente_razon_social', 'servicio_nombre'],
    'numeric' => [
      'id'         => 'int',
      'cliente_id' => 'int',
      'servicio_id'=> 'int',
      'presupuesto_id' => 'int',
      'total'      => 'float',
      'saldo'      => 'float',
      'orden'      => 'int',
      'requiere_homologacion' => 'int',
    ],
    'filters' => [
      'estado' => [
        'col' => 't.estado',
        'type' => 'str',
        'in' => ['pendiente','en_proceso','homologacion','finalizado','entregado','cancelado']
      ],
      'prioridad' => [
        'col' => 't.prioridad',
        'type' => 'str',
        'in' => ['baja','normal','alta','urgente']
      ],
      'cliente_id' => [
        'col' => 't.cliente_id',
        'type' => 'int'
      ],
      'servicio_id' => [
        'col' => 't.servicio_id',
        'type' => 'int'
      ],
      'requiere_homologacion' => [
        'col' => 't.requiere_homologacion',
        'type' => 'int',
        'in' => [0, 1]
      ],
    ],
    'per_page' => 50,
  ];

  // Si tenemos filtros custom, hacer query manual
  if ($hasSaldoFilter || $fechaDesde || $fechaHasta) {
    // Construir WHERE manual para filtros custom
    $where = ['1=1'];
    $params = [];
    $types = '';
    
    // Agregar filtros básicos procesados
    if (!empty($customParams['estado'])) {
      $allowed = ['pendiente','en_proceso','homologacion','finalizado','entregado','cancelado'];
      if (in_array($customParams['estado'], $allowed)) {
        $where[] = 't.estado = ?';
        $params[] = $customParams['estado'];
        $types .= 's';
      }
    }
    
    if (!empty($customParams['prioridad'])) {
      $allowed = ['baja','normal','alta','urgente'];
      if (in_array($customParams['prioridad'], $allowed)) {
        $where[] = 't.prioridad = ?';
        $params[] = $customParams['prioridad'];
        $types .= 's';
      }
    }
    
    if (!empty($customParams['cliente_id'])) {
      $where[] = 't.cliente_id = ?';
      $params[] = (int)$customParams['cliente_id'];
      $types .= 'i';
    }
    
    if (!empty($customParams['servicio_id'])) {
      $where[] = 't.servicio_id = ?';
      $params[] = (int)$customParams['servicio_id'];
      $types .= 'i';
    }
    
    // Filtros custom
    if ($hasSaldoFilter) {
      $where[] = 't.saldo > 0';
    }
    
    if ($fechaDesde) {
      $where[] = 't.fecha_ingreso >= ?';
      $params[] = $fechaDesde;
      $types .= 's';
    }
    
    if ($fechaHasta) {
      $where[] = 't.fecha_ingreso <= ?';
      $params[] = $fechaHasta;
      $types .= 's';
    }
    
    // Búsqueda
    if (!empty($customParams['q'])) {
      $where[] = "(t.codigo LIKE ? OR t.nombre LIKE ? OR c.nombre LIKE ? OR c.razon_social LIKE ? OR s.nombre LIKE ?)";
      $searchTerm = '%' . $customParams['q'] . '%';
      $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
      $types .= 'sssss';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Ordenamiento
    $orderBy = 't.fecha_ingreso DESC, t.id DESC';
    if (!empty($customParams['order']) && in_array($customParams['order'], $cfg['orderable'])) {
      $sortDir = (!empty($customParams['dir']) && strtoupper($customParams['dir']) === 'ASC') ? 'ASC' : 'DESC';
      $orderBy = "t.{$customParams['order']} {$sortDir}";
    }
    
    // Paginación
    $page = isset($customParams['page']) ? max(1, (int)$customParams['page']) : 1;
    $perPage = isset($customParams['per_page']) ? min(100, max(1, (int)$customParams['per_page'])) : 50;
    $offset = ($page - 1) * $perPage;
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total 
                 FROM prm_trabajos t 
                 INNER JOIN clientes c ON c.id = t.cliente_id
                 LEFT JOIN prm_servicios s ON s.id = t.servicio_id
                 LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id
                 WHERE {$whereClause}";
    
    if ($params) {
      $stmtCount = $db->prepare($countSql);
      $stmtCount->bind_param($types, ...$params);
      $stmtCount->execute();
      $totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
    } else {
      $totalRows = $db->query($countSql)->fetch_assoc()['total'];
    }
    
    // Query principal
    $selectCols = implode(', ', array_map(function($alias, $expr) {
      return "$expr AS $alias";
    }, array_keys($cfg['select']), $cfg['select']));
    
    $sql = "SELECT {$selectCols}
            FROM {$cfg['table']}
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($sql);
    if ($params) {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
      // Aplicar conversiones numéricas
      foreach ($cfg['numeric'] as $k => $t) {
        if (isset($row[$k])) {
          if ($t === 'int') $row[$k] = (int)$row[$k];
          if ($t === 'float') $row[$k] = (float)$row[$k];
        }
      }
      $items[] = $row;
    }
    
    $totalPages = ceil($totalRows / $perPage);
    
    $result = [
      'items' => $items,
      'meta' => [
        'total' => (int)$totalRows,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $totalPages
      ]
    ];
    
  } else {
    // Usar lcars_list para filtros simples
    $result = lcars_list($cfg, $customParams);
  }
  
  // Agregar cálculos adicionales a todos los items
  foreach ($result['items'] as &$item) {
    // Calcular días para entrega
    if ($item['fecha_entrega_estimada']) {
      $diff = strtotime($item['fecha_entrega_estimada']) - time();
      $item['dias_para_entrega'] = floor($diff / (60 * 60 * 24));
    } else {
      $item['dias_para_entrega'] = null;
    }
    
    // Porcentaje de saldo
    if ($item['total'] > 0) {
      $item['porc_saldo'] = round(($item['saldo'] / $item['total']) * 100, 2);
    } else {
      $item['porc_saldo'] = 0;
    }
    
    // Total pagado
    $item['total_pagado'] = $item['total'] - $item['saldo'];
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);
*/
  
  // Construir la query base
  $where = ['1=1'];
  $params = [];
  $types = '';
  
  // Filtros
  if (!empty($_GET['estado'])) {
    $allowed = ['pendiente','en_proceso','homologacion','finalizado','entregado','cancelado'];
    if (in_array($_GET['estado'], $allowed)) {
      $where[] = 't.estado = ?';
      $params[] = $_GET['estado'];
      $types .= 's';
    }
  }
  
  if (!empty($_GET['prioridad'])) {
    $allowed = ['baja','normal','alta','urgente'];
    if (in_array($_GET['prioridad'], $allowed)) {
      $where[] = 't.prioridad = ?';
      $params[] = $_GET['prioridad'];
      $types .= 's';
    }
  }
  
  if (!empty($_GET['cliente_id'])) {
    $where[] = 't.cliente_id = ?';
    $params[] = (int)$_GET['cliente_id'];
    $types .= 'i';
  }
  
  if (!empty($_GET['servicio_id'])) {
    $where[] = 't.servicio_id = ?';
    $params[] = (int)$_GET['servicio_id'];
    $types .= 'i';
  }
  
  if (isset($_GET['saldo_pendiente']) && $_GET['saldo_pendiente'] == '1') {
    $where[] = 't.saldo > 0';
  }
  
  if (!empty($_GET['fecha_desde'])) {
    $where[] = 't.fecha_ingreso >= ?';
    $params[] = $_GET['fecha_desde'];
    $types .= 's';
  }
  
  if (!empty($_GET['fecha_hasta'])) {
    $where[] = 't.fecha_ingreso <= ?';
    $params[] = $_GET['fecha_hasta'];
    $types .= 's';
  }
  
  // Búsqueda
  if (!empty($_GET['q'])) {
    $where[] = "(t.codigo LIKE ? OR t.nombre LIKE ? OR c.nombre LIKE ? OR c.razon_social LIKE ? OR s.nombre LIKE ?)";
    $searchTerm = '%' . $_GET['q'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sssss';
  }
  
  $whereClause = implode(' AND ', $where);
  
  // Ordenamiento
  $orderBy = 't.fecha_ingreso DESC, t.id DESC';
  if (!empty($_GET['sort'])) {
    $allowedSort = ['id', 'codigo', 'nombre', 'fecha_ingreso', 'fecha_entrega_estimada', 'total', 'saldo', 'orden'];
    $sortField = $_GET['sort'];
    $sortDir = (!empty($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';
    if (in_array($sortField, $allowedSort)) {
      $orderBy = "t.{$sortField} {$sortDir}";
    }
  }
  
  // Paginación
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 50;
  $offset = ($page - 1) * $perPage;
  
  // Contar total
  $countSql = "SELECT COUNT(*) as total 
               FROM prm_trabajos t 
               INNER JOIN clientes c ON c.id = t.cliente_id
               LEFT JOIN prm_servicios s ON s.id = t.servicio_id
               LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id
               WHERE {$whereClause}";
  
  if ($params) {
    $stmtCount = $db->prepare($countSql);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
  } else {
    $totalRows = $db->query($countSql)->fetch_assoc()['total'];
  }
  
  // Query principal
  $sql = "SELECT 
            t.id,
            t.codigo,
            t.nombre,
            t.descripcion,
            t.cliente_id,
            c.contacto_nombre AS cliente_nombre,
            c.razon_social AS cliente_razon_social,
            t.presupuesto_id,
            p.codigo AS presupuesto_codigo,
            t.servicio_id,
            s.nombre AS servicio_nombre,
            t.fecha_ingreso,
            t.fecha_entrega_estimada,
            t.fecha_entrega_real,
            t.estado,
            t.prioridad,
            t.total,
            t.moneda,
            t.medio_pago,
            t.saldo,
            t.requiere_homologacion,
            t.homologacion_estado,
            t.orden,
            t.creado_en,
            t.actualizado_en
          FROM prm_trabajos t
          INNER JOIN clientes c ON c.id = t.cliente_id
          LEFT JOIN prm_servicios s ON s.id = t.servicio_id
          LEFT JOIN cli_presupuestos p ON p.id = t.presupuesto_id
          WHERE {$whereClause}
          ORDER BY {$orderBy}
          LIMIT ? OFFSET ?";
  
  $params[] = $perPage;
  $params[] = $offset;
  $types .= 'ii';
  
  $stmt = $db->prepare($sql);
  if ($params) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  
  $items = [];
  while ($row = $result->fetch_assoc()) {
    // Calcular días para entrega
    if ($row['fecha_entrega_estimada']) {
      $diff = strtotime($row['fecha_entrega_estimada']) - time();
      $row['dias_para_entrega'] = floor($diff / (60 * 60 * 24));
    } else {
      $row['dias_para_entrega'] = null;
    }
    
    // Porcentaje de saldo
    if ($row['total'] > 0) {
      $row['porc_saldo'] = round(($row['saldo'] / $row['total']) * 100, 2);
    } else {
      $row['porc_saldo'] = 0;
    }
    
    // Total pagado
    $row['total_pagado'] = $row['total'] - $row['saldo'];
    
    // Convertir campos numéricos
    $row['id'] = (int)$row['id'];
    $row['cliente_id'] = (int)$row['cliente_id'];
    $row['servicio_id'] = $row['servicio_id'] ? (int)$row['servicio_id'] : null;
    $row['presupuesto_id'] = $row['presupuesto_id'] ? (int)$row['presupuesto_id'] : null;
    $row['total'] = (float)$row['total'];
    $row['saldo'] = (float)$row['saldo'];
    $row['total_pagado'] = (float)$row['total_pagado'];
    $row['orden'] = (int)$row['orden'];
    $row['requiere_homologacion'] = (int)$row['requiere_homologacion'];
    
    $items[] = $row;
  }
  
  $totalPages = ceil($totalRows / $perPage);
  
  $response = [
    'items' => $items,
    'meta' => [
      'total' => (int)$totalRows,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => $totalPages
    ]
  ];
  
  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[trabajos/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}