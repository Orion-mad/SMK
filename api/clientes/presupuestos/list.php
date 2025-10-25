<?php
// /api/clientes/presupuestos/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../core/list_helper.php';

try {
  $db = DB::get();
  
  // Verificar que las tablas existen
  $checkTable = $db->query("SHOW TABLES LIKE 'cli_presupuestos'");
  if ($checkTable->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'tabla_no_existe', 'detail' => 'La tabla cli_presupuestos no existe']);
    exit;
  }

  $cfg = [
    'table' => 'cli_presupuestos p INNER JOIN clientes c ON c.id = p.cliente_id',
    'select' => [
      'id'                => 'p.id',
      'codigo'            => 'p.codigo',
      'cliente_id'        => 'p.cliente_id',
      'cliente_nombre'    => 'CASE WHEN c.razon_social IS NOT NULL AND c.razon_social != \'\' THEN c.razon_social ELSE c.contacto_nombre END',
      'cliente_tipo_doc'  => 'c.tipo_doc',
      'cliente_doc'       => 'c.nro_doc',
      'titulo'            => 'p.titulo',
      'fecha_emision'     => 'p.fecha_emision',
      'fecha_vencimiento' => 'p.fecha_vencimiento',
      'dias_validez'      => 'p.dias_validez',
      'estado'            => 'p.estado',
      'moneda'            => 'p.moneda',
      'subtotal'          => 'p.subtotal',
      'descuento_monto'   => 'p.descuento_monto',
      'iva_monto'         => 'p.iva_monto',
      'total'             => 'p.total',
      'tipo_cobro'        => 'p.tipo_cobro',
      'version'           => 'p.version',
      'activo'            => 'p.activo',
      'creado_en'         => 'p.creado_en',
      'actualizado_en'    => 'p.actualizado_en',
      'enviado_en'        => 'p.enviado_en',
      'aprobado_en'       => 'p.aprobado_en',
    ],
    'orderable' => ['id', 'codigo', 'fecha_emision', 'fecha_vencimiento', 'estado', 'total', 'cliente_nombre'],
    'default_order' => ['p.id' => 'DESC'],
    'searchable' => ['p.codigo', 'c.razon_social', 'c.contacto_nombre', 'c.nro_doc', 'p.titulo'],
    'numeric' => [
      'id'              => 'int',
      'cliente_id'      => 'int',
      'subtotal'        => 'float',
      'descuento_monto' => 'float',
      'iva_monto'       => 'float',
      'total'           => 'float',
      'dias_validez'    => 'int',
      'version'         => 'int',
    ],
    'filters' => [
      'estado' => [
        'col' => 'p.estado',
        'type' => 'str',
        'in' => ['borrador', 'enviado', 'aprobado', 'rechazado', 'vencido', 'cancelado']
      ],
      'cliente_id' => [
        'col' => 'p.cliente_id',
        'type' => 'int'
      ],
      'moneda' => [
        'col' => 'p.moneda',
        'type' => 'str',
        'in' => ['ARG', 'DOL', 'EUR']
      ],
      'tipo_cobro' => [
        'col' => 'p.tipo_cobro',
        'type' => 'str',
        'in' => ['mensual', 'anual', 'unico']
      ],
      // Filtro por rango de fechas
      'fecha_desde' => [
        'col' => 'p.fecha_emision',
        'type' => 'date',
        'op' => '>='
      ],
      'fecha_hasta' => [
        'col' => 'p.fecha_emision',
        'type' => 'date',
        'op' => '<='
      ],
    ],
    'per_page' => 50,
  ];

  $result = lcars_list($cfg, $_GET);
  
  // Calcular estado_calculado y agregar items_count
  if (isset($result['items'])) {
    $ids = array_column($result['items'], 'id');
    
    // Obtener conteo de items por presupuesto
    if (!empty($ids)) {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $db->prepare("
        SELECT presupuesto_id, COUNT(*) as count 
        FROM cli_presupuestos_items 
        WHERE presupuesto_id IN ($placeholders) AND activo = 1
        GROUP BY presupuesto_id
      ");
      $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
      $stmt->execute();
      $itemsResult = $stmt->get_result();
      
      $itemsCounts = [];
      while ($row = $itemsResult->fetch_assoc()) {
        $itemsCounts[$row['presupuesto_id']] = (int)$row['count'];
      }
      $stmt->close();
    }
    
    // Enriquecer cada item
    foreach ($result['items'] as &$item) {
      // Agregar conteo de items
      $item['items_count'] = $itemsCounts[$item['id']] ?? 0;
      
      // Calcular estado real (vencido si pasó la fecha)
      $estado = $item['estado'];
      if ($estado === 'enviado' && $item['fecha_vencimiento']) {
        $venc = strtotime($item['fecha_vencimiento']);
        if ($venc < time()) {
          $estado = 'vencido';
        }
      }
      $item['estado_calculado'] = $estado;
    }
  }
  
  // Asegurar que siempre devuelva la estructura correcta
  if (!isset($result['items'])) {
    $result = ['items' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]];
  }
  
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[presupuestos/list] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error', 
    'detail' => $e->getMessage(),
    'items' => [],
    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0]
  ]);
}