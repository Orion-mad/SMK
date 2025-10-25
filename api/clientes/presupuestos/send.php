<?php
// /api/clientes/presupuestos/send.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  
  if (!$input) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $id = (int)($input['id'] ?? 0);
  $emailDestino = trim($input['email'] ?? '');
  $mensaje = trim($input['mensaje'] ?? 'sin mensaje');
  
  if ($id <= 0) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id requerido', 'debug' => ['id' => $id]]);
    exit;
  }

  if (empty($emailDestino)) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email requerido', 'debug' => ['email' => $emailDestino]]);
    exit;
  }
  
  if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email inválido: ' . $emailDestino]);
    exit;
  }

  $db = DB::get();

  // Obtener presupuesto
  $stmt = $db->prepare("
    SELECT 
      p.id,
      p.codigo,
      p.estado,
      p.total,
      p.moneda,
      p.fecha_vencimiento,
      p.dias_validez,
      p.cliente_id,
      COALESCE(NULLIF(c.razon_social, ''), c.contacto_nombre) AS cliente_nombre,
      c.email AS cliente_email
    FROM cli_presupuestos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    WHERE p.id = ?
    LIMIT 1
  ");

  $stmt->bind_param('i', $id);
  $stmt->execute();
  $presupuesto = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$presupuesto) {
    //http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Presupuesto no encontrado']);
    exit;
  }

  // Verificar que no sea borrador
  if ($presupuesto['estado'] === 'borrador') {
    //http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se puede enviar un presupuesto en estado borrador']);
    exit;
  }

  // Cargar PHPMailer
  $base = $_SERVER['DOCUMENT_ROOT'] . '/servicios/phpmailer/src/';
  require_once $base . 'PHPMailer.php';
  require_once $base . 'SMTP.php';
  require_once $base . 'Exception.php';

  // Verificar que la clase exista
  if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    throw new Exception('PHPMailer no está cargado o la ruta es incorrecta: ' . $base);
  }

  // Función para generar PDF temporal
  function generarPDFTemporal($presupuestoId) {
    // Capturar la salida del generador de PDF
    ob_start();
    $_GET['id'] = $presupuestoId;
    require __DIR__ . '/pdf.php';
    $pdfContent = ob_get_clean();
    return $filename;
  }

  try {
    // Crear instancia de PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Cargar configuración
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/servicios/phpmailer/mailform.config.json';
    if (!file_exists($configPath)) {
      throw new Exception("Archivo de configuración no encontrado: {$configPath}");
    }
    
    $formConfig = json_decode(file_get_contents($configPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception("Error parseando JSON: " . json_last_error_msg());
    }
    // Configurar SMTP
    if (!empty($formConfig['useSmtp'])) {
      $mail->isSMTP();
      $mail->Host       = $formConfig['host'] ?? '';
      $mail->Port       = $formConfig['port'] ?? 587;
      $mail->SMTPAuth   = false;
      $mail->Username   = $formConfig['username'] ?? '';
      $mail->Password   = $formConfig['password'] ?? '';
      $mail->SMTPOptions = array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            );
      
      $mail->Timeout = $formConfig['timeout'] ?? 30;
      $mail->CharSet = 'UTF-8';
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';
    }
    
    // Configurar remitente y destinatario
    $mail->setFrom('admin@sysmika.com', 'Orion Sistemas');
    $mail->addAddress($emailDestino, $presupuesto['cliente_nombre']);
    $mail->addReplyTo('admin@sysmika.com', 'Sysmika all web');
    
    // Contenido del email
    $mail->isHTML(true);
    $mail->Subject = 'Presupuesto ' . $presupuesto['codigo'];
    
    $fechaVenc = $presupuesto['fecha_vencimiento'] 
      ? date('d/m/Y', strtotime($presupuesto['fecha_vencimiento']))
      : ($presupuesto['dias_validez'] . ' días desde emisión');
    

    $htmlBody = "
      <html>
      <body style='font-family: Arial, sans-serif;'>
        <h2 style='color: #1f497d;'>Presupuesto {$presupuesto['codigo']}</h2>
        <p>Estimado/a <strong>{$presupuesto['cliente_nombre']}</strong>,</p>
        <p>" . nl2br(htmlspecialchars($mensaje)) . "</p>
        <p>Adjuntamos el presupuesto solicitado.</p>
        <hr style='border: 1px solid #ddd;'>
        <p><strong>Total: " . $presupuesto['moneda'] . " $" . number_format((float)$presupuesto['total'], 2, ',', '.') . "</strong></p>
        <p><small>Válido hasta: {$fechaVenc}</small></p>
        <br>
        <p style='color: #666; font-size: 12px;'>
          Riobamba 51 Dto. 1, Lanús - Buenos Aires - Argentina<br>
          Teléfono: (54)11.4249.1385 | WhatsApp: (54)9.11.2321.6228
        </p>
      </body>
      </html>
    ";
    
    $mail->Body = $htmlBody;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $htmlBody));
    
    // Generar y adjuntar PDF
    $pdfPath = generarPDFTemporal($id);
    if (!file_exists($pdfPath)) {
      throw new Exception('No se pudo generar el PDF');
    }
$mail->SMTPDebug = 4;
//echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $formConfig]);exit;

    
    //$clienteNombreLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $presupuesto['razon_social']);
    //$pdfFilename = 'Presupuesto_' . $clienteNombreLimpio . '_' . $presupuesto['codigo'] . '.pdf';
    //$mail->addAttachment($pdfPath, $pdfFilename);
    
    // Enviar email
    $mail->send();
    
    // Eliminar archivo temporal
    if (file_exists($pdfPath)) {
      @unlink($pdfPath);
    }
  } catch (Exception $e) {
    throw new Exception('Error enviando email: ' . $e->getMessage());
  }

  // Actualizar estado a "enviado" y timestamp
  $db->begin_transaction();

  try {
    $stmt = $db->prepare("
      UPDATE cli_presupuestos 
      SET estado = 'enviado', enviado_en = NOW() 
      WHERE id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Registrar en historial
    $stmt = $db->prepare("
      INSERT INTO cli_presupuestos_historial (presupuesto_id, estado_anterior, estado_nuevo, comentario)
      VALUES (?, ?, 'enviado', ?)
    ");
    $comentario = "Enviado a: $emailDestino";
    $stmt->bind_param('iss', $id, $presupuesto['estado'], $comentario);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    http_response_code(200);
    echo json_encode([
      'ok' => true, 
      'mensaje' => 'Presupuesto enviado correctamente a ' . $emailDestino
    ], JSON_UNESCAPED_UNICODE);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  error_log('[presupuestos/send] ' . $e->getMessage());
  //http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}