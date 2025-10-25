<?php
// /servicios/pdf/TrabajoPDF.php
require_once __DIR__ . '/fpdf.php';

class TrabajoPDF extends FPDF {
    private $tipo;
    private $clienteNombre;
    private $caratulaOrion	    = "../../../assets/img/caratula-orion.png";
    private $caratulaSysmika    = __DIR__."../../assets/img/caratula-sysmika.png";

    public function __construct($tipo = 'orion', $clienteNombre = '') {
        parent::__construct();
        $this->tipo = $tipo;
        $this->clienteNombre = $clienteNombre;
    }
    
    // Portada
    public function Portada($titulo) {
        $this->AddPage();
        
        if ($this->tipo === 'orion') {
            $this->PortadaOrion($titulo);
        } else {
            $this->PortadaSysmika($titulo);
        }
        
    }
    private function PortadaOrion($titulo) {
        // Título superior
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(31, 73, 125);
        $this->Cell(0, 15, utf8_decode('Conectando tu negocio con el futuro digital'), 0, 1, 'C');

        $ancho_pagina = $this->GetPageWidth();
        $alto_pagina  = $this->GetPageHeight();
        $this->Image(__DIR__.'/../../assets/img/caratula-orion.png', 0, 0, $ancho_pagina, $alto_pagina);
        // Título del documento si existe
        if ($titulo) {
            $margen_superior = $alto_pagina * 0.80; // 80% del alto
            $posicion_y = $margen_superior; // Posición vertical del texto
            
            // Configurar el texto
            $this->SetFont('Arial', 'B', 14); // Fuente
            $this->SetXY(10, $posicion_y); // Posición horizontal y vertical
            $this->SetFillColor(31, 73, 125);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 10, utf8_decode('Trabajo: '.$titulo), 1, 1, 'C', true);
        }
    }
    
    private function PortadaSysmika($titulo) {
        // Logo SYSMIKA
        $this->Ln(20);
        $this->SetFont('Arial', 'B', 32);
        $this->SetTextColor(31, 73, 125);
        $this->Cell(10, 12, 'O', 0, 0, 'L');
        $this->SetFont('Arial', 'B', 28);
        $this->Cell(0, 12, 'SYSMIKA', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 6, utf8_decode('La solución integral para tu presencia en internet'), 0, 1, 'L');
        
        $ancho_pagina = $this->GetPageWidth();
        $alto_pagina  = $this->GetPageHeight();
        $this->Image(__DIR__.'/../../assets/img/caratula-orion.png', 0, 0, $ancho_pagina, $alto_pagina);
 
        // Título del documento
        if ($titulo) {
            $margen_superior = $alto_pagina * 0.80; // 80% del alto
            $posicion_y = $margen_superior; // Posición vertical del texto

            $this->Ln(10);
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(31, 73, 125);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 10, utf8_decode($titulo), 1, 1, 'C', true);
        }
    }    
    // Página de contenido
    public function PaginaContenido() {
        $this->AddPage();
        $this->SetMargins(20, 20, 20);
    }
    
    // Header
    function Header() {
        // Solo para páginas de contenido (no portada)
        if ($this->PageNo() > 1) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(31, 73, 125);
            $this->Cell(0, 10, utf8_decode('Trabajo de Producción'), 0, 0, 'L');
            
            if ($this->clienteNombre) {
                $this->Cell(0, 10, utf8_decode($this->clienteNombre), 0, 0, 'R');
            }
            
            $this->Ln(15);
        }
    }
    
    // Footer
    function Footer() {
        // Solo para páginas de contenido (no portada)
        if ($this->PageNo() > 1) {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            
            // Número de página
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
        }
    }
}