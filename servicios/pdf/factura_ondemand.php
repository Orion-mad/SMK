<?
include('../../inc/conect.php');
try{
require('fpdf.php');
require('../phpqrcode/qrlib.php');
    $_FC = $USERS->full_list("facturacion","WHERE id  = {$_GET['F']}");
    $_FC = $USERS->queryToArray($_FC);
    $FC_det = json_decode($_FC['concepto']);
    //die;
    $cadathumb	= glob("../../img/logo/empresa/".$GBL_U[0]['codigo']."/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);

////    $_PC = $USERS->listar("sesiones","pacientes_carpetas","WHERE paciente  = {$_GET['factura']} AND prestacion = {}");
////    $_PC = $USERS->queryToArray($_PC);
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
      "codAut" => (int)$_FC['cae']
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
    public $direccion;
    public $empresa;
    public $ptv;
    public $cmp;
    public $cae;
    public $venceCae;
    public $tf;
    public $aliCuota;
    
    function Header()
    {
        if($_SESSION['facturacion']['PTOVTA'] == 1){
        // 1. Logo
        $this->Image($this->logo, 10, 5, 28); // Logo a la izquierda
        }

        // 2. Nombre de la empresa y datos centrales
        $this->SetY(10); // Ajusta la altura vertical
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(150, 8, utf8_decode($this->empresa), 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $this->Cell(150, 5, utf8_decode($this->direccion).' - Buenos Aires', 0, 1, 'C');
        $this->Cell(150, 5, utf8_decode('Condición frente al IVA: Responsable Inscripto'), 0, 1, 'C');

        // 3. Tipo de factura y datos a la derecha
        $this->SetXY(150, 10); // Posición para alinear a la derecha
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(50, 5, 'FACTURA '.$this->tf, 0, 2, 'R');

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(50, 5, 'Fecha: '.$this->fecha, 0, 2, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(50, 5, 'CUIT: '.$this->cuit, 0, 2, 'R');
        $this->Cell(50, 5, 'IIBB: '.$this->iibb, 0, 2, 'R');
        $this->Cell(50, 5, 'Comprobante Nro: '.$this->ptv.'-'.$this->cmp, 0, 2, 'R');

        // Línea horizontal al final del header
        $this->Ln(5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
    }


    function Footer()
    {
        $this->SetY(-60);
        $this->Cell(120, 8, 'IVA', 'T B');
        $this->Cell(10, 8, ' ', 'T B');
        $this->Cell(30, 8, $this->aliCuota.' ', 'T B');
        $this->Cell(30, 8, '$'.number_format($this->iva,2,',','.'), 'T B', 1, 'R');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(160, 8, 'TOTAL', 'B');
        $this->Cell(30, 8, '$'.number_format($this->ttf,2,',','.'), 'B', 1, 'R');
        
        $this->Ln(10);

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 8, 'CAE: '.$this->cae, 0, 1);
        $this->Cell(0, 8, 'Fecha de Vto. de CAE: '.$this->venceCae, 0, 1);
        $this->Cell(0, 8,utf8_decode('Comprobante autorizado por la AFIP'), 0, 1);

        $this->Image('qr.png', 170, $this->GetY() - 30, 30);
    }
}
if($_FC['sesiones'] > 1) $unidades   = $_FC['sesiones'];
else $unidades   = 1;
$fecha      = $GUSERS->fechaes($_FC['fecha']);
$tf         = $_FC['tipo_factura'];
if($tf == 'B'):
    $parcial    = $_FC['total_facturado']/$unidades;
    $total      = $_FC['total_facturado'];
    $iva        = 0;
    $ttf        = $_FC['total_facturado'];
else:
    $parcial    = $FC_det->ImpNeto/$unidades;
    $total      = $FC_det->ImpNeto;
    $iva        = $FC_det->ImpIVA;
    $ttf        = $FC_det->ImpTotal;
endif;
$cae        = $_FC['cae'];
$venceCae   = $_FC['cae_vence'];
foreach ($_SESSION['ga'] as $item) {
            if ($item->Id == $FC_det->Iva->AlicIva[0]->Id) {
                $idIva =  $item->Id;
                $aliCuota = $item->Desc;
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
$pdf->ptv  = $ptv;
$pdf->cmp  = $cmp;
$pdf->cae  = $cae;
$pdf->venceCae  = $venceCae;
$pdf->tf  = $tf;
$pdf->aliCuota = $aliCuota;
$pdf->iva = $iva;
$pdf->ttf = $ttf;
$pdf->fecha = $fecha;
$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

// Cliente
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Cliente', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 5, 'Nombre:'.$_FC['razon_social'], 0, 1);
$pdf->Cell(100, 5, 'CUIT: '.$FC_det->DocNro, 0, 1);
$pdf->Cell(100, 5,  utf8_decode('Condición IVA: Resp. Inscripto'), 0, 1);
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
$pdf->MultiCell(115, $alto, utf8_decode(strip_tags($FC_det->Detalle)), 0);
$yAfter = $pdf->GetY();
$alturaUsada_b = $yAfter;
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
$pdf->Output('I',$_GET['nombre'],'');

} catch(Exception $e) {
echo'<pre class="border-danger border-start border-end border-3 p-2 rounded-2 bg-opacity-25 bg-danger mt-2 w-100 h-auto">';print_r($e->getMessage());echo'</pre>';
}

?>