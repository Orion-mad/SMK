<?php
// /api/contable/cobros/email_proforma.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $id = (int)($input['id'] ?? 0);
  
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID de cobro requerido']);
    exit;
  }

  $db = DB::get();
  
  // Obtener datos del cobro y cliente
  $sql = "
    SELECT 
      co.codigo,
      co.concepto,
      co.total,
      co.moneda,
      co.fecha_emision,
      c.razon_social as cliente_razon,
      c.nombre_fantasia as cliente_fantasia,
      c.email as cliente_email,
      c.contacto_email
    FROM cnt_cobros co
    INNER JOIN clientes c ON c.id = co.cliente_id
    WHERE co.id = ? AND co.activo = 1
    LIMIT 1
  ";
  
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc(); 
  
  if (!$row) {
    http_response_code(404);
  }
  
  $stmt->close();
  // Determinar email del cliente
  $emailCliente = $row['contacto_email'] ?: $row['cliente_email'];
  
  if (!$emailCliente) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El cliente no tiene email registrado']);
    exit;
  }
  
  // Datos del email
  $clienteNombre = $row['cliente_fantasia'] ?: $row['cliente_razon'];
  $subject = "Proforma {$row['codigo']} - {$clienteNombre}";
  
  // URL de la proforma
  $proformaUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                 . "://" . $_SERVER['HTTP_HOST'] 
                 . "/api/contable/cobros/proforma.php?id={$id}&action=view";
  // Cargar PHPMailer
  $base = $_SERVER['DOCUMENT_ROOT'] . '/servicios/phpmailer/src/';
  require_once $base . 'PHPMailer.php';
  require_once $base . 'SMTP.php';
  require_once $base . 'Exception.php';

  // Verificar que la clase exista
  if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    throw new Exception('PHPMailer no está cargado o la ruta es incorrecta: ' . $base);
  }
    // Crear instancia de PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
   // echo json_encode(['ok' => false, 'error' => json_encode($row)]);
   // exit;
  
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
    $mail->addAddress($emailCliente,$row['cliente_razon']);
    $mail->addReplyTo('admin@sysmika.com', 'Sysmika all web');
    
    // Contenido del email
    $mail->isHTML(true);
    $mail->Subject = 'Aviso de pago';
    
  
  // Cuerpo del email
  $body = '
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      color: #333;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 20px;
    }
    .header {
      background: #333;
      color: white;
      padding: 20px;
      text-align: center;
    }
    .content {
      padding: 20px;
      background: #f9f9f9;
    }
    .button {
      display: inline-block;
      padding: 12px 24px;
      background: #007bff;
      color: white;
      text-decoration: none;
      border-radius: 4px;
      margin: 20px 0;
    }
    .details {
      background: white;
      padding: 15px;
      border-left: 4px solid #007bff;
      margin: 20px 0;
    }
    .footer {
      text-align: center;
      padding: 20px;
      color: #666;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2>Proforma de Pago</h2>
    </div>
    
    <div class="content">
      <p>Estimado/a {$clienteNombre},</p>
      
      <p>Adjuntamos la proforma correspondiente a:</p>
      
      <div class="details">
        <strong>Código:</strong> '.$row['codigo'].'<br>
        <strong>Concepto:</strong> '.$row['concepto'].'<br>
        <strong>Importe:</strong> '.number_format((float)$row['total'], 2, ',', '.').'  '.$row['moneda'].'<br>
        <strong>Fecha de emisión:</strong> '.date('d/m/Y', strtotime($row['fecha_emision'])).'
      </div>
      
      <p>Puede visualizar y descargar la proforma haciendo clic en el siguiente enlace:</p>
      
      <p style="text-align: center;">
        <a href="{$proformaUrl}" class="button">Ver Proforma</a>
      </p>
      
      <p><strong>Nota importante:</strong> Esta es una proforma (comprobante no fiscal). Una vez efectuado el pago, se emitirá la factura oficial correspondiente.</p>
      
      <p>Ante cualquier consulta, no dude en contactarnos.</p>
      
      <p>Saludos cordiales.</p>
    </div>
    
    <div class="footer">
      <p>Este es un mensaje automático, por favor no responda a este email.</p>
    </div>
  </div>
</body>
</html>
';
    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

$mail->SMTPDebug = 4;$mail->send();
echo json_encode(['ok' => false, 'error' => $body, 'detail' => $formConfig]);exit;

    
    // Enviar email
  if ($mail->send()) {
    // Registrar envío (opcional)
    // Podrías crear una tabla de logs de emails enviados
    
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'message' => 'Proforma enviada exitosamente',
      'email' => $emailCliente
    ]);
  } else {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'Error al enviar el email. Verifique la configuración SMTP.'
    ]);
  }
  
} catch (Throwable $e) {
  error_log('[contable/cobros/email_proforma] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}