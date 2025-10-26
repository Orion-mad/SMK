<?php
// /inc/email_config.php
/**
 * Configuración de Email para PHPMailer
 * 
 * IMPORTANTE: Completar con tus datos reales
 */

return [
    // Configuración SMTP
    'smtp' => [
        'host' => 'mail.sysmika.com',           // Servidor SMTP
        'port' => 465,                         // Puerto (587 para TLS, 465 para SSL)
        'secure' => 'ssl',                     // 'tls' o 'ssl'
        'auth' => true,                        // Usar autenticación
        'username' => 'noreply@sysmika.com',    // Tu email SMTP
        'password' => '0800miguel60',       // Password de aplicación
        
        // Configuración de timeouts (importante para evitar timeout)
        'timeout' => 30,                       // Timeout en segundos
        'options' => [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]
    ],
    
    // Remitente por defecto
    'from' => [
        'email' => 'tu-email@gmail.com',
        'name' => 'Sysmika Desarrollos Web'
    ],
    
    // Reply-to por defecto
    'replyto' => [
        'email' => 'admin@sysmika.com',
        'name' => 'Administración'
    ],
    
    // Datos de la empresa (para templates)
    'empresa' => [
        'nombre' => 'Sysmika Desarrollos Web',
        'direccion' => 'Riobamba 51 Dpto 1',
        'telefono' => '(011) 4249-1385',
        'email' => 'admin@sysmika.com',
        'web' => 'www.sysmika.com',
        'cuit' => '20-14095277-0'
    ],
    
    // Debug (true solo en desarrollo)
    'debug' => false,
];

/**
 * GUÍA DE CONFIGURACIÓN PARA GMAIL:
 * 
 * 1. Activar verificación en dos pasos:
 *    https://myaccount.google.com/signinoptions/two-step-verification
 * 
 * 2. Generar contraseña de aplicación:
 *    https://myaccount.google.com/apppasswords
 *    - Seleccionar "Correo" y "Otro dispositivo personalizado"
 *    - Copiar la contraseña generada (16 caracteres)
 *    - Usar esa contraseña en 'password' arriba
 * 
 * 3. Configuración recomendada:
 *    - host: smtp.gmail.com
 *    - port: 587
 *    - secure: tls
 * 
 * OTROS PROVEEDORES:
 * 
 * Outlook/Hotmail:
 * - host: smtp-mail.outlook.com
 * - port: 587
 * - secure: tls
 * 
 * Yahoo:
 * - host: smtp.mail.yahoo.com
 * - port: 587
 * - secure: tls
 * 
 * Servidor propio:
 * - Consultar con tu proveedor de hosting
 * - Generalmente: mail.tudominio.com
 * - Puerto: 587 (TLS) o 465 (SSL)
 */