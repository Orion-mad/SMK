<?
include('../../inc/conect.php');
require('fpdf.php');
require('../phpqrcode/qrlib.php');
$a_g        = explode('|',$_GET['key']);

$DT         = $_SESSION['lote_factura'][$a_g[0]][$a_g[1]];
//echo'<pre>';print_r( $DT);echo'</pre>';die;

$OS         = $USERS->full_list('os',"WHERE id = {$DT['id os']}");
$_PI = $USERS->full_list("pacientes_informes","WHERE paciente = {$DT['id paciente']} AND periodo = '".date("Y-m")."'");
//print_r($DT);

$CNCPT      = $DT['detalle'];
$CU         = "Cuit";
$CNP        = "Servicios";    
//die;
$cadathumb	= glob("../img/logo/empresa/".$GBL_U[0]['codigo']."/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);

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
//echo'<pre>';print_r($datosQR);echo'</pre>';
$qrBase64   = base64_encode(json_encode($datosQR));
$urlQR      = "https://www.afip.gob.ar/fe/qr/?p=".$qrBase64;
QRcode::png($urlQR, 'qr.png', QR_ECLEVEL_L, 4);
$empresa = $GBL_U[0]['empresa'];

class PDF extends FPDF
{
    public $logo;
    public $cuit;
    public $direccion;
    public $empresa;
    public $cae;
    public $venceCae;
    
    function Header()
    {
        // Logo
        $this->Image($this->logo, 10, 10, 30); // logo de la empresa
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'FACTURA A', 0, 1, 'R');
        
        // CUIT y punto de venta
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'CUIT: '.$this->cuit, 0, 1, 'R');
        $this->Cell(0, 5, 'Punto de Venta: 0003', 0, 1, 'R');
        $this->Cell(0, 5, 'Comprobante Nro: 0003-00001234', 0, 1, 'R');
        $this->Cell(0, 5, 'Fecha inicio actividad: ', 0, 1, 'R');

        $this->Ln(1);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, utf8_decode($this->empresa), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, utf8_decode($this->direccion).' - Buenos Aires', 0, 1);
        $this->Cell(0, 5, utf8_decode('Condición frente al IVA: Responsable Inscripto'), 0, 1);
        $this->Ln(5);

        $this->Line(10, $this->GetY(), 200, $this->GetY()); // Línea horizontal
    }

    function Footer()
    {
        $this->SetY(-30);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 8, 'CAE: '.$this->cae, 0, 1);
        $this->Cell(0, 8, 'Fecha de Vto. de CAE: '.$this->venceCae, 0, 1);
        $this->Cell(0, 8,utf8_decode('Comprobante autorizado por la AFIP'), 0, 1);

        $this->Image('qr.png', 170, $this->GetY() - 30, 30);
    }
}

$unidades   = $DT['unidades'];
$parcial    = $DT['unitario'];
$total      = $DT['ImpNeto'];
$iva        = $DT['ImpIVA'];
$ttf        = $DT['ImpTotal'];
$cae        = 'XXXXXXXXXXXXXXXX';
$venceCae   = 'XX-XX-XXXX';
    

$pdf = new PDF('P','mm','A4');
if($cadathumb){
    $pdf->logo  = $cadathumb[0];
}else{
    $pdf->logo  = "../img/logo/orion.png";
}
$pdf->empresa  = $GBL_U[0]['empresa'];
$pdf->cuit  = $GBL_U[0]['cuit'];
$pdf->direccion  = $GBL_U[0]['direccion'];
$pdf->cae  = $cae;
$pdf->venceCae  = $venceCae;

$pdf->SetAuthor  ='M@D';
$pdf->SetCreator  ='Sysmika';
$pdf->AliasNbPages();
$pdf->AddPage();

// Cliente
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Cliente', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 5, 'Nombre:'.$OS[0]['nombre'], 0, 1);
$pdf->Cell(100, 5, 'CUIT: '.$OS[0]['cuit'], 0, 1);
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
$pdf->MultiCell(115, $alto, utf8_decode(strip_tags($DT['detalle'])), 0);
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

$pdf->Cell(120, 8, 'IVA', 'T B');
$pdf->Cell(10, 8, ' ', 'T B');
$pdf->Cell(30, 8, $DT['IdAlicuota'].'%', 'T B');
$pdf->Cell(30, 8, '$'.number_format($iva,2,',','.'), 'T B', 1, 'R');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(160, 8, 'TOTAL', 'B');
$pdf->Cell(30, 8, '$'.number_format($ttf,2,',','.'), 'B', 1, 'R');

$pdf->Output();


?>