<?
$EMP        = $_SESSION['facturacion'];
$DT         = $_SESSION['FacturaLote'];
$CNCPT      = $DT['Detalle'];
//echo'<pre>';print_r($DT);echo'</pre>';die;

$PRE       = $USERS->listar('nombre_corto','prestaciones',"WHERE id = {$DT['Prestacion']}");

$cadathumb = '';
if($EMP['PTOVTA'] == 1) $cadathumb = glob("../../img/logo/empresa/SYS-16029i/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
$carpeta_destino   = $_SERVER['DOCUMENT_ROOT'].'/facturas/'.$DT['OS'].'/';
if (!file_exists($carpeta_destino)) {
	@mkdir($carpeta_destino, 0777, true);
}
$nombre_archivo    = trim($DT['Paciente']).' '.trim($PRE[0]['nombre_corto']).' '.$DT['PtoVta'].' '.$DT['CbteDesde'];

$fechac = DateTime::createFromFormat('Ymd', $DT['CbteFch']);
$comp = $fechac->format('Y-m-d');
$datosQR = [
             "ver" => 1,
             "fecha" => $comp,
             "cuit" => $EMP['PTOVTA_CUIT'],
             "ptoVta" => (int)$DT['PtoVta'],
             "tipoCmp" => (int)$DT['Concepto'],
             "nroCmp" => (int)$DT['CbteDesde'],
             "importe" => (float)$DT['ImpIVA'],
             "moneda" => "PES",
             "ctz" => (float)1.00,
             "tipoDocRec" => (int)$DT['DocTipo'],
             "nroDocRec" => (int)$DT['DocNro'],
             "tipoCodAut" => "E",
             "codAut" => (string)$DT['CAE']
           ];
//echo'<pre>';print_r($datosQR);echo'</pre>';
$qrBase64   = base64_encode(json_encode($datosQR));
$urlQR      = "https://www.afip.gob.ar/fe/qr/?p=".$qrBase64;
QRcode::png($urlQR, 'qr.png', QR_ECLEVEL_L, 4);
$empresa    = $EMP['PTOVTA_RZ'];
$CAE        = $DT['CAE'];
$CAEFchVto  = $DT['CAEFchVto'];
//echo'<pre>';print_r($DT);echo'</pre>';
//die;

if($DT['Unidades'] > 1) $unidades   = $DT['Unidades'];
else $unidades   = 1;
$parcial    = $DT['ImpTotConc']/$unidades;
$total      = $DT['ImpTotConc'];
$ttf        = $DT['ImpTotConc'];
////////////////////////////////////////////
$pdf = new PDF('P','mm','A4');
if($cadathumb){
    $pdf->logo  = $cadathumb[0];
}else{
    $pdf->logo  = NULL;
}
$pdf->TipoFactura   = $DT['TipoFactura'];
$pdf->empresa   = $EMP['PTOVTA_RZ'];
$pdf->cuit      = $EMP['PTOVTA_CUIT'];
$pdf->iibb      = $EMP['PTOVTA_IIBB'];
$pdf->direccion = $EMP['PTOVTA_DIR'];
$pdf->inicioAct = $EMP['PTOVTA_ACT'];
$pdf->CAE       = $DT['CAE'];
$pdf->ttf       = $ttf;
$pdf->CAEFchVto = $GUSERS->fechaes($DT['CAEFchVto']);
$pdf->PtoVta    = $GUSERS->espacios_num($DT['PtoVta'],4);
$pdf->CbteDesde = $GUSERS->espacios_num($DT['CbteDesde'],8);
$pdf->fecha     = $GUSERS->fechaes($DT['Fecha']);;

$pdf->SetAuthor  ='M@D';
$pdf->SetCreator ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

// Cliente
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Cliente', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 5, 'Nombre: '.$DT['RazonSocial'], 0, 1);
$pdf->Cell(100, 5, $DT['Domicilio'], 0, 1);
$pdf->Cell(100, 5, 'CUIT: '.$DT['DocNro'], 0, 1);
$pdf->Cell(100, 5,  utf8_decode('Condición IVA: Resp. Inscripto'), 0, 1);
$pdf->Cell(100, 5,  utf8_decode('Fecha inicio actividad '.$pdf->inicioAct), 0, 1);
$pdf->Ln(3);

// Tabla de ítems
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(120, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
$pdf->Cell(10, 8, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Precio Unit.', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Importe', 1, 1, 'C', true);
$pdf->Ln(5);

// Items
$x = $pdf->GetX();
$y = $pdf->GetY();
$alto = 6;
$pdf->MultiCell(115, $alto, utf8_decode(strip_tags($DT['Detalle'])), 0);
$yAfter = $pdf->GetY();
$alturaUsada_b = $yAfter + 10;
$alturaUsada = 8;
$pdf->SetXY($x + 120, $y);
$pdf->Cell(10, $alturaUsada, $unidades, 0, 0, 'R');
$pdf->Cell(30, $alturaUsada, '$'.number_format($parcial,2,',','.'), 0, 0, 'R');
$pdf->Cell(30, $alturaUsada, '$'.number_format($total,2,',','.'), 0, 1, 'R');
$pdf->Ln($alturaUsada_b);
//$pdf->Cell(100, 8, utf8_decode($DT['detalle']), 1);
//$pdf->Cell(30, 8, '1', 1, 0, 'R');
//$pdf->Cell(30, 8, '$'.$DT['unitario'], 1, 0, 'R');
//$pdf->Cell(30, 8, '$'.$parcial, 1, 1, 'R');


//$pdf->Output();
$pdf->Output( 'F',$carpeta_destino . $nombre_archivo.'.pdf');
//$pdf->Output( 'I',$nombre_archivo.'.pdf');


?>