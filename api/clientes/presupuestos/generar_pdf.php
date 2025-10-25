<?php
// /api/clientes/presupuestos/generar_pdf.php
require_once __DIR__ . '/../../../servicios/pdf/PresupuestoPDF.php';
require_once __DIR__ . '/../../../inc/conect.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        die('ID de presupuesto requerido');
    }

    $db = DB::get();

    // Obtener presupuesto con datos del cliente
    $stmt = $db->prepare("
        SELECT 
            p.*,
            CASE 
                WHEN c.razon_social IS NOT NULL AND c.razon_social != '' THEN c.razon_social
                ELSE c.contacto_nombre
            END AS cliente_nombre,
            c.tipo_doc AS cliente_tipo_doc,
            c.nro_doc AS cliente_doc,
            c.email AS cliente_email,
            c.telefono AS cliente_telefono,
            c.direccion AS cliente_direccion
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
        die('Presupuesto no encontrado');
    }

    // Obtener items
    $stmt = $db->prepare("
        SELECT * FROM cli_presupuestos_items
        WHERE presupuesto_id = ? AND activo = 1
        ORDER BY orden ASC, id ASC
    ");
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Determinar tipo de PDF (orion para sistemas, sysmika para hosting/diseño)
    $tipo = 'orion'; // Por defecto, podrías agregar un campo en la BD para esto
    
    // Crear PDF con nombre del cliente
    $pdf = new PresupuestoPDF($tipo, $presupuesto['cliente_nombre']);
    $pdf->AliasNbPages();
    
    // PORTADA - usar cliente_nombre como título
    $tituloPortada = $presupuesto['cliente_nombre'];
    $pdf->Portada($tituloPortada);
    
    // PÁGINA 1: DESCRIPCIÓN DEL PROYECTO
    $pdf->PaginaContenido();
    
    // Título de la página
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 10, utf8_decode($presupuesto['titulo'] ?: 'Presupuesto'), 0, 1, 'L');
    
    // Introducción/Descripción
    if (!empty($presupuesto['introduccion'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $texto = strip_tags($presupuesto['introduccion']);
        $pdf->MultiCell(0,5, utf8_decode($texto));
    }
    
    // Sección de ítems/características
    if (count($items) > 0) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(31, 73, 125);
        $pdf->Cell(0, 8, utf8_decode('Características del Proyecto'), 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($items as $item) {
            if ($item['tipo'] === 'seccion') {
                // Título de sección
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->SetTextColor(31, 73, 125);
                $pdf->Cell(0, 6, utf8_decode(strip_tags($item['descripcion'])), 0, 1, 'L');
                $pdf->SetFont('Arial', '', 10);
                $pdf->SetTextColor(0, 0, 0);
            } else {
                // Item normal
                $texto = strip_tags($item['descripcion']);
                $pdf->Cell(5, 5, utf8_decode(''), 0, 0, 'L');
                $pdf->MultiCell(0, 5, utf8_decode($texto));
            }
                $pdf->Ln(5);
        }
    }
    
    /*/ PÁGINA 2: TECNOLOGÍAS Y COSTOS
    $pdf->AddPage();
    
    // Sección Tecnología
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 8, utf8_decode('Tecnología'), 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    // Tecnologías estándar
    $pdf->MultiCell(0,6, utf8_decode($presupuesto['observaciones']), 0, 'L');
    
    // Sección Costos
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 10, 'Costos', 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Tabla de costos
    $pdf->SetFillColor(240, 240, 240);
    
    $pdf->Cell(100, 7, 'Propietario:', 1, 0, 'L', true);
    $pdf->Cell(0, 7, utf8_decode($presupuesto['moneda'] . ' $' . number_format($presupuesto['total'], 2, ',', '.')), 1, 1, 'R');
    
    $pdf->Cell(100, 7, 'Tipo de proyecto:', 1, 0, 'L');
    $pdf->Cell(0, 7, utf8_decode($presupuesto['titulo']), 1, 1, 'R');
    
    $pdf->Cell(100, 7, 'Tipo de cobro:', 1, 0, 'L');
    $pdf->Cell(0, 7, utf8_decode(ucfirst($presupuesto['tipo_cobro'])), 1, 1, 'R');
    
    // Fecha y lugar
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $fecha = date('d-m-Y', strtotime($presupuesto['fecha_emision']));
    $pdf->Cell(0, 5, utf8_decode('Lanús ' . $fecha), 0, 1, 'L');
    
*/    
    // Condiciones adicionales si existen
    if (!empty($presupuesto['condiciones'])) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(31, 73, 125);
        $pdf->Cell(0, 8, utf8_decode('Condiciones Comerciales'), 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(5, 5, utf8_decode(''), 0, 0, 'L');
        $texto = strip_tags($presupuesto['condiciones']);
        $pdf->MultiCell(0, 5, utf8_decode($texto));
    }
    
    // Observaciones
    if (!empty($presupuesto['observaciones'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(31, 73, 125);
        $pdf->Cell(0, 8, 'Observaciones', 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(5, 5, utf8_decode(''), 0, 0, 'L');
        $texto = strip_tags($presupuesto['observaciones']);
        $pdf->MultiCell(0, 5, utf8_decode($texto));
    }
    // PÁGINA 3: TÉRMINOS Y CONDICIONES
    //$pdf->AddPage();
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(31, 73, 125);
    $pdf->Cell(0, 8, utf8_decode('Plazos de entrega y vencimiento de presupuestos'), 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $terminos = [
        'Los presupuestos tendrán una vigencia de 15 días.',
        'La forma de pago 50% al iniciar el trabajo, saldo al finalizar.(Sino se especifico otra)',
        'En el caso de servicios mensualizados, los mismos vencerán los días 10 de cada mes.',
        'Los plazos de entrega serán pautados con la aceptación del presupuesto.',
        'En caso de cambios y/o adicionales, los mismos se presupuestarán y los plazos de entrega serán pautados con la aceptación de los mismos.',
        'Las entregas serán en la fecha pautada.',
        'Las programaciones se entregarán testeadas, las mismas pueden presentar errores propios de cualquier programación, los mismos serán corregidos en el momento de su conocimiento, sin costo por el término de tres (3) meses a partir de la entrega final.',
        'Los trabajos serán realizados y entregados en nuestros servidores sin costo adicional.',
        'No realizamos trabajos en servidores externos, salvo pedido expreso del cliente con un costo que será pautado al momento de iniciar el trabajo.'
    ];
    
    foreach ($terminos as $termino) {
        $pdf->Cell(5, 5, utf8_decode(''), 0, 0, 'L');
        $pdf->Cell(5, 5, utf8_decode('*'), 0, 0, 'L');
        $pdf->MultiCell(0, 5, utf8_decode($termino));
        $pdf->Ln(1);
    }
    
    // Salida del PDF
    $filename = 'Presupuesto_' . $presupuesto['codigo'] . '.pdf';
    $pdf->Output('I', $filename);

} catch (Throwable $e) {
    error_log('[presupuestos/generar_pdf] ' . $e->getMessage());
    die('Error generando PDF: ' . $e->getMessage());
}