<?
include('../../inc/conect.php');
require('fpdf.php');
require('../phpqrcode/qrlib.php');
    if($_SESSION['facturacion']['PTOVTA_PROD'] == 'producción'):
        $_TBL               = "notas_credito_arca";                       // TABLA DE LISTADO                      
    else:
        $_TBL               = "notas_credito";                       // TABLA DE LISTADO                      
    endif;

    $_FC = $USERS->full_list("$_TBL","WHERE id  = {$_GET['factura']}");
    $_FC = $USERS->queryToArray($_FC);
    $FC_det = json_decode($_FC['concepto']);
    //die;
    $cadathumb	= glob("../../img/logo/empresa/".$GBL_U[0]['codigo']."/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
try{
////    $_PC = $USERS->listar("sesiones","pacientes_carpetas","WHERE paciente  = {$_GET['factura']} AND prestacion = {}");
////    $_PC = $USERS->queryToArray($_PC);
    
//echo'<pre>';print_r($FC_det->Iva->AlicIva->Id);echo'</pre>';
//echo'<pre>';print_r($_FC);echo'</pre>';
//echo'<pre>';print_r($FC_det);echo'</pre>';
    
// Generar QR como antes
$fechac = DateTime::createFromFormat('Ymd', $FC_det->CbteFch);
$comp = $fechac->format('Y-m-d');
    $datosQR = [
      "ver" => 1,
      "fecha" => $comp,
      "cuit" => $_SESSION['facturacion']['PTOVTA_CUIT'],
      "ptoVta" => (int)$FC_det->PtoVta,
      "tipoCmp" => (int)$FC_det->CbteTipo,
      "nroCmp" => (int)$FC_det->CbteDesde,
      "importe" => (float)$FC_det->ImpTotal,
      "moneda" => "PES",
      "ctz" => (float)1.00,
      "tipoDocRec" => (int)$FC_det->DocTipo,
      "nroDocRec" => (int)$FC_det->DocNro,
      "tipoCodAut" => "E",
      "codAut" => (string)$_FC['cae']
    ];
//echo'<pre>';print_r($_FC);echo'</pre>';
//echo'<pre>';print_r($FC_det);echo'</pre>';
    $qrBase64   = base64_encode(json_encode($datosQR));
    $urlQR      = "https://www.afip.gob.ar/fe/qr/?p=".$qrBase64;
    QRcode::png($urlQR, 'qr.png', QR_ECLEVEL_L, 4);
    $empresa = $_SESSION['facturacion']['PTOVTA_RZ'];
    $ptv     = $GUSERS->espacios_num($FC_det->PtoVta,4);
    $cmp     = $GUSERS->espacios_num($FC_det->CbteDesde,8);

//echo'<pre>';print_r($_SESSION['facturacion']);echo'</pre>';
//echo'<pre>';print_r($_FC);echo'</pre>';
//echo'<pre>';print_r($FC_det);echo'</pre>';
//die;

class PDF extends FPDF
{
    public $logo;
    public $cuit;
    public $iibb;
    public $fecha;
    public $inicioAct;
    public $direccion;
    public $empresa;
    public $ptv;
    public $cmp;
    public $cae;
    public $venceCae;
    public $tf;
    public $tf2;
    public $ttf;
    public $aliCuota;
    
    function Header()
    {
        if($_SESSION['facturacion']['PTOVTA'] == 1){
        // 1. Logo
        $this->Image($this->logo, 95, 5, 28); // Logo a la izquierda
        }

        // 2. Nombre de la empresa y datos centrales
        $this->SetY(10); // Ajusta la altura vertical
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(150, 8, utf8_decode($this->empresa), 0, 1, 'L');

        $this->SetFont('Arial', '', 10);
        $this->Cell(150, 5, utf8_decode($this->direccion).' - Buenos Aires', 0, 1, 'L');
        $this->Cell(150, 5, utf8_decode('Condición frente al IVA: Responsable Inscripto'), 0, 1, 'L');

        // 3. Tipo de factura y datos a la derecha
        $this->SetXY(150, 10); // Posición para alinear a la derecha
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(50, 6, utf8_decode('Nota de Crédito '.$this->tf), 0, 2, 'R');
        if($this->tf2):
            $this->SetFont('Arial', '', 10);
            $this->Cell(50, 6, $this->tf2, 0, 2, 'R');
        endif;

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(50, 6, 'Fecha: '.$this->fecha, 0, 2, 'R');
        $this->Cell(50, 7, 'Comprobante Nro: '.$this->ptv.'-'.$this->cmp, 0, 2, 'R');
        //$this->Cell(50, 4, '', 0, 2, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(50, 5, 'CUIT: '.$this->cuit, 0, 2, 'R');
        $this->Cell(50, 5, 'IIBB: '.$this->iibb, 0, 2, 'R');
        $this->Cell(50, 5,'Fecha inicio actividad: '.$this->inicioAct , 0, 2, 'R');
        $this->Cell(50, 5, 'Condicion de Venta: Contado', 0, 2, 'R');

        // Línea horizontal al final del header
        $this->Ln(5);
        //8$this->Line(10, $this->GetY(), 200, $this->GetY());
    }


    function Footer()
    {
        $this->SetY(-65);
        $this->Cell(130, 8, 'Subtotal $', 'L T',0,'R');
        $this->Cell(30, 8, '$ ', 'T',0,'R');
        //$this->Cell(10, 8, ' ', 'T B',0);
        $this->Cell(30, 8, number_format($this->ImpNeto,2,',','.'), 'T R',1,'R');
        $this->Cell(130, 8, 'IVA', 'L',0,'R');
        //$this->Cell(10, 8, ' ', 'T B',0);
        $this->Cell(30, 8, $this->aliCuota.' $ ', '',0,'R');
        $this->Cell(30, 8, number_format($this->iva,2,',','.'), 'R', 1, 'R');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(160, 8, 'IMPORTE TOTAL $ ', 'L B',0,'R');
        $this->Cell(30, 8, number_format($this->ttf,2,',','.'), 'B R', 1, 'R');
        
        $this->Ln(1);
        $this->Cell(190, 6, utf8_decode($this->empresa), 1,1,'C');
        $this->Ln(31);
        $this->Image('qr.png', 10, $this->GetY() - 30, 30);
        //$this->Ln(25);
        $this->SetFont('Arial', 'B', 9);
        $this->SetY(-25);
        $this->Cell(30, 6, '', 0, 0, 'L');
        $this->Cell(160, 6, utf8_decode('Comprobante autorizado por ARCA - CAE N° ' . $this->cae . ' - Vto. CAE: ' . $this->venceCae . ' - S.E.U.O.'), 0, 1, 'L');
        
        //$this->Cell(0, 6, 'CAE: '.$this->cae, 0, 1);
        //$this->Cell(0, 6, 'Fecha de Vto. de CAE: '.$this->venceCae, 0, 1);
        //$this->Cell(0, 6,utf8_decode('Comprobante autorizado por ARCA  - S.E.U.O.'), 0, 1);
        $this->Cell(0,10,utf8_decode('Página '.$this->PageNo().'/{nb}'),0,0,'C');

    }
}
if($_FC['sesiones'] > 1) $unidades   = $_FC['sesiones'];
else $unidades   = 1;
$fecha      = $GUSERS->fechaes($_FC['fecha']);
$tf         = $_FC['tipo_factura'];
    
if($tf == 'B'):
    $parcial    = $_FC['total_facturado']/$unidades;
    $total      = $_FC['total_facturado'];
    $iva        = '0';
    $ttf        = $_FC['total_facturado'];
elseif($tf == 'FCE'):
    $parcial    = $FC_det->ImpNeto/$unidades;
    $total      = $FC_det->ImpNeto;
    $iva        = $FC_det->ImpIVA;
    $ttf        = $FC_det->ImpTotal;
    $tf        = 'A - FCE';
    $tf2        = utf8_decode('Factura de crédito electrónica');
elseif($tf == 'A'):
    $parcial    = $FC_det->ImpNeto/$unidades;
    $total      = $FC_det->ImpNeto;
    $iva        = $FC_det->ImpIVA;
    $ttf        = $FC_det->ImpTotal;
endif;
$cae        = $_FC['cae'];
$venceCae   = $_FC['cae_vence'];
$TR = 'IVA Sujeto exento';
foreach ($_SESSION['ga'] as $item) {
            if ($item->Id == $FC_det->Iva->AlicIva->Id) {
                $idIva =  $item->Id;
                $aliCuota = $item->Desc;
                if($item->Desc == '10.5%'){
                    $TR = 'IVA Responsable Inscripto';
                }
                break;
            }
        }

$pdf = new PDF('P','mm','A4');
if($cadathumb){
    $pdf->logo  = $cadathumb[0];
}else{
    $pdf->logo   = "";
}
$pdf->empresa    = $_SESSION['facturacion']['PTOVTA_RZ'];
$pdf->cuit       = $_SESSION['facturacion']['PTOVTA_CUIT'];
$pdf->iibb       = $_SESSION['facturacion']['PTOVTA_IIBB'];
$pdf->direccion  = $_SESSION['facturacion']['PTOVTA_DIR'];
$pdf->direccion  = $_SESSION['facturacion']['PTOVTA_DIR'];
$pdf->inicioAct  = $GUSERS->fechaes($_SESSION['facturacion']['PTOVTA_ACT']);
$pdf->ptv  = $ptv;
$pdf->cmp  = $cmp;
$pdf->cae  = $cae;
$pdf->venceCae  = $venceCae;
$pdf->tf  = $tf;
if($tf2):
    $pdf->tf2  = $tf2;
endif;
$pdf->aliCuota = $aliCuota;
$pdf->iva = $iva;
$pdf->ImpNeto = $total;
$pdf->ttf = $ttf;
$pdf->fecha = $fecha;
$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

// Periodo Facturado
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, utf8_decode('Per. facturado desde: '.$GUSERS->fechaArca($FC_det->FchServDesde)), 1, 0);
$pdf->Cell(65, 8, utf8_decode('Hasta: '.$GUSERS->fechaArca($FC_det->FchServHasta)), 'B T', 0);
$pdf->Cell(65, 8, utf8_decode('Fecha venc. para el pago: '.$GUSERS->fechaArca($FC_det->FchVtoPago)), 1, 1);
$pdf->Ln(3);
// Cliente
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 6, utf8_decode('Razón Social: '), 'T R L',1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 6, utf8_decode($_FC['razon_social']), 'L R', 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, utf8_decode('Dirección: '), 'L', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, utf8_decode($FC_det->Domicilio), '', 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, 'CUIT: ', '', 0,'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 6, $FC_det->DocNro, 'R', 1,'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6,  utf8_decode('Condición IVA: '), 'L B', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(160, 6,  utf8_decode($TR), 'B R', 1,'L');
$pdf->Ln(3);

// Tabla de ítems
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(120, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
$pdf->Cell(10, 8, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Precio Unit.', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Importe', 1, 1, 'C', true);
$pdf->Ln(5);
if(is_array($FC_det->Detalle)):
    
    $alturaDet = 0;
    foreach($FC_det->Detalle  as $av):
        // Ancho de cada columna
        $w1 = 110;  // Descripción
        $w2 = 20;  // Cantidad
        $w3 = 30;  // Precio unitario
        $w4 = 30;  // Subtotal

        $alto = 6; // Alto por línea de texto

        // Posición inicial
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // 1. Medir la altura que usará la descripción
        $tmpY = $pdf->GetY();
        $pdf->MultiCell($w1, $alto, utf8_decode(strip_tags($av[0])), 0);
        $alturaUsada = $pdf->GetY() - $tmpY;
        $alturaDet += 1;

        // 2. Volver a posición inicial para imprimir todo alineado
        $pdf->SetXY($x, $y);

        // 4. Posicionar columna 2 (Cantidad)
        $pdf->SetXY($x + $w1, $y);
        $pdf->Cell($w2, $alturaUsada, $av[1], 0, 0, 'R');

        // 5. Columna 3 (Precio Unitario)
        $pdf->Cell($w3, $alturaUsada, '$'.number_format($av[2], 2, ',', '.'), 0, 0, 'R');

        // 6. Columna 4 (Subtotal)
        $pdf->Cell($w4, $alturaUsada, '$'.number_format($av[2] * $av[1], 2, ',', '.'), 0, 1, 'R');

        // 7. Salto opcional entre filas
        $pdf->Ln(2);
    
          if ($alturaDet % 3 == 0) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(60, 8, utf8_decode('Per. facturado desde: '.$GUSERS->fechaArca($FC_det->FchServDesde)), 1, 0);
            $pdf->Cell(65, 8, utf8_decode('Hasta: '.$GUSERS->fechaArca($FC_det->FchServHasta)), 'B T', 0);
            $pdf->Cell(65, 8, utf8_decode('Fecha venc. para el pago: '.$GUSERS->fechaArca($FC_det->FchVtoPago)), 1, 1);
            $pdf->Ln(3);
            // Cliente
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(190, 6, utf8_decode('Razón Social: '), 'T R L',1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(190, 6, utf8_decode($_FC['razon_social']), 'L R', 1);

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(30, 6, utf8_decode('Dirección: '), 'L', 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(60, 6, utf8_decode($FC_det->Domicilio), '', 0);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(30, 6, 'CUIT: ', '', 0,'R');
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(70, 6, $FC_det->DocNro, 'R', 1,'L');

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(30, 6,  utf8_decode('Condición IVA: '), 'L B', 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(160, 6,  utf8_decode($TR), 'B R', 1,'L');
            $pdf->Ln(3);
              
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(120, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
            $pdf->Cell(10, 8, 'Cant.', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Precio Unit.', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Importe', 1, 1, 'C', true);
            $pdf->Ln(5);
          }
    
    endforeach;
    
else:
    // Items
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $alto = 6;
    $pdf->MultiCell(115, $alto, utf8_decode(strip_tags($FC_det->Detalle)), 0);
    $yAfter = $pdf->GetY();
    $alturaUsada_b = $yAfter;
    $alturaUsada = 8;
    $pdf->SetXY($x + 120, $y);
    $pdf->Cell(10, $alturaUsada, $unidades, 0, 0, 'R');
    $pdf->Cell(30, $alturaUsada, '$'.number_format($parcial,2,',','.'), 0, 0, 'R');
    $pdf->Cell(30, $alturaUsada, '$'.number_format($total,2,',','.'), 0, 1, 'R');
    $pdf->Ln($alturaUsada_b);
endif;
//$pdf->Cell(100, 8, utf8_decode($DT['detalle']), 1);
//$pdf->Cell(30, 8, '1', 1, 0, 'R');
//$pdf->Cell(30, 8, '$'.$DT['unitario'], 1, 0, 'R');
//$pdf->Cell(30, 8, '$'.$parcial, 1, 1, 'R');


//$pdf->Output();
if($_GET['nombre']?? null):
$pdf->Output('D',$_GET['nombre'].'.pdf','');
else:
$pdf->Output('I','archivo','');
endif;
    
}catch(Exception $e){
     print_r($e->getMessage(),true);
}

?>