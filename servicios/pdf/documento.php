<?php
try{
    include($_SERVER['DOCUMENT_ROOT']."/inc/conect.php");
    //require('fpdf.php');
   // date_default_timezone_set('America/Argentina/Buenos_Aires');
    //print_r($_REQUEST);
    $F        = $_REQUEST['F'] ?? null;
    $D        = $_REQUEST['D'] ?? null;
    $RC       = $USERS->full_list('documentos',"WHERE id = {$D}");
    if($F):
        $SG   = $USERS->full_list('pacientes_ingresos',"WHERE id = {$F}");
    endif;
    $carpeta_destino    = $_SERVER['DOCUMENT_ROOT'].'/documentos/general';
    $nombre_archivo     = $RC[0]['titulo'];
    require('WriteHTMLdocs.php');
//print_r($RC);die;


// Tu código modificado
$pdf = new PDF('P','mm','A4');
$pdf->SetAuthor('M@D');
$pdf->SetCreator('Sysmika');
$pdf->AliasNbPages();


// Segunda página - Contenido
$pdf->AddPage();
$pdf->SetFont('Arial','',14);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);

// Título
$pdf->Cell(0,6,utf8_decode($RC[0]['titulo']),0,1,'C',0);
$pdf->Ln(3);

// Subtítulo
$pdf->SetFont('Arial','',12);
$pdf->WriteHTML(utf8_decode($RC[0]['subtitulo']));

// Contenido del documento
$pdf->SetFont('Arial','',11);
$pdf->WriteHTML(utf8_decode($RC[0]['documento']));

if($F_DEPRECATED)    {
    $pdf->Ln(8);
    $pdf->Image($_SERVER['DOCUMENT_ROOT'].'/documentos/firmas/firma_'.$D.'_'.$F.'.png', 80, null, 90, 0);
    $pdf->Ln(2);
    $pdf->Cell(250,6,utf8_decode($SG[0]['responsable']),0,1,'C',0);
}
    
    
// Generar PDF
$pdf->Output('I', $nombre_archivo.'.pdf', '');


    /*
    if($tp == 'imprime'){
        $pdf->Output();
    }elseif(($tp == 'presupuesto') or ($tp == 'todo')){
        $pdf->Output( 'F',$carpeta_destino . $nombre_archivo.'.pdf');
        $pdf->Output();
    }elseif($tp == 'save'){
        $pdf->Output($carpeta_destino . $nombre_archivo.'.pdf', 'F');
    }elseif($tp == 'caja'){
        $pdf->Output();
    }else{
        $pdf->Output('D','recibo.pdf','');
    }
    $pdf->Output();
    //echo'<pre>';print_r($N_E);echo'</pre>';
    */
} catch(Exception $e) {
    
    
    // Mostrar información completa del error
    echo '<div class="w-100 border-start border-3 border-warning bg-warning bg-opacity-10 p-2">Error: ' . utf8_decode($e->getMessage()) . '<br>';
    echo "Código: " . $e->getCode() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "</div>";
    
    // También registrar en log
    error_log("Error AFIP: " . $e->getMessage());
}
    
    
?>