<?php
// api/core/crud_helper.php
// Requiere tu /inc/conect.php con DB::get()

/**
 * Valida y castea payload según $cfg['fields'].
 * Tipos soportados: int, float, bool, str, set
 * Opciones por campo: required(bool), nullable(bool), default(mixed), in(array) [para set], syn(array) [mapa de sinónimos]
 * Devuelve [cols=>vals, errors[]]
 */
function lcars_validate(array $fieldsCfg, array $payload): array {
  $cols = [];
  $errors = [];

  foreach ($fieldsCfg as $alias => $def) {
    $type     = $def['type']     ?? 'str';
    $colName  = $def['col']      ?? $alias;
    $required = $def['required'] ?? false;
    $nullable = $def['nullable'] ?? false;
    $default  = $def['default']  ?? null;
    $allowed  = $def['syn']      ?? $def['in'] ?? null;   // para set, acepta 'sin' o 'in'
    //$syn      = $def['syn']      ?? [];     // mapa de sinónimos para set/str
    $maxLen   = $def['max']      ?? null;   // opcional para str

    $hasValue = array_key_exists($alias, $payload);
    $val      = $hasValue ? $payload[$alias] : $default;

    // Normalización por tipo
    switch ($type) {
      case 'int':
        if ($val === '' || $val === null) { $val = null; }
        else { $val = (int)$val; }
        break;

      case 'float':
        if ($val === '' || $val === null) { $val = null; }
        else { $val = (float)$val; }
        break;

      case 'bool':
        $val = !empty($val) ? 1 : 0;
        break;

      case 'set':
        $val = strtolower((string)$val);
        if ($val === '' && $default !== null) $val = strtolower((string)$default);
        if ($val === '' && $nullable) $val = strtolower((string)$nullable);
        if (isset($allowed[$val])) $val = $allowed[$val];
        if (is_array($allowed) && !in_array($val, $allowed, true)) {
          if ($required) { $errors[] = "$alias inválido (recibido: '$val', esperado: ".implode('|', $allowed).")"; continue 2; }
          // si no requerido, forzamos null o default válido
          $val = (is_array($allowed) && count($allowed)) ? $val : null;
        }
        break;

      default: // 'str'
        if ($val === null) { /* ok */ }
        else { $val = (string)$val; }
        if ($maxLen && is_string($val)) $val = mb_substr($val, 0, (int)$maxLen);
        break;
    }

    // Reglas required/nullable
    $isEmptyStr = ($type === 'str' || $type === 'set') && ($val === '' || $val === null);
    if ($required && ($val === null || $isEmptyStr)) {
      $errors[] = "$alias requerido";
      continue;
    }
    if (!$nullable && $val === null) {
      // si no es nullable y vino null, usar default si existe
      if ($default !== null) $val = $default;
    }

    $cols[$colName] = $val;
  }

  return [$cols, $errors];
}

/**
 * Guarda (insert/update) en $cfg['table'] con pk $cfg['pk'].
 * $cfg:
 *  - table (string), pk (string, default 'id')
 *  - fields (array) ver lcars_validate()
 *  - unique (array de aliases) -> chequeo de unicidad
 *  - children (array opcional): [
 *        'collection_key' => 'features',
 *        'table' => 'prm_planes_features',
 *        'fk'    => 'plan_id',
 *        'fields'=> [ alias=>['col'=>'','type'=>...] ... ],
 *        'replace' => true  (borra e inserta),
 *   ]
 * Devuelve ['ok'=>true,'id'=>int,'created'=>bool]
 */
function lcars_save(array $cfg, array $payload): array {
  $db = DB::get();
  $table = $cfg['table']; 
  $pk = $cfg['pk'] ?? 'id';
  $fieldsCfg = $cfg['fields'] ?? [];
  $unique    = $cfg['unique'] ?? [];
  $children  = $cfg['children'] ?? null;

  $id = (int)($payload[$pk] ?? 0);
  [$cols, $errors] = lcars_validate($fieldsCfg, $payload);
  
  if ($errors) {
    http_response_code(400);
    return ['ok'=>false, 'errors'=>$errors, 'validation_failed' => true];
  }

  try {
    $db->begin_transaction();

    // Unicidad - mejorado para soportar unique compuesto
    foreach ($unique as $key => $value) {
      // Puede ser: 'codigo' o 'uk_doc' => ['tipo_doc', 'nro_doc']
      if (is_array($value)) {
        // Unicidad compuesta
        $conditions = [];
        $types = '';
        $vals = [];
        
        foreach ($value as $field) {
          if (!isset($fieldsCfg[$field])) continue;
          $col = $fieldsCfg[$field]['col'] ?? $field;
          $val = $cols[$col] ?? null;
          if ($val === null || $val === '') continue;
          
          $conditions[] = "$col = ?";
          $vals[] = $val;
          $types .= is_int($val) ? 'i' : 's';
        }
        
        if (!empty($conditions)) {
          $where = implode(' AND ', $conditions);
          $sqlU = "SELECT $pk FROM $table WHERE $where".($id>0?" AND $pk <> ?":"")." LIMIT 1";
          $stmtU = $db->prepare($sqlU);
          
          if ($id > 0) {
            $types .= 'i';
            $vals[] = $id;
          }
          
          $stmtU->bind_param($types, ...$vals);
          $stmtU->execute();
          $stmtU->store_result();
          
          if ($stmtU->num_rows > 0) {
            $db->rollback();
            http_response_code(409);
            return ['ok'=>false, 'error'=>'duplicado', 'field'=>$key];
          }
          $stmtU->free_result();
          $stmtU->close();
        }
      } else {
        // Unicidad simple
        $alias = $value;
        if (!isset($fieldsCfg[$alias])) continue;
        $colUnique = $fieldsCfg[$alias]['col'] ?? $alias;
        $val = $cols[$colUnique] ?? null;
        if ($val === null || $val === '') continue;

        $sqlU = "SELECT $pk FROM $table WHERE $colUnique = ?".($id>0?" AND $pk <> ?":"")." LIMIT 1";
        $stmtU = $db->prepare($sqlU);
        if ($id>0) $stmtU->bind_param('si', $val, $id); else $stmtU->bind_param('s', $val);
        $stmtU->execute(); 
        $stmtU->store_result();
        
        if ($stmtU->num_rows > 0) {
          $db->rollback();
          http_response_code(409);
          return ['ok'=>false, 'error'=>'duplicado', 'field'=>$alias];
        }
        $stmtU->free_result(); 
        $stmtU->close();
      }
    }

    // INSERT vs UPDATE
    if ($id === 0) {
      // construir INSERT
      $colsNoNull = array_keys($cols);
      $placeholders = implode(',', array_fill(0, count($colsNoNull), '?'));
      $colsSql = implode(',', $colsNoNull);

      $sql = "INSERT INTO $table ($colsSql) VALUES ($placeholders)";
      $stmt = $db->prepare($sql);

      if (!$stmt) {
        $db->rollback();
        return ['ok'=>false, 'error'=>'prepare_failed', 'mysql_error'=>$db->error, 'sql'=>$sql];
      }

      // tipos bind
      $types = '';
      $vals  = [];
      foreach ($colsNoNull as $c) {
        $v = $cols[$c];
        $vals[] = $v;
        $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
      }

      $stmt->bind_param($types, ...$vals);
      
      if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $db->rollback();
        return ['ok'=>false, 'error'=>'insert_failed', 'mysql_error'=>$error];
      }
      
      $id = (int)$db->insert_id;
      $stmt->close();
      $created = true;

    } else {
      // construir UPDATE
      $sets = [];
      $vals = [];
      $types = '';
      
      foreach ($cols as $c => $v) {
        $sets[] = "$c = ?";
        $vals[] = $v;
        $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
      }
      
      if (!$sets) {
        // nada para actualizar, igual consideramos ok
        $db->commit();
        return ['ok'=>true, 'id'=>$id, 'created'=>false, 'message'=>'no_changes'];
      }
      
      $sql = "UPDATE $table SET ".implode(',', $sets)." WHERE $pk = ? LIMIT 1";
      $stmt = $db->prepare($sql);
      
      if (!$stmt) {
        $db->rollback();
        return ['ok'=>false, 'error'=>'prepare_failed', 'mysql_error'=>$db->error, 'sql'=>$sql];
      }
      
      $types .= 'i';
      $vals[] = $id;
      $stmt->bind_param($types, ...$vals);
      
      if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $db->rollback();
        return ['ok'=>false, 'error'=>'update_failed', 'mysql_error'=>$error];
      }
      
      $stmt->close();
      $created = false;
    }

    // Children (replace)
    if ($children && !empty($children['replace'])) {
      $listKey = $children['collection_key'] ?? null;
      $childTable = $children['table'] ?? null;
      $fk = $children['fk'] ?? null;
      $childFieldsCfg = $children['fields'] ?? [];

      if ($listKey && $childTable && $fk) {
        // Borro actuales
        $del = $db->prepare("DELETE FROM $childTable WHERE $fk=?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        // Inserto nuevos
        $items = is_array($payload[$listKey] ?? null) ? $payload[$listKey] : [];
        if (!empty($items)) {
          // Preparo SQL dinámico para hijos
          $aliases = array_keys($childFieldsCfg);
          $colsChild = array_map(fn($a)=>$childFieldsCfg[$a]['col'] ?? $a, $aliases);
          $sqlIns = "INSERT INTO $childTable ($fk,".implode(',', $colsChild).") VALUES (".
                    implode(',', array_fill(0, count($colsChild)+1, '?')).")";
          $stmt = $db->prepare($sqlIns);

          foreach ($items as $one) {
            [$ccols, $cerr] = lcars_validate($childFieldsCfg, $one);
            if ($cerr) continue; // salteo inválidos
            // bind types
            $vals = [$id];
            $types = 'i';
            foreach ($colsChild as $cname) {
              $v = $ccols[$cname] ?? null;
              $vals[] = $v;
              $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
          }
          $stmt->close();
        }
      }
    }

    $db->commit();
    http_response_code($created ? 201 : 200);
    return ['ok'=>true, 'id'=>$id, 'created'=>$created, 'syn'=>$types, 'vals'=>$vals];
    
  } catch (Throwable $e) {
    $db->rollback();
    return ['ok'=>false, 'error'=>'exception', 'message'=>$e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine()];
  }
}

/**
 * Delete con cascadas opcionales.
 * $cfg: table, pk (default 'id'), cascade (array de SQL con ? para id)
 */
function lcars_delete(array $cfg, int $id): array {
  $db = DB::get();
  $table = $cfg['table']; 
  $pk = $cfg['pk'] ?? 'id';

  try {
    $db->begin_transaction();

    // cascadas
    $cascade = $cfg['cascade'] ?? [];
    foreach ($cascade as $sql) {
      $stmt = $db->prepare($sql);
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
    }

    // delete principal
    $dp = $db->prepare("DELETE FROM $table WHERE $pk=? LIMIT 1");
    $dp->bind_param('i', $id);
    $dp->execute();
    $affected = $dp->affected_rows;
    $dp->close();

    if ($affected !== 1) {
      $db->rollback();
      http_response_code(404);
      return ['ok'=>false, 'error'=>'no_encontrado'];
    }

    $db->commit();
    http_response_code(204); // sin body
    return ['ok'=>true];
    
  } catch (Throwable $e) {
    $db->rollback();
    return ['ok'=>false, 'error'=>'exception', 'message'=>$e->getMessage()];
  }
}