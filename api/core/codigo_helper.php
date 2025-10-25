<?php
// /api/core/codigo_helper.php
declare(strict_types=1);

/**
 * Genera el siguiente código automático para una tabla
 * 
 * @param mysqli $db Conexión a la base de datos
 * @param string $tabla Nombre de la tabla
 * @param string $campo Nombre del campo de código (default: 'codigo')
 * @param string $prefijo Prefijo para el código (default: tabla en mayúsculas)
 * @return string Nuevo código generado
 */
function lcars_codigo_next($db, string $tabla, string $campo = 'codigo', string $prefijo = null): string {
  // Si no se especifica prefijo, usar las primeras 3 letras de la tabla en mayúsculas
  if (!$prefijo) {
    $prefijo = strtoupper(substr($tabla, 0, 3));
  }
  
  // Buscar el último código con ese prefijo
  $stmt = $db->prepare("
    SELECT $campo 
    FROM $tabla 
    WHERE $campo LIKE CONCAT(?, '%') 
    ORDER BY $campo DESC 
    LIMIT 1
  ");
  
  $stmt->bind_param('s', $prefijo);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    // No hay códigos previos, empezar en 1
    $stmt->close();
    return $prefijo . '-0001';
  }
  
  $row = $result->fetch_assoc();
  $ultimo_codigo = $row[$campo];
  $stmt->close();
  
  // Extraer el número del último código
  // Formato esperado: PREFIJO-0001, PREFIJO-0002, etc.
  if (preg_match('/(\d+)$/', $ultimo_codigo, $matches)) {
    $ultimo_numero = (int)$matches[1];
    $nuevo_numero = $ultimo_numero + 1;
    
    // Mantener el mismo padding (cantidad de dígitos)
    $padding = strlen($matches[1]);
    return $prefijo . '-' . str_pad((string)$nuevo_numero, $padding, '0', STR_PAD_LEFT);
  }
  
  // Si no se pudo extraer número, empezar en 1
  return $prefijo . '-0001';
}

/**
 * Autogenera código si no viene o está vacío (solo en INSERT)
 * Modifica el array $input directamente
 * 
 * @param mysqli $db Conexión a la base de datos
 * @param string $tabla Nombre de la tabla
 * @param array &$input Array de datos de entrada (se modifica por referencia)
 * @param string $campo_codigo Nombre del campo de código (default: 'codigo')
 * @param string $campo_id Nombre del campo ID (default: 'id')
 * @param string $prefijo Prefijo opcional para el código
 */
function lcars_autocodigo($db, string $tabla, array &$input, string $campo_codigo = 'codigo', string $campo_id = 'id', string $prefijo = null): void {
  $id = (int)($input[$campo_id] ?? 0);
  $codigo = trim($input[$campo_codigo] ?? '');
  
  // Solo autogenerar en INSERT (id = 0) y si el código está vacío
  if ($id === 0 && empty($codigo)) {
    $input[$campo_codigo] = lcars_codigo_next($db, $tabla, $campo_codigo, $prefijo);
  }
}

/**
 * Verifica si un código ya existe en la tabla (excepto el registro actual en UPDATE)
 * 
 * @param mysqli $db Conexión a la base de datos
 * @param string $tabla Nombre de la tabla
 * @param string $codigo Código a verificar
 * @param int $id ID del registro actual (0 para INSERT)
 * @param string $campo_codigo Nombre del campo de código (default: 'codigo')
 * @param string $campo_id Nombre del campo ID (default: 'id')
 * @return bool True si el código ya existe, false si está disponible
 */
function lcars_codigo_existe($db, string $tabla, string $codigo, int $id = 0, string $campo_codigo = 'codigo', string $campo_id = 'id'): bool {
  if ($id > 0) {
    // UPDATE: verificar si existe en otro registro
    $stmt = $db->prepare("SELECT $campo_id FROM $tabla WHERE $campo_codigo = ? AND $campo_id != ? LIMIT 1");
    $stmt->bind_param('si', $codigo, $id);
  } else {
    // INSERT: verificar si existe en cualquier registro
    $stmt = $db->prepare("SELECT $campo_id FROM $tabla WHERE $campo_codigo = ? LIMIT 1");
    $stmt->bind_param('s', $codigo);
  }
  
  $stmt->execute();
  $stmt->store_result();
  $existe = $stmt->num_rows > 0;
  $stmt->close();
  
  return $existe;
}