<?php
session_start();
include("../inc/conect.php");;
date_default_timezone_set('America/Argentina/Buenos_Aires');
//print_r($_REQUEST);
$tp         = $_REQUEST['tipo'];
$get        = $_REQUEST['id'];
// DATA = administradora|usuario
$abc        = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P");
$min_abc    = array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p");

$N_A        = $CNSLTS->full_list('empresa',"WHERE id = 1");
$N_U        = $CNSLTS->full_list('members',"WHERE id = 1");
if($tp == 'inquilino'){
    $RC         = $CNSLTS->full_list('alquileres',"WHERE id = $get");
    $carpeta_destino    = '../servicios/docs/alquileres/';
    $nombre_archivo     = $RC[0]['id'];
}elseif($tp == 'propietario'){
    $RC         = $CNSLTS->full_list('pagos_mp',"WHERE id = $get");
    $carpeta_destino    = '../servicios/docs/avisos/';
    $nombre_archivo     = 'aviso '.$RC[0]['mpcod'];
    $RC[0]['concepto']  = $RC[0]['compra'];
}else{
    $RC         = $CNSLTS->full_list('recibos',"WHERE id = $get");
    
}
$CL         = $CNSLTS->full_list('clientes',"WHERE id = '".$RC[0]['cliente']."'");
$encabezado = '';

require('WriteHTML.php');
//echo'<pre>';print_r($deco);echo'</pre>';
/// total de linea 190
// Creación del objeto de la clase heredada
$pdf = new PDF('P','mm','A4');
$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

/////////////////////////// tTop /////////////////
//$pdf->Image('../img/logo/logo.png', 10, 10, 200, 200);
$pdf->SetFont('Arial','B',12);
$pdf->SetDrawColor(28,51,130);
$pdf->SetFillColor(28,51,130);
$pdf->SetTextColor(255,255,255);
$pdf->SetLineWidth(0.5);
$pdf->Image('../images/logo/logo.png',12,13,12);
$pdf->Ln(5);
$pdf->SetX(25);
$pdf->Cell(170,7,utf8_decode($N_A[0]['razonsoc']),0,1,'C',1);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','B',8);

$pdf->Ln(5);
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->Cell(0,6,utf8_decode($N_A[0]['direccion']).' - telefono: '.$N_A[0]['telef'].' -  cel: '.$N_A[0]['movil'].' - email: '.$N_A[0]['mail'],'L R B',1,'C',1);

/////////////////////////// Datos /////////////////

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


if($tp == 'imprime'){
    $pdf->Output();
}elseif($tp == 'save'){
    $pdf->Output($carpeta_destino . $nombre_archivo.'.pdf', 'F');
}elseif($tp == 'aviso'){
    $pdf->Output('D',$nombre_archivo.'.pdf', '');
}elseif($tp == 'caja'){
    $pdf->Output();
}else{
    $pdf->Output('D','recibo.pdf','');
}

/*
$pdf->Output();
//echo'<pre>';print_r($N_E);echo'</pre>';
*/
?>