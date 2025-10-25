<?php
session_start();
include("../../inc/conect.php");
date_default_timezone_set('America/Argentina/Buenos_Aires');
if (isset($_GET['d'])) {
    // Obtener y decodificar los datos
    $encodedData = $_GET['d'];
    $decodedData = json_decode(base64_decode($encodedData), true);

    // Mostrar los datos decodificados
    if ($decodedData) {
             $tp         = htmlspecialchars($decodedData['tipo']);
             $get        = htmlspecialchars($decodedData['id']);
    } else {
        echo "Error al decodificar los datos.";
    }
} else {
    echo "No se recibieron datos.";
}

if($tp == 'tipo_contrato'):
    $RC         = $USERS->full_list('tipos_contratos',"WHERE id = {$get}");
endif;
    
//echo'<pre>';print_r($RC);echo'</pre>';
//echo'<pre>cobro<br>';print_r(json_decode($RC[0]['detalle'],true));echo'</pre>';
//echo'<pre>inquilino<br>';print_r($N_A);echo'</pre>';
    
//die;

require('WriteHTML.php');
/// total de linea 190
// Creación del objeto de la clase heredada
$pdf = new PDF('P','mm','Letter');
$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial','',10);
$pdf->SetLineWidth(0.1);
$pdf->WriteHTML(utf8_decode($RC[0]['contrato']));
$pdf->Ln(8);

/*////////////////////////// Datos /////////////////

$pdf->Ln(8);
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->Cell(60,8,utf8_decode('Cliente: '.$CL[0]['nombre'].' '.$CL[0]['apellido']),'T B',0,'L');
$pdf->Cell(50,8,utf8_decode('Cuit: '.$CL[0]['cuit']),'T B',0,'C');
$pdf->Cell(80,8,utf8_decode('Dirección: '.$CL[0]['direccion']),'T B',0,'R');

/////////////////////////// Pedido /////////////////
$pdf->Ln(12);
$pdf->SetFont('Arial','B',10);
$pdf->SetLineWidth(0.4);
$pdf->Cell(0,8,'Detalle','B',0,'L');
$pdf->Ln(10);

	
$pdf->SetFont('Arial','',10);
$pdf->SetLineWidth(0.1);
$pdf->WriteHTML(utf8_decode($RC[0]['concepto']));
$pdf->Ln(8);
	
$pdf->Ln(15);
$pdf->SetLineWidth(0.4);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,('Total $'.number_format($RC[0]['monto'],2,',','.')),1,0,'R');

// libre de culpa y cargo
$pdf->Ln(15);
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,8,('Documento no valido como factura | impreso en la web | s.e.u.o.'),0,0,'L');


///////////////////////////////////////////////////////////*/
$carpeta_destino = 'documentos/contratos_temporarios/';
$nombre_archivo = 'contrato_'.$RC[0]['codigo'];


    $pdf->Output($carpeta_destino.$nombre_archivo.'.pdf', 'F');
    $pdf->Output();
/*
$pdf->Output();
//echo'<pre>';print_r($N_E);echo'</pre>';
*/
?>