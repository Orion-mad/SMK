<?php
// /servicios/fpdf/PresupuestoPDF.php

require_once __DIR__ . '/fpdf.php';

class PresupuestoPDF extends FPDF {
    private $tipo; // 'orion' o 'sysmika'
    private $datosEmpresa;
    private $clienteNombre;
    private $caratulaOrion	    = "../../../assets/img/caratula-orion.png";
    private $caratulaSysmika    = __DIR__."../../assets/img/caratula-sysmika.png";
    
    public function __construct($tipo = 'orion', $clienteNombre = '', $orientation = 'P') {
        parent::__construct($orientation, 'mm', 'A4');
        $this->tipo = $tipo;
        $this->clienteNombre = $clienteNombre;
        
        // Datos de la empresa
        $this->datosEmpresa = [
            'direccion' => 'Riobamba 51 Dto. 1',
            'ciudad' => 'Lanús - Buenos Aires - Argentina',
            'telefono' => '(54)11.4249.1385',
            'whatsapp' => '(54)9.11.2321.6228',
            'admin' => '(54)9.11.6699.1263',
            'web_orion' => 'orion.ar',
            'web_sysmika' => 'sysmika.ar'
        ];
    }
    
    // Portada
    function Portada($titulo = '') {
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
            $this->Cell(0, 10, utf8_decode('Presupuesto: '.$titulo), 1, 1, 'C', true);
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
    function PaginaContenido() {
        $this->AddPage();
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
    }
    
    // Encabezado personalizado
    function Header() {
        if ($this->PageNo() > 1) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 10, utf8_decode($this->tipo === 'orion' ? 'ORION WEB SYSTEM' : 'SYSMIKA'), 0, 0, 'L');
            $this->Cell(0, 10, utf8_decode('página ') . ($this->PageNo() - 1) . '/{nb}', 0, 0, 'R');
            $this->Ln(15);
        }
    }
    
    // Pie de página
    function Footer() {
        if ($this->PageNo() > 1) {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 10, utf8_decode($this->datosEmpresa['ciudad']), 0, 0, 'C');
        }
    }
    
    // Método auxiliar para círculos
}