<?php
session_start();
include("../inc/conect.php");
date_default_timezone_set('America/Argentina/Buenos_Aires');
$get        = $_GET['pedido'];
// DATA = administradora|usuario
$data       = explode('|',$_GET['data']);
$abc        = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P");
$min_abc    = array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p");

$N_A        = $CNSLTS->full_list('orion_empresas',"WHERE id = 3");
$N_U        = $CNSLTS->full_list('orion_members',"WHERE id = 4");
$PD         = $CNSLTS->full_list('pedidos',"WHERE id = $get");

//$deco 		= json_decode($PD[0]['pedido'],true);
$deco = json_decode(utf8_encode($PD[0]['pedido']),true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'Error decodificando JSON: ' . json_last_error_msg();
}

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
$pdf->SetDrawColor(179,49,0);
$pdf->SetFillColor(255,131,5);
$pdf->SetTextColor(255,255,255);
$pdf->SetLineWidth(0.5);
$pdf->Image('../img/logo/distrinsumos.jpg',10,3,10);
$pdf->Ln(5);
$pdf->Cell(0,7,utf8_decode($N_A[0]['empresa']),'T L R B',1,'C',1);
$pdf->SetFont('Arial','B',8);
//$pdf->Cell(0,6,utf8_decode($N_A[0]['direccion']).' - telefono: '.utf8_decode($N_A[0]['telefono']).' - email: '.utf8_decode($N_A[0]['email']),'L R B',1,'C',1);
/////////////////////////// Datos /////////////////

$pdf->Ln(5);
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->Cell(120,8,utf8_decode('Remito '.$PD[0]['codigo']),0,0,'L');
$pdf->Cell(60,8,utf8_decode('Fecha '.$GNRLS->fechaes($PD[0]['fecha'])),0,0,'R');
$pdf->Ln(10);
$pdf->Cell(60,8,utf8_decode('Cliente '.$PD[0]['nombre']),'B',0,'L');
$pdf->Cell(50,8,utf8_decode('Cuit '.$PD[0]['cuit']),'B',0,'C');
$pdf->Cell(80,8,utf8_decode('Dirección '.$PD[0]['direccion']),'B',0,'R');

/////////////////////////// Pedido /////////////////
$pdf->Ln(10);
$pdf->SetFont('Arial','B',10);
$pdf->SetLineWidth(0.4);
$pdf->Cell(20,8,'Cant','B',0,'L');
$pdf->Cell(110,8,utf8_decode('Artículo'),'B',0,'C');
$pdf->Cell(30,8,'Unitario','B',0,'R');
$pdf->Cell(30,8,'Sub total','B',0,'R');
$pdf->Ln(10);

$i = 0;
foreach($deco['articulo'] as $each => $key){
	$cant 		= $deco['cantidad'][$each];
	$precio 	= $deco['precio'][$each];
	$tp 		= $deco['tparcial'][$each];
//	$art 		= $CNSLTS->listar('codigo,sku,articulo,stock','articulos',"WHERE id = '".$each."'");
	
$pdf->SetFont('Arial','',10);
$pdf->SetLineWidth(0.1);
$pdf->Cell(20,8,$cant,'B',0,'L');
$pdf->Cell(110,8,utf8_decode($key),'B',0,'L');
$pdf->Cell(30,8,number_format($precio,2,',','.'),'B',0,'R');
$pdf->Cell(30,8,number_format($tp,2,',','.'),'B',0,'R');
$pdf->Ln(8);
	
++$i; 
}
$pdf->Ln(15);
$pdf->SetLineWidth(0.4);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,utf8_decode('Total $'.number_format($PD[0]['total'],2,',','.')),1,0,'R');

$pdf->Output();

/*
$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->Cell(0,6,utf8_decode('Edifico '.$N_E[0]['edificio'].' '.$N_E[0]['direccion'].' '.$N_E[0]['numero'].' - '.$N_E[0]['localidad']),' L R',1,'L',1);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,7,utf8_decode($N_U[0]['nombre'].' '.$N_U[0]['apellido'].' '.$N_U[0]['dni'].' U.F. '.$get[2].' Piso / Lote '.$N_U[0]['piso_lote'].' Depto / Casa '.$N_U[0]['depto_casa'].' - Periodo '.$get[0]),'B L R',1,'L',1);
$pdf->Ln(1);


$pdf->SetFont('Arial','',12);
$pdf->SetDrawColor(67,154,63);
$pdf->SetFillColor(203,255,198);
$pdf->SetTextColor(67,154,63);
$pdf->Cell(50,6,'',0,0,'C');
$pdf->Cell(90,6,utf8_decode('PAGADO'),1,0,'C',1);
$pdf->Cell(50,6,'',0,0,'C');
$pdf->Ln(5);



$pdf->SetFont('Arial','',8);
$pdf->SetDrawColor(153,153,153);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(120,8,utf8_decode('Saldo anterior:'),0,0,'L');
$pdf->Cell(60,8,utf8_decode('$'.number_format($Q_L[0]['saldo_anterior'],2,'.','')),0,0,'R');
$pdf->Ln(5);
$pdf->Cell(120,8,utf8_decode('Pagos:'),0,0,'L');
$pdf->Cell(60,8,utf8_decode('$'.number_format($Q_L[0]['pago'],2,'.','')),0,0,'R');
$pdf->Ln(5);
$pdf->Cell(120,8,utf8_decode('Intereses:'),0,0,'L');
$pdf->Cell(60,8,utf8_decode('$'.number_format($int ,2,'.','')),0,0,'R');
$pdf->Ln(5);
        $gral = $CNSLTS->listar('valor','generales_edificio',"WHERE edificio = '".$get[1]."' AND id_gral = 4");
        $wL   = round(100/$gral[0]['valor']);
        $ttgg = 0;
        for($i=0; $i < $gral[0]['valor'];$i++){
            $pdf->Cell(120,8,utf8_decode('Gastos '.$abc[$i].':'),0,0,'L');
            $pdf->Cell(60,8,'$'.number_format($decode[$min_abc[$i]],2,'.',''),0,0,'R');
            $pdf->Ln(5);
            $ttgg += $decode[$min_abc[$i]];
        }
//echo $COM_homeapp.'|'.$decode['cargos'].'|'.$Q_L[0]['saldo_anterior'].'|'.$decode['extraordinarias'].'|'.$int.'|'.$ttgg;
$ttee = ($COM_homeapp)+($Q_L[0]['monto'])+($Q_L[0]['saldo_anterior'])+($int);
$ttee = round($ttee);
$ttee = number_format($ttee,2,'.','');

$pdf->Cell(120,8,utf8_decode('Extraordinarias:'),0,0,'L');
$pdf->Cell(60,8,'$'.number_format($decode['extraordinarias'],2,'.',''),0,0,'R');
$pdf->Ln(5);
$pdf->Cell(120,8,utf8_decode('Fondo Reserva:'),0,0,'L');
$pdf->Cell(60,8,utf8_decode('$'.number_format($decode['fondo_reserva'],2,'.','')),0,0,'R');
$pdf->Ln(5);
$pdf->Cell(120,8,utf8_decode('Particulares:'),0,0,'L');
$pdf->Cell(60,8,'$'.number_format($decode['cargos'],2,'.',''),0,0,'R');
$pdf->Ln(5);
$pdf->Cell(120,8,utf8_decode('Zenrise:'),0,0,'L');
$pdf->Cell(60,8,utf8_decode('$'.number_format($COM_homeapp,2,'.','')),0,0,'R');
$pdf->Ln(10);

$pdf->SetFont('arial','B',10);
$pdf->Cell(120,10,utf8_decode('Total a Pagar:'),0,0,'L');
$pdf->Cell(60,10,'$'.$ttee,0,0,'R');
$pdf->Ln(8);
$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(153,153,153);
$pdf->Cell(190,5,'----------------------------------------------------------------------------------------------------------------------------------------------',0,0,'C');
$pdf->Ln(10);

// DETALLE DEL PAGO A PARCIAL
if($Q_L[0]['id_pago'] < 0){
        
 //echo'<pre>';print_r($deuda);echo'</pre>';
$pdf->SetFont('Arial','',12);
$pdf->SetDrawColor(168,84,11);
$pdf->SetFillColor(255,207,124);
$pdf->SetTextColor(168,84,11);
$pdf->Cell(180,8,'Detalle del link de pago '.($Q_L[0]['id_pago']*-1),'T B L R',0,'C',1);
$pdf->Ln(10);
$pdf->SetFont('Arial','',10);
$pdf->SetDrawColor(153,153,153);
$pdf->SetFillColor(250,250,250);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(90,6,'Periodo','T B L',0,'C',1);
$pdf->Cell(90,6,'Monto','R B T',0,'C',1);
$pdf->SetFont('Arial','',8);
$pdf->Ln(10);
    foreach($deuda as $Kper => $Vper){
        $pdf->Cell(90,4,$Kper,'B',0,'L',1);
        $pdf->Cell(90,4,$Vper['total'],'B',0,'R',1);
        $pdf->Ln(10);
    }
$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(190,5,'----------------------------------------------------------------------------------------------------------------------------------------------',0,0,'C');
$pdf->Ln(10);
}
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(0,0,0);
//$pdf->Cell(60,10,utf8_decode('Nombre:'.$GBL_E[0]['edificio']),0,0,'L');
$pdf->Cell(60,8,'Fecha','T B L',0,'C',1);
$pdf->Cell(60,8,utf8_decode('Operación'),'T B',0,'C',1);
$pdf->Cell(60,8,'Monto','R B T',0,'C',1);

$pdf->Ln(10);
$pdf->Cell(60,8,$Q_L[0]['fecha_pago'],'B',0,'L');
$pdf->Cell(60,8,$Q_L[0]['id_pago'],'B',0,'L');
$pdf->Cell(60,8,'$'.$Q_L[0]['pago'],'B',0,'R');
if($ttee != $Q_L[0]['pago']){
    $saldo = $ttee - $Q_L[0]['pago'];
$pdf->Ln(10);
$pdf->SetFont('Arial','',10);
$pdf->Cell(120,8,'Saldo:','B',0,'L');
$pdf->Cell(60,8,'$'.number_format($saldo,2,'.',''),'B',0,'R');
    
}
$pdf->Output();
//$pdf->Output('D',$_GET['periodo'].'_'.$_GET['data'].'.pdf','');
//echo'<pre>';print_r($N_E);echo'</pre>';
*/
?>