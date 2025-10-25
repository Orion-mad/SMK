<?php
// /api/core/list_helper.php
// Requiere: mysqlnd (tu diag mostró que está OK).
// Uso: lcars_list($config, $_GET);

function lcars_list(array $cfg, array $params): array {
  // === Config obligatoria ===
  if (empty($cfg['table'])) throw new InvalidArgumentException('table requerido');
  if (empty($cfg['select']) || !is_array($cfg['select'])) throw new InvalidArgumentException('select requerido');

  // === DB ===
  if (!class_exists('DB')) throw new RuntimeException('DB class no encontrada (conect.php)');
  $db = DB::get();

  // === SELECT ===
  // $cfg['select'] = ['alias' => 'sql_col_o_expr', ...] (definido por el endpoint → seguro)
  $selectPieces = [];
  foreach ($cfg['select'] as $alias => $expr) {
    // sólo alias válidos (evitar inyecciones en AS)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$alias)) {
      throw new InvalidArgumentException('Alias inválido: '.$alias);
    }
    $selectPieces[] = $expr . ' AS ' . $alias;
  }
  $selectSql = implode(', ', $selectPieces);

  $tableSql = $cfg['table']; // puede incluir alias seguro definido en el endpoint (p.ej. "prm_planes p")

  // === Parámetros comunes ===
  $page     = max(1, (int)($params['page'] ?? 1));
  $perPage  = max(1, min(200, (int)($params['per_page'] ?? ($cfg['per_page'] ?? 50))));
  $offset   = ($page - 1) * $perPage;

  $orderable   = $cfg['orderable']   ?? [];                   // ['id','codigo',...]
  $defaultOrd  = $cfg['default_order'] ?? ['id' => 'ASC'];    // ['orden'=>'ASC','id'=>'ASC'] o ['id'=>'ASC']
  $searchable  = $cfg['searchable']  ?? [];                   // aliases (coinciden con keys de $cfg['select'])
  $numericCast = $cfg['numeric']     ?? [];                   // ['id'=>'int','precio'=>'float']

  // === WHERE dinámico (con prepared) ===
  $wheres = [];
  $bindT  = '';
  $bindV  = [];

  // Filtros declarativos (exact match)
  // $cfg['filters'] = ['activo'=>['col'=>'p.activo','type'=>'int'], 'moneda'=>['col'=>'p.moneda','type'=>'str','in'=>['ARG','DOL','EUR']]]
  if (!empty($cfg['filters'])) {
    foreach ($cfg['filters'] as $key => $f) {
      $paramKey = $f['param'] ?? $key; // p.ej. GET ?activo=1
      if (!isset($params[$paramKey]) || $params[$paramKey]==='') continue;
      $col  = $f['col'];
      $type = $f['type'] ?? 'str';
      $val  = $params[$paramKey];

      if (!empty($f['in']) && is_array($f['in'])) { // whitelist
        if (!in_array($val, $f['in'], true)) continue; // ignoro valor inválido
      }

      if ($type === 'int') {
        $val = (int)$val;
        $wheres[] = "$col = ?";
        $bindT   .= 'i';
        $bindV[]  = $val;
      } else {
        $val = (string)$val;
        $wheres[] = "$col = ?";
        $bindT   .= 's';
        $bindV[]  = $val;
      }
    }
  }

  // Búsqueda simple (q) sobre columnas $searchable
  $q = trim((string)($params['q'] ?? ''));
  if ($q !== '' && !empty($searchable)) {
    $likeParts = [];
    foreach ($searchable as $alias) {
      if (!isset($cfg['select'][$alias])) continue;
      $colExpr = $cfg['select'][$alias];
      $likeParts[] = "$colExpr LIKE ?";
      $bindT   .= 's';
      $bindV[]  = '%' . $q . '%';
    }
    if ($likeParts) $wheres[] = '(' . implode(' OR ', $likeParts) . ')';
  }

  $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

  // === ORDER BY seguro ===
  // Se acepta 'order' como alias y 'dir' ASC/DESC. Si querés múltiples por default, se respetan.
  $orderParam = (string)($params['order'] ?? '');
  $dirParam   = strtoupper((string)($params['dir'] ?? ''));
  $dirParam   = in_array($dirParam, ['ASC','DESC'], true) ? $dirParam : 'ASC';

  $orderSql = '';
  $pieces   = [];

  if ($orderParam && in_array($orderParam, $orderable, true)) {
    // Mapear alias a expr real
    $expr = $cfg['select'][$orderParam] ?? $orderParam; // cae en alias si coincide
    $pieces[] = $expr . ' ' . $dirParam;
  } else {
    // default_order puede ser ['orden'=>'ASC','id'=>'ASC'] o ['id'=>'ASC']
    foreach ($defaultOrd as $alias => $dir) {
      $dir2 = in_array(strtoupper($dir), ['ASC','DESC'], true) ? strtoupper($dir) : 'ASC';
      $expr = $cfg['select'][$alias] ?? $alias;
      $pieces[] = $expr . ' ' . $dir2;
    }
  }
  if ($pieces) $orderSql = 'ORDER BY ' . implode(', ', $pieces);

  // === SQL final ===
  $sqlData  = "SELECT $selectSql FROM $tableSql $whereSql $orderSql LIMIT ? OFFSET ?";
  $sqlCount = "SELECT COUNT(*) AS c FROM $tableSql $whereSql";

  // === Ejecutar COUNT ===
  $stmtC = $db->prepare($sqlCount);
  if ($bindT) $stmtC->bind_param($bindT, ...$bindV);
  $stmtC->execute();
  $resC = $stmtC->get_result();
  $total = (int)($resC->fetch_assoc()['c'] ?? 0);
  $stmtC->close();

  // === Ejecutar DATA ===
  $stmtD = $db->prepare($sqlData);
  $bindT2 = $bindT . 'ii';
  $bindV2 = array_merge($bindV, [ (int)$perPage, (int)$offset ]);
  $stmtD->bind_param($bindT2, ...$bindV2);
  $stmtD->execute();
  $resD = $stmtD->get_result();

  $items = [];
  while ($row = $resD->fetch_assoc()) {
    // Cast numéricos
    foreach ($numericCast as $k => $t) {
      if (!array_key_exists($k, $row)) continue;
      if ($t === 'int')   $row[$k] = (int)$row[$k];
      if ($t === 'float') $row[$k] = (float)$row[$k];
    }
    $items[] = $row;
  }
  $stmtD->close();

  $pages = (int)max(1, ceil($total / $perPage));

  return [
    'items' => $items,
    'meta'  => [
      'total'    => $total,
      'page'     => $page,
      'per_page' => $perPage,
      'pages'    => $pages
    ]
  ];
}
