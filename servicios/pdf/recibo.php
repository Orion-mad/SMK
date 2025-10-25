<?php
include("../inc/conect.php");
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

$N_A   = $GNRLS->full_list('empresas',"WHERE id = {$_SESSION['ACCOUNT']}");
$N_U   = $GNRLS->full_list('members',"WHERE id = 1");
$prv   = $GNRLS->full_list('provincias',"WHERE id = {$N_A[0]['provincia']}");
$loc   = $GNRLS->full_list('localidades',"WHERE id = {$N_A[0]['localidad']}");

if($tp == 'contrato'){
    $inquilinos  = [];
    $RC         = $USERS->full_list('cobros',"WHERE id = {$get}");
    $CNTR       = $USERS->full_list('alquiler',"WHERE codigo = '{$RC[0]['contrato']}'");
    $J_i_cntr   = json_decode($CNTR[0]['inquilino'],true);
    
    //inquilinos
    foreach($J_i_cntr as $each_i):
    $INQ       = $USERS->full_list('inquilinos',"WHERE id = '{$each_i}'");
    $inquilinos[$INQ[0]['id']]  = $INQ[0]['nombre'].' '.$INQ[0]['apellido'];
    endforeach;
    
    //propietarios
    //
    $carpeta_destino    = '../servicios/pdf/alquileres/';
    $nombre_archivo     = $RC[0]['contrato'].'-'.$RC[0]['cuota'];
    //$nombre_js          = json_decode($RC[0]['inquilino'],true);
}elseif($tp == 'propietario'){
}else{
    $RC         = $USERS->full_list('recibos',"WHERE id = {$get}");
}
    $nyai = '';
    foreach($inquilinos as $ki => $each_i):
        $CL         = $USERS->full_list('inquilinos',"WHERE id = {$ki}");
        $nyai       .= $CL[0]['nombre'].' '.$CL[0]['apellido'].' - ';
    endforeach;
    $nyai = trim($nyai,' - ');
    $encabezado = '';
    
    
//echo'<pre>inquilino<br>';print_r($inquilinos);echo'</pre>';
//echo'<pre>cobro<br>';print_r(json_decode($RC[0]['detalle'],true));echo'</pre>';
//echo'<pre>inquilino<br>';print_r($N_A);echo'</pre>';
    
//die;

require('WriteHTML.php');

class PDF extends FPDF
{
    function CreateReceipt($x, $y)
    {
        /////////////////////////// tTop /////////////////
        //$pdf->Image('../img/logo/logo.png', 10, 10, 200, 200);
        $this->SetXY($x, $y);
        $this->SetFont('Arial','B',12);
        $this->SetDrawColor(0,0,0);
        $this->SetFillColor(255,255,255);
        $this->SetTextColor(0,0,0);
        $this->SetLineWidth(0.5);
        $this->Image('../img/logo/logo.png',32,2,12);
        //$this->Ln(8);
        $this->SetX(90);

        $this->SetFont('Arial','',20);
        $this->Cell(20,3,'X',0,0,'C',1);

        $this->SetFont('Arial','B',14);
        $this->WriteHTML(utf8_decode($RC[0]['concepto']));

        $this->Cell(100,5,utf8_decode('Rendición de Cobranza'),0,1,'C',1);
        $this->SetFont('Arial','B',10);
        $this->SetX(132);
        $this->Cell(40,5,utf8_decode('N° 0002 - 00004045'),0,1,'L',1);
        $this->SetX(132);
        $this->Cell(40,5,date("d-m-Y"),0,0,'L',1);

        $this->Ln(2);
        $this->SetFont('Arial','B',12);
        $this->Cell(70,-5,html_entity_decode($N_A[0]['empresa'], ENT_QUOTES, 'UTF-8'),0,0,'L',1);
        $this->SetTextColor(0,0,0);
        $this->SetFont('Arial','B',8);
        $this->Cell(40,-5,"no valido como factura",0,0,'C',1);


        $this->Ln(5);
        $this->SetFont('Arial','',8);
        $this->SetFillColor(255,255,255);
        $this->SetTextColor(0,0,0);
        $this->SetLineWidth(0.5);
        $this->Cell(0,5,utf8_decode($N_A[0]['direccion']).'  '.$prv[0]['nombre'].' '.$loc[0]['nombre'],0,1,'L',0);
        $this->Cell(0,5,'telefono: '.$N_A[0]['telefono'],0,1,'L',0);
        $this->Cell(0,-5,$N_A[0]['iva'].' - cuit: '.$N_A[0]['cuit'],0,1,'R',0);
        $this->Cell(0,-5,'IIBB: '.$N_A[0]['iibb'].' | inicio actividad '.$GUSERS->fechaes($N_A[0]['inicio_actividades']),0,1,'R',0);
        //$this->Cell(0,12,'','B',1,'L',0);

        $this->Ln(10);
        $this->SetFont('Arial','',10);
        $this->Cell(120,8,'RINDO POR CUENTA DE TERCEROS',1,0,'L',0);
        $this->Cell(70,8,'Carpeta '.$CNTR[0]['carpeta'],1,1,'L',0);

        $this->Cell(95,5,'Locador: ','L R',0,'L',0);
        $this->Cell(95,5,'Locatario: ','L R',1,'L',0);
        $this->Cell(95,5,'Localidad: ','L R',0,'L',0);
        $this->Cell(95,5,'Localidad:  '.$CNTR[0]['carpeta'],'L R',1,'L',0);
        $this->Cell(95,5,'CUIT: ','L R B',0,'L',0);
        $this->Cell(95,5,'CUIT: ','L R B',1,'L',0);

        $this->Ln(1.5);
        $this->Cell(190,6,utf8_decode('Recibi de '.$N_A[0]['contacto'].' los conceptos que se detallan a continuación cuota: '.$RC[0]['cuota']),1,1,'L',0);

        $this->Ln(1.5);
        $this->SetFont('Arial','B',10);
        $this->Cell(120,5,'Concepto','T L B',0,'C',1);
        $this->Cell(70,5,'Cobrado',1,1,'C',0);

        $this->SetFont('Arial','',10);
        $detalle = json_decode($RC[0]['detalle'],true);
        foreach($detalle as $k => $v):
        if(is_array($v)):
                $this->Cell(120,5,'Servicios entregados','L R ',0,'L',0);
                $this->Cell(70,5,'','L R',1,'R',0);
            foreach($v as $vb):
                $mvb .= $vb." - ";
            endforeach;
                $this->MultiCell(190,5,$mvb,'L R ','L');
               // $this->Cell(70,8,'',1,1,'R',0);
        else:
            $this->Cell(120,5,$k,'R L',0,'L',0);
            $this->Cell(70,5,number_format($v,2,',','.'),'L R',1,'R',0);
        endif;
        endforeach;

        $this->Ln(1.5);
        $this->SetFont('Arial','B',10);
        $this->Cell(120,8,'Total',0,0,'R',1);
        $this->Cell(70,8,'$'.number_format($RC[0]['monto'],2,',','.'),1,1,'R',0);
        $this->Ln(1.5);
        $this->Cell(190,8,'Son pesos: '.$GUSERS->num2letras($RC[0]['monto'], true, true),0,1,'L',1);
        $this->Ln(2);
        $this->SetX(130);

        $this->Cell(60,5,'Firma: ..................................................',0,0,'L',1);
    }
}


/// total de linea 190
// Creación del objeto de la clase heredada
    $pdf = new PDF('P','mm','A4');
    $pdf->SetAuthor  ='M@D';
    $pdf->SetCreator  ='Sysmika';
    //$pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->CreateReceipt(10, 10);
    $pdf->Output($carpeta_destino . $nombre_archivo.'.pdf', 'F');
    $pdf->Output();






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

/*
$pdf->Output();
//echo'<pre>';print_r($N_E);echo'</pre>';
*/
?>