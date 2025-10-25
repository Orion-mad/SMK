<?
/*
include('../inc/conect.php');
require('fpdf.php');
require('../phpqrcode/qrlib.php');
*/

require($_SERVER['DOCUMENT_ROOT'].'/servicios/pdf/fpdf.php');
require($_SERVER['DOCUMENT_ROOT'].'/servicios/phpqrcode/qrlib.php');

$EMP        = $_SESSION['facturacion'];
$DT         = json_decode($_SESSION['Facturacion'],true);
$CU         = "Cuit";
$CNP        = "Servicios";    
//echo'<pre>';print_r($DT);echo'</pre>';
//die;
$cadathumb = '';
if($EMP['PTOVTA'] == 1) $cadathumb	       = glob("../../img/logo/empresa/SYS-16029i/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
$carpeta_destino   = '../../facturas/b/';
$nombre_archivo    = $DT['DocNro'].'_'.$DT['CbteDesde'];

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
             "codAut" => (int)$DT['CAE']
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

class PDF extends FPDF
{
    public $logo;
    public $cuit;
    public $direccion;
    public $empresa;
    public $CAE;
    public $CAEFchVto;
    public $CbteDesde;
    public $fecha;
    
    function Header()
    {
        // Column widths
        $col1_x = 5;      // Logo
        $col2_x = 50;      // Datos empresa
        $col3_x = 140;     // Datos factura
        $y_start = 10;

        if($this->logo ?? null){
        // 1. LOGO (columna izquierda)
        $this->Image($this->logo, $col1_x,5, 30); // ancho 30
        }

        // 2. DATOS EMPRESA (columna central)
        $this->SetXY($col2_x, $y_start);
        $this->SetFont('Arial', 'B', 12);
        $this->MultiCell(80, 5, utf8_decode($this->empresa), 0);
        $this->SetFont('Arial', '', 10);
        $this->SetX($col2_x);
        $this->MultiCell(80, 5, utf8_decode($this->direccion).' - Buenos Aires', 0);
        $this->SetX($col2_x);
        $this->MultiCell(80, 5, utf8_decode('Condición frente al IVA: Responsable Inscripto'), 0);

        // 3. DATOS FACTURA (columna derecha)
        $this->SetXY($col3_x, $y_start);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(60, 8, 'FACTURA B', 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->SetX($col3_x);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(60, 6, 'Fecha: '.$this->fecha, 0, 1, 'R');
        $this->SetX($col3_x);
        $this->Cell(60, 5, 'CUIT: '.$this->cuit, 0, 1, 'R');
        $this->SetX($col3_x);
        $this->Cell(60, 5, 'IIBB: '.$this->iibb, 0, 1, 'R');
        $this->SetX($col3_x);
        $this->Cell(60, 5, 'Comprobante Nro: '.$this->PtoVta.'-'.$this->CbteDesde, 0, 1, 'R');

        // Línea horizontal
        $this->Ln(6);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }


    function Footer()
    {
        $this->SetY(-45);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(160, 8, 'TOTAL', 'B');
        $this->Cell(30, 8, '$'.number_format($this->ttf,2,',','.'), 'B', 1, 'R');
        
        $this->SetY(-30);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 8, 'CAE: '.$this->CAE, 0, 1);
        $this->Cell(0, 8, 'Fecha de Vto. de CAE: '.$this->CAEFchVto, 0, 1);
        $this->Cell(0, 8,utf8_decode('Comprobante autorizado por la AFIP'), 0, 1);

        $this->Image('qr.png', 170, $this->GetY() - 30, 30);
    }
}

$unidades   = 1;
$parcial    = $DT['ImpTotConc'];
$total      = $DT['ImpTotConc'];
$ttf        = $DT['ImpTotConc'];
////////////////////////////////////////////
$pdf = new PDF('P','mm','A4');
if($cadathumb){
    $pdf->logo  = $cadathumb[0];
}else{
    $pdf->logo  = NULL;
}
$pdf->ttf       = $ttf;
$pdf->empresa   = $EMP['PTOVTA_RZ'];
$pdf->cuit      = $EMP['PTOVTA_CUIT'];
$pdf->iibb      = $EMP['PTOVTA_IIBB'];
$pdf->direccion = $EMP['PTOVTA_DIR'];
$pdf->CAE       = $DT['CAE'];
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
$pdf->Cell(100, 5, 'Nombre: '.$DT['empresa'], 0, 1);
$pdf->Cell(100, 5, utf8_decode($DT['direccion']), 0, 1);
$pdf->Cell(100, 5, 'CUIT: '.$DT['DocNro'], 0, 1);
$pdf->Cell(100, 5, utf8_decode('Condición IVA: Resp. Inscripto'), 0, 1);
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