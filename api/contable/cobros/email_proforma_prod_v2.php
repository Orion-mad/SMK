<?php
// /api/contable/cobros/email_proforma_prod_v2.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../inc/conect.php';
require_once __DIR__ . '/../../../servicios/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../../servicios/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../../servicios/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../../servicios/pdf/documento_proforma.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
  // Cargar configuración
  $emailConfig = require __DIR__ . '/../../../inc/email_config.php';
  
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
      co.*,
      c.razon_social,
      c.nombre_fantasia,
      c.tipo_doc,
      c.nro_doc,
      c.iva_cond as condicion_iva,
      c.direccion,
      c.localidad,
      c.provincia,
      c.cp,
      c.email,
      c.contacto_email,
      c.telefono,
      c.celular
    FROM cnt_cobros co
    INNER JOIN clientes c ON c.id = co.cliente_id
    WHERE co.id = ? AND co.activo = 1
    LIMIT 1
  ";
  
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if (!$row = $result->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Cobro no encontrado']);
    exit;
  }
  
  $stmt->close();
  
  // Determinar email del cliente
  $emailCliente = $row['contacto_email'] ?: $row['email'];
  
  if (!$emailCliente || !filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El cliente no tiene un email válido registrado']);
    exit;
  }
  
  // Preparar datos
  $nombreCliente = !empty($row['nombre_fantasia']) ? $row['nombre_fantasia'] : $row['razon_social'];
  $documentoCliente = $row['tipo_doc'] . ': ' . $row['nro_doc'];
  
  $cobro = [
    'id' => $row['id'],
    'codigo' => $row['codigo'],
    'numero_factura' => $row['numero_factura'],
    'tipo' => $row['tipo'],
    'concepto' => $row['concepto'],
    'subtotal' => (float)$row['subtotal'],
    'descuento' => (float)$row['descuento'],
    'impuestos' => (float)$row['impuestos'],
    'total' => (float)$row['total'],
    'moneda' => $row['moneda'],
    'fecha_emision' => $row['fecha_emision'],
    'fecha_vencimiento' => $row['fecha_vencimiento'],
    'estado' => $row['estado'],
    'monto_pagado' => (float)$row['monto_pagado'],
    'saldo' => (float)$row['saldo'],
    'observaciones' => $row['observaciones']
  ];
  
  $cliente = [
    'razon_social' => $nombreCliente,
    'documento' => $documentoCliente,
    'condicion_iva' => $row['condicion_iva'],
    'direccion' => $row['direccion'],
    'localidad' => $row['localidad'],
    'provincia' => $row['provincia'],
    'cp' => $row['cp'],
    'email' => $emailCliente,
    'telefono' => $row['telefono'] ?: $row['celular']
  ];
  
  // Generar PDF en memoria
  $pdf = new DocumentoProforma($cobro, $cliente);
  $pdf->generarProforma();
  $pdfContent = $pdf->Output('S'); // Output como string
  
  // Configurar PHPMailer usando el archivo de configuración
  $mail = new PHPMailer(true);
  
  try {
    // Configuración del servidor SMTP desde config
    $mail->isSMTP();
    $mail->Host       = $emailConfig['smtp']['host'];
    $mail->SMTPAuth   = $emailConfig['smtp']['auth'];
    $mail->Username   = $emailConfig['smtp']['username'];
    $mail->Password   = $emailConfig['smtp']['password'];
    $mail->SMTPSecure = $emailConfig['smtp']['secure'];
    $mail->Port       = $emailConfig['smtp']['port'];
    
    // Timeouts desde config
    $mail->Timeout = $emailConfig['smtp']['timeout'];
    $mail->SMTPOptions = $emailConfig['smtp']['options'];
    
    // Debug si está habilitado
    if ($emailConfig['debug']) {
      $mail->SMTPDebug = SMTP::DEBUG_SERVER;
      $mail->Debugoutput = 'error_log';
    }
    
    // Configuración del remitente desde config
    $mail->setFrom($emailConfig['from']['email'], $emailConfig['from']['name']);
    $mail->addReplyTo($emailConfig['replyto']['email'], $emailConfig['replyto']['name']);
    
    // Destinatario
    $mail->addAddress($emailCliente, $nombreCliente);
    
    // Adjuntar PDF
    $filename = 'proforma_' . $cobro['codigo'] . '.pdf';
    $mail->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
    
    // Contenido del email
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Proforma ' . $cobro['codigo'] . ' - ' . $nombreCliente;
    
    // Cuerpo del email en HTML
    $mail->Body = generarCuerpoEmail($cobro, $cliente, $emailConfig['empresa']);
    
    // Alternativa en texto plano
    $mail->AltBody = generarCuerpoTextoPlano($cobro, $cliente, $emailConfig['empresa']);
    
    // Enviar
    $mail->send();
    
    // Registrar envío exitoso (opcional)
    registrarEnvio($db, $id, $emailCliente);
    
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'message' => 'Proforma enviada exitosamente',
      'email' => $emailCliente,
      'codigo' => $cobro['codigo']
    ]);
    
  } catch (Exception $e) {
    error_log('[PHPMailer Error] ' . $mail->ErrorInfo);
    throw new Exception('Error al enviar email: ' . $mail->ErrorInfo);
  }
  
} catch (Throwable $e) {
  error_log('[contable/cobros/email_proforma_prod_v2] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Error al enviar el email',
    'detail' => $e->getMessage()
  ]);
}

// Función para generar cuerpo del email en HTML
function generarCuerpoEmail($cobro, $cliente, $empresa) {
  $total = number_format($cobro['total'], 2, ',', '.');
  $fecha = date('d/m/Y', strtotime($cobro['fecha_emision']));
  
  return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
    }
    .container {
      max-width: 600px;
      margin: 20px auto;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px 20px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 28px;
    }
    .content {
      padding: 30px 20px;
    }
    .greeting {
      font-size: 16px;
      margin-bottom: 20px;
    }
    .details-box {
      background: #f8f9fa;
      border-left: 4px solid #667eea;
      padding: 15px;
      margin: 20px 0;
      border-radius: 4px;
    }
    .details-box table {
      width: 100%;
      border-collapse: collapse;
    }
    .details-box td {
      padding: 8px 0;
    }
    .details-box td:first-child {
      font-weight: bold;
      width: 40%;
      color: #555;
    }
    .note {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 15px;
      margin: 20px 0;
      font-size: 14px;
      border-radius: 4px;
    }
    .footer {
      background: #f8f9fa;
      padding: 20px;
      text-align: center;
      font-size: 12px;
      color: #666;
      border-top: 1px solid #dee2e6;
    }
    .footer p {
      margin: 5px 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📄 Proforma de Pago</h1>
    </div>
    
    <div class="content">
      <p class="greeting">Estimado/a <strong>{$cliente['razon_social']}</strong>,</p>
      
      <p>Le adjuntamos la proforma correspondiente al servicio contratado. Encontrará el detalle completo en el documento PDF adjunto.</p>
      
      <div class="details-box">
        <table>
          <tr>
            <td>Código:</td>
            <td><strong>{$cobro['codigo']}</strong></td>
          </tr>
          <tr>
            <td>Concepto:</td>
            <td>{$cobro['concepto']}</td>
          </tr>
          <tr>
            <td>Fecha de emisión:</td>
            <td>{$fecha}</td>
          </tr>
          <tr>
            <td>Importe total:</td>
            <td><strong style="font-size: 18px; color: #667eea;">\${$total} {$cobro['moneda']}</strong></td>
          </tr>
        </table>
      </div>
      
      <div class="note">
        <strong>⚠️ Nota importante:</strong><br>
        Esta es una proforma (comprobante no fiscal). Una vez efectuado el pago, se emitirá la factura oficial correspondiente.
      </div>
      
      <p>Ante cualquier consulta, no dude en contactarnos.</p>
      
      <p>Saludos cordiales,<br>
      <strong>Equipo de Administración</strong></p>
    </div>
    
    <div class="footer">
      <p><strong>{$empresa['nombre']}</strong></p>
      <p>{$empresa['telefono']} | {$empresa['email']}</p>
      <p style="margin-top: 15px; font-style: italic;">Este es un mensaje automático, por favor no responda a este email.</p>
    </div>
  </div>
</body>
</html>
HTML;
}

// Función para generar cuerpo en texto plano
function generarCuerpoTextoPlano($cobro, $cliente, $empresa) {
  $total = number_format($cobro['total'], 2, ',', '.');
  $fecha = date('d/m/Y', strtotime($cobro['fecha_emision']));
  
  return <<<TEXT
PROFORMA DE PAGO

Estimado/a {$cliente['razon_social']},

Le adjuntamos la proforma correspondiente al servicio contratado.

DETALLES:
- Código: {$cobro['codigo']}
- Concepto: {$cobro['concepto']}
- Fecha de emisión: {$fecha}
- Importe total: \${$total} {$cobro['moneda']}

IMPORTANTE: Esta es una proforma (comprobante no fiscal). Una vez efectuado el pago, se emitirá la factura oficial correspondiente.

Ante cualquier consulta, no dude en contactarnos.

Saludos cordiales,
Equipo de Administración

---
{$empresa['nombre']}
{$empresa['telefono']} | {$empresa['email']}

Este es un mensaje automático, por favor no responda a este email.
TEXT;
}

// Función para registrar el envío (opcional)
function registrarEnvio($db, $cobro_id, $email_destinatario) {
  try {
    $sql = "
      INSERT INTO cnt_cobros_emails_log (cobro_id, email_destinatario, fecha_envio)
      VALUES (?, ?, NOW())
      ON DUPLICATE KEY UPDATE fecha_envio = NOW()
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $cobro_id, $email_destinatario);
    $stmt->execute();
    $stmt->close();
  } catch (Exception $e) {
    // Si la tabla no existe, ignorar el error
    error_log('[registrarEnvio] Tabla de logs no existe: ' . $e->getMessage());
  }
}