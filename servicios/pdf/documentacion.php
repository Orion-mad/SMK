<?php
try {
    // Includes
    include $_SERVER['DOCUMENT_ROOT'] . "/inc/conect.php";
    require_once __DIR__ . '/fpdf.php';
    require_once __DIR__ . '/writeTablesIa.php'; // <-- Clase extendida

    // Inputs
    $P  = isset($_REQUEST['P']) ? (int)$_REQUEST['P'] : null;
    $D  = isset($_REQUEST['D']) ? $_REQUEST['D'] : null;
    // Datos
    if (!$D) {
        throw new Exception("Parámetro D (documento) es obligatorio.");
    }
    //$RC = $USERS->full_list('documentos', "WHERE id = {$D}");
    $RC = $USERS->full_list('pacientes_presupuestos', "WHERE id = {$P}");
    if (empty($RC)) {
        throw new Exception("No se encontró el documento ID={$P}.");
    }
//print_r($RC);
    $doc     = $RC[0];
    $titulo  = (string)($doc['titulo'] ?? 'Documento');
    $subt    = (string)($doc['subtitulo'] ?? '');
    $htmlTbl = (string)($doc['documento'] ?? '');

    $SG = [];
    if ($F) {
        $SG = $USERS->full_list('pacientes_ingresos', "WHERE id = {$F}");
    }

    // Nombre archivo
    $nombre_archivo = preg_replace('/[^\w\-]+/u', '_', $titulo);

    // Helper subtítulo: HTML -> texto con saltos
    $subt_text = trim(
        str_replace(
            ["<br>", "<br/>", "<br />"],
            "\n",
            strip_tags($subt, "<br>")
        )
    );

    // PDF
    $pdf = new PDF_Table('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAuthor('M@D');
    $pdf->SetCreator('Sysmika');
    $pdf->AliasNbPages();

    // Página
    $pdf->AddPage();

    // Título
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, utf8_decode($titulo), 0, 1, 'C');
    $pdf->Ln(2);

    // Subtítulo (MultiCell con salto)
    if ($subt_text !== '') {
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, utf8_decode($subt_text));
        $pdf->Ln(3);
    }

    // Tabla HTML (si hay contenido)
// Tabla HTML (respeta thead/tbody, colspan, align, width)
if ($htmlTbl !== '') {
    $pdf->SetBodyFont('Arial', '', 10);
    $pdf->SetHeaderFont('Arial', 'B', 10);
    $pdf->SetBorders('LRBT'); // bordes por defecto
    $pdf->WriteHTMLRichTable($htmlTbl, [
        'default_align' => 'L',
        'header_bold'   => true
    ]);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, utf8_decode('Sin contenido de tabla para este documento.'), 0, 1, 'L');
}

    // Firma (opcional si existe archivo)
    if ($F) {
        $firma_path = $_SERVER['DOCUMENT_ROOT'] . '/documentos/firmas/firma_' . $D . '_' . $F . '.png';
        if (is_file($firma_path)) {
            $pdf->Ln(8);
            // X centrado: Image(x, y, w). Si y=null, usa la actual.
            $x_center = ($pdf->GetPageWidth() - 90) / 2;
            $pdf->Image($firma_path, $x_center, null, 90, 0);
            $pdf->Ln(2);
            if (!empty($SG) && !empty($SG[0]['responsable'])) {
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 6, utf8_decode($SG[0]['responsable']), 0, 1, 'C');
            }
        }
    }

    // Salida
    $pdf->Output('I', $nombre_archivo . '.pdf');

} catch (Exception $e) {
    // Mostrar info del error
    echo '<div class="w-100 border-start border-3 border-warning bg-warning bg-opacity-10 p-2">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<br>';
    echo "Código: " . (int)$e->getCode() . "<br>";
    echo "Archivo: " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . "<br>";
    echo "Línea: " . (int)$e->getLine() . "<br>";
    echo "Stack trace: " . nl2br(htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')) . "</div>";

    // Log
    error_log("Error PDF Documentacion: " . $e->getMessage());
}
