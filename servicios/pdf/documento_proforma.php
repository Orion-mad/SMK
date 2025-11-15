<?php
// servicios/pdf/documento_proforma.php
require_once __DIR__ . '/fpdf.php';

class DocumentoProforma extends FPDF
{
    private $empresa = [
        'nombre' => 'Orion Group',
        'direccion' => 'Riobamba 51 Dpto 1',
        'telefono' => '(011) 4249-1385',
        'email' => 'hola@orion.ar',
        'web' => 'www.orion.ar',
        'cuit' => '20-14095277-0'
    ];
    
    private $cobro = [];
    private $cliente = [];
    private $angle = 0;

    
    public function __construct($cobro, $cliente) {
        parent::__construct('P', 'mm', 'A4');
        $this->cobro = $cobro;
        $this->cliente = $cliente;
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 15, 15);
    }
    
    // Header personalizado
    function Header() {
        
        $Logo	= glob("../../../assets/img/logos/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
        // Logo (si existe)
        $logoPath = $Logo[0];
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 30);
        }
        
        // Datos de la empresa
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, $this->empresa['nombre'], 0, 1, 'R');
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, $this->empresa['direccion'], 0, 1, 'R');
        $this->Cell(0, 4, $this->empresa['telefono'] . ' - ' . $this->empresa['email'], 0, 1, 'R');
        $this->Cell(0, 4, $this->empresa['cuit'], 0, 1, 'R');
        
        $this->Ln(5);
        
        // Título PROFORMA
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'PROFORMA', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        
        // Subtítulo
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, 'Comprobante No Fiscal', 0, 1, 'C');
        
        $this->Ln(5);
        
        // Línea separadora
        
        $y_start = $this->GetY();
         if ($this->cobro['estado'] == 'pagado') {
            $this->SetDrawColor(200, 200, 200);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->Ln(2);
            $this->SetTextColor(0,150,0);
            $this->SetX(18, $y_start);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(60, 6, 'PAGADO', 1, 1, 'C');
            $this->SetTextColor(0, 0, 0);
            $this->Ln(2);
        }
        
        
    }
    
    // Footer personalizado
    function Footer() {
        $this->SetY(-85);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        
        $this->SetFont('Arial', 'B', 10);
        // Transferencia Bancaria
        $this->Cell(95, 10, 'Transferencia Bancaria', 0, 0);
        // Transferencia MercadoPago
        $this->Cell(95, 10, 'Transferencia MercadoPago', 0, 0);
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 11);
        $this->Ln(2);
        $this->Cell(95, 7, 'Cuenta Pesos', 0, 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(95, 7, 'CVU: 0000003100103339214615', 0, 0);
        $this->Ln(5);
        $this->Cell(95, 7, 'CBU: 1430001713006474490017', 0, 0);
        $this->Cell(95, 7, 'Alias: sysmika', 0, 0);
        $this->Ln(5);
        $this->Cell(190, 7, 'Alias: miguel.angel.doval', 0, 0);
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(190, 7, 'Cuenta Dolar', 0, 0);
        $this->SetFont('Arial', '', 11);
        $this->Ln(5);
        $this->Cell(190, 7, 'CBU: 1430001714006474490025', 0, 0);
        $this->Ln(5);
        $this->Cell(190, 7, 'Alias: sysmika.dolar', 0, 0);
        $this->Ln(5);
        $this->MultiCell(0, 7, 'Confirmar el pago por mail o whatsapp', 0, 1);
        
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, utf8_decode('IMPORTANTE: Este es un comprobante de proforma, no válido como factura fiscal.'), 0, 1, 'C');
        $this->Cell(0, 4, utf8_decode('Una vez efectuado el pago, se emitirá la factura oficial correspondiente.'), 0, 1, 'C');
        $this->Ln(2);
  
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, utf8_decode('Generado el ' . date('d/m/Y H:i') . ' - Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
    
    // Sección de información del cobro y cliente
    function SeccionInformacion() {
        $this->SetFont('Arial', 'B', 10);
        
        // Recuadro con datos principales
        $this->SetFillColor(240, 240, 240);
        $this->SetDrawColor(200, 200, 200);
        
        // Fila 1: Código y Fecha
        $y_start = $this->GetY();
        $this->Rect(15, $y_start, 90, 25);
        $this->Rect(105, $y_start, 90, 25);
        
        // Columna izquierda - Código y Pago realizado
        $this->SetXY(15, $y_start);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(90, 6, 'CODIGO:', 0, 1, 'L');
        $this->SetX(15);
        $this->SetFont('Arial', '', 12);
        $this->Cell(90, 8, $this->cobro['codigo'], 0, 1, 'L');
       
        // Columna derecha - Fechas
        $this->SetXY(105, $y_start);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(90, 6, 'FECHA DE EMISION:', 0, 1, 'L');
        $this->SetX(105);
        $this->SetFont('Arial', '', 10);
        $this->Cell(90, 6, $this->formatDate($this->cobro['fecha_emision']), 0, 1, 'L');
        
        if (!empty($this->cobro['fecha_vencimiento'])) {
            $this->SetX(105);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(90, 6, 'VENCIMIENTO:', 0, 1, 'L');
            $this->SetX(105);
            $this->SetFont('Arial', '', 10);
            $this->Cell(90, 6, $this->formatDate($this->cobro['fecha_vencimiento']), 0, 0, 'L');
        }
        
        $this->Ln(8);
        
        // Información del cliente
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, 'DATOS DEL CLIENTE', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetDrawColor(200, 200, 200);
        $y_cliente = $this->GetY();
        $this->Rect(15, $y_cliente, 180, 30);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 6, utf8_decode('Razón Social:'), 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, utf8_decode($this->cliente['razon_social']), 0, 1, 'L');
        
        if (!empty($this->cliente['documento'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 6, 'Documento:', 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, utf8_decode($this->cliente['documento']), 0, 1, 'L');
        }
        
        if (!empty($this->cliente['condicion_iva'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 6, utf8_decode('Condición IVA:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, $this->cliente['condicion_iva'], 0, 1, 'L');
        }
        
        if (!empty($this->cliente['direccion'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(40, 6, utf8_decode('Dirección:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $direccion = utf8_decode($this->cliente['direccion']);
            if (!empty($this->cliente['localidad'])) {
                $direccion .= ' - ' . utf8_decode($this->cliente['localidad']);
            }
            $this->Cell(0, 6, $direccion, 0, 1, 'L');
        }
        
        $this->Ln(5);
    }
    
    // Sección de concepto
    function SeccionConcepto() {
        $this->SetFont('Arial', 'B', 11);
        $this->Ln(4);
        $this->Cell(0, 6, 'CONCEPTO', 0, 1, 'L');
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5, utf8_decode($this->cobro['concepto']), 0, 'L');
        
        $this->Ln(5);
    }
    
    // Tabla de detalle
    function TablaDetalle() {
        // Encabezado de tabla
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        
        $this->Cell(130, 8, utf8_decode('Descripción'), 1, 0, 'L', true);
        $this->Cell(50, 8, 'Importe', 1, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 10);
        
        // Fila de subtotal
        $this->Cell(130, 7, utf8_decode($this->cobro['concepto']), 1, 0, 'L');
        $this->Cell(50, 7, '$ ' . $this->formatMoney($this->cobro['subtotal']), 1, 1, 'R');
        
        $this->Ln(5);
    }
    
    // Resumen de totales
    function ResumenTotales() {
        $x_start = 125;
        $width_label = 20;
        $width_amount = 50;
        
        $this->SetX($x_start);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($width_label, 6, 'Subtotal:', 0, 0, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell($width_amount, 6, '$ ' . $this->formatMoney($this->cobro['subtotal']), 0, 1, 'R');
        
        // Descuento si existe
        if ($this->cobro['descuento'] > 0) {
            $this->SetX($x_start);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($width_label, 6, 'Descuento:', 0, 0, 'R');
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(200, 0, 0);
            $this->Cell($width_amount, 6, '- $ ' . $this->formatMoney($this->cobro['descuento']), 0, 1, 'R');
            $this->SetTextColor(0, 0, 0);
        }
        
        // Impuestos si existen
        if ($this->cobro['impuestos'] > 0) {
            $this->SetX($x_start);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($width_label, 6, 'Impuestos:', 0, 0, 'R');
            $this->SetFont('Arial', '', 10);
            $this->Cell($width_amount, 6, '$ ' . $this->formatMoney($this->cobro['impuestos']), 0, 1, 'R');
        }
        
        // Línea separadora
        $this->SetX($x_start);
        $this->SetDrawColor(0, 0, 0);
        $this->Cell($width_label + $width_amount, 0, '', 'T', 1);
        
        // Total
        $this->SetX($x_start);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell($width_label, 8, 'TOTAL:', 0, 0, 'R');
        $this->SetFont('Arial', 'B', 14);
        $this->Cell($width_amount, 8, '$ ' . $this->formatMoney($this->cobro['total']) . ' ' . $this->cobro['moneda'], 0, 1, 'R');
        
        $this->Ln(5);
    }
    
    // Sección de observaciones
    function SeccionObservaciones() {
        if (!empty($this->cobro['observaciones'])) {
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(0, 6, 'OBSERVACIONES', 0, 1, 'L');
            $this->Ln(2);
            
            $this->SetFillColor(250, 250, 250);
            $this->SetFont('Arial', '', 9);
            $y_obs = $this->GetY();
            $this->Rect(15, $y_obs, 180, 0); // Se ajustará automáticamente
            $this->Ln(3);
            $this->MultiCell(0, 4, utf8_decode($this->cobro['observaciones']), 0, 'L');
            $this->Ln(3);
        }
    }
    
    // Marca de agua "PROFORMA"
    function MarcaAgua() {
        $this->SetFont('Arial', 'B', 80);
        $this->SetTextColor(240, 240, 240);
        $this->RotatedText(60, 200, 'PROFORMA', 45);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Texto rotado
    function RotatedText($x, $y, $txt, $angle) {
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }
    
    // Rotación
    function Rotate($angle, $x=-1, $y=-1) {
        if($x == -1) $x = $this->x;
        if($y == -1) $y = $this->y;
        if($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if($angle != 0) {
            $angle *= M_PI/180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    function _endpage() {
        if($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
    
    // Helpers
    private function formatMoney($amount) {
        return number_format((float)$amount, 2, ',', '.');
    }
    
    private function formatDate($date) {
        if (empty($date)) return '-';
        $dt = new DateTime($date);
        return $dt->format('d/m/Y');
    }
    
    // Generar documento completo
    public function generarProforma() {
        $this->AliasNbPages();
        $this->AddPage();
        
        // Marca de agua
        $this->MarcaAgua();
        
        // Contenido
        $this->SeccionInformacion();
        //$this->SeccionConcepto();
        $this->TablaDetalle();
        $this->ResumenTotales();
        $this->SeccionObservaciones();
    }
}