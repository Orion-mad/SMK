<?php
include("../../inc/conect.php");
date_default_timezone_set('America/Argentina/Buenos_Aires');
$HLP         = $USERS->full_list('help',"GROUP BY modulo ORDER BY item");
$nombre_archivo = 'SISTEMA ASSISTIRE: MANUAL DE PROCEDIMIENTOS'; 
$item    = '';
$modulo  = '';


  ksort( $_SESSION['ACCESO'] );
  foreach ( $_SESSION['ACCESO'] as $KEY_a => $VALa ) {
    $modulo .= '<p>';
        if ( $KEY_a != 'Config' ) {
          $V_ARR = explode( '-', $KEY_a );
        $modulo .= '# '.$V_ARR[1].' > ';   
          foreach ( $VALa as $KEY => $VAL ) {
              $modulo.=$VAL.', ';
          }
        }
    $modulo .= '</p>';
  }

//print_r($modulo);

require('WriteHTML.php');

$pdf = new PDF('P','mm','A4');
$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->SetTextColor(28,71,130);
$pdf->AddPage();
$pdf->AddFont('DejaVuSans-Bold', '', 'DejaVuSans-Bold.php');
$pdf->AddFont('DejaVuSans', '', 'DejaVuSans.php');
$pdf->SetFont('DejaVuSans-Bold','',12);
    $ancho_pagina = $pdf->GetPageWidth();
    $alto_pagina  = $pdf->GetPageHeight();
    $pdf->Image('../../img/caratula-orion.png', 0, 0, $ancho_pagina, $alto_pagina);

    // Calcular la posición al 10% desde la parte superior
    $margen_superior = $alto_pagina * 0.80; // 80% del alto
    $posicion_y = $margen_superior; // Posición vertical del texto

    // Configurar el texto
    $pdf->SetFont('DejaVuSans', '', 14); // Fuente
    $pdf->SetXY(10, $posicion_y); // Posición horizontal y vertical
    $pdf->Cell(0, 10, utf8_decode($nombre_archivo), 1, 1, 'C');
    $pdf->AddPage();
/*
$pdf->SetFont('Arial','',10);
$pdf->SetLineWidth(0.1);
$pdf->WriteHTML(utf8_decode($RC[0]['contrato']));
$pdf->Ln(8);
*/
$cus = count($HLP);
$ics = 1;
$ki = 1;

foreach ($HLP as $row) {
    // Si cambia el módulo, mostramos un nuevo título
    
 //echo'<pre>';   print_r($row);echo'</pre>';
    
        $modulo_actual = ($row['modulo']);
    if ($row['modulo'] !== $modulo_actual) {
        if($modulo_actual == 'Home') $modulo_actual = 'Manual de Procedimientos';
        $pdf->SetFont('DejaVuSans', 'B', 14);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, strtoupper(($modulo_actual)), 0, 1);
    }
    $HLPi   = $USERS->full_list('help',"WHERE modulo = '{$row['modulo']}' ORDER BY item");
    $cu     = count($HLPi);
    $ic     = 1;
    foreach ($HLPi as $kki => $rowi) {
//print_r($rowi['texto']);echo'<hr>';
        // Mostrar el ítem
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Ln(2);
        $pdf->Cell(0, 10, utf8_decode(strtoupper($ki.'.'.$kki.'# '.$rowi['modulo']).' > '.strtoupper($rowi['item'])), 0, 1);
        
        
        $texto = html_entity_decode($rowi['texto']);
        // Mostrar el texto con HTML
        $pdf->SetFont('Arial', '', 11);
        $pdf->WriteHTML(utf8_decode($texto));
        if($row['modulo'] == 'home'):
            $pdf->Ln(12);
            $pdf->WriteHTML(utf8_decode($modulo));
        endif;    
    if($ic != $cu ) $pdf->AddPage();
    ++$ic;
    }
    
    $pdf->Ln(5);
    if($ics != $cus ) $pdf->AddPage();
    ++$ics;
    ++$ki;
}



///////////////////////////////////////////////////////////*/
    //$pdf->Output();
    $pdf->Output('I',$nombre_archivo.'.pdf','');


?>