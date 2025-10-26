<?php
try{
    include($_SERVER['DOCUMENT_ROOT']."/inc/conect.php");
    //require('fpdf.php');
   // date_default_timezone_set('America/Argentina/Buenos_Aires');
    //print_r($_REQUEST);
    $D        = $_REQUEST['A'] ?? null;
    if($D):
        $Doc    = $USERS->listar('codigo,documento','documentos',"WHERE id = {$D}");
        $J_Doc[$Doc[0]['codigo']]  = $Doc[0]['documento'];
        $W      = "WHERE id = '";
    else:
        $P        = $_REQUEST['P'] ?? null;    
        $RCa      = $USERS->full_list($_GET['T'],"WHERE paciente = {$P}");
        $J_Doc    = json_decode($RCa[0]['documentacion'],true);
        $W      = "WHERE codigo = '";
    endif;
    
    require('Write2HTML.php');
    
   foreach($J_Doc as $k => $doc):  
    $documento  = $doc;
    $RC         = $USERS->full_list('documentos',$W.$k."'");
    $cadathumb	= glob("../../../assets/img/logos/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
    
//print_r($documento);die;
    $pdf = new PDF('P','mm','A4');
        if($cadathumb){
            $pdf->logo  = $cadathumb[0];
        }else{
            $pdf->logo  = "../../img/logo/orion.png";
        }

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
    $pdf->Ln(3);
    $pdf->Cell(0,6,utf8_decode($RC[0]['titulo']),0,1,'C',0);
    $pdf->Ln(3);

    // Subtítulo
    $pdf->SetFont('Arial','',12);
    $pdf->WriteHTML(utf8_decode($RC[0]['subtitulo']));

    // Contenido del documento
    $pdf->Ln(3);
    $pdf->SetFont('Arial','',11);
    $pdf->WriteHTML(utf8_decode($documento));

    // Generar PDF

   endforeach;    
    $pdf->Output();

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