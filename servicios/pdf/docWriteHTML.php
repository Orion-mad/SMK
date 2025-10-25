<?
require('fpdf.php');
class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('../img/logo/empresa/SYS-16029i/asistire_logo.png', 10, 10, 30); // logo de la empresa
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'FACTURA A', 0, 1, 'R');
        
        // CUIT y punto de venta
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'CUIT: 20-30123456-7', 0, 1, 'R');
        $this->Cell(0, 5, 'Punto de Venta: 0001', 0, 1, 'R');
        $this->Cell(0, 5, 'Comprobante Nro: 0001-00001234', 0, 1, 'R');

        $this->Ln(5);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'Empresa Demo SRL', 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Av. Siempre Viva 123 - Buenos Aires', 0, 1);
        $this->Cell(0, 5, utf8_decode('Condición frente al IVA: Responsable Inscripto'), 0, 1);
        $this->Ln(5);

        $this->Line(10, $this->GetY(), 200, $this->GetY()); // Línea horizontal
    }

    function Footer()
    {
        $this->SetY(-50);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'CAE: 70417234567890', 0, 1);
        $this->Cell(0, 5, 'Fecha de Vto. de CAE: 24/04/2025', 0, 1);
        $this->Cell(0, 5,utf8_decode('Comprobante autorizado por la AFIP'), 0, 1);

        $this->Image('qr.png', 170, $this->GetY() - 20, 30);
    }    
    
    function WriteText($text)
    {
        $intPosIni = 0;
        $intPosFim = 0;
        if (strpos($text,'<')!==false && strpos($text,'[')!==false)
        {
            if (strpos($text,'<')<strpos($text,'['))
            {
                $this->Write(5,substr($text,0,strpos($text,'<')));
                $intPosIni = strpos($text,'<');
                $intPosFim = strpos($text,'>');
                $this->SetFont('','B');
                $this->Write(5,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1));
                $this->SetFont('','');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            else
            {
                $this->Write(5,substr($text,0,strpos($text,'[')));
                $intPosIni = strpos($text,'[');
                $intPosFim = strpos($text,']');
                $w=$this->GetStringWidth('a')*($intPosFim-$intPosIni-1);
                $this->Cell($w,$this->FontSize+0.75,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1),1,0,'');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
        }
        else
        {
            if (strpos($text,'<')!==false)
            {
                $this->Write(5,substr($text,0,strpos($text,'<')));
                $intPosIni = strpos($text,'<');
                $intPosFim = strpos($text,'>');
                $this->SetFont('','B');
                $this->WriteText(substr($text,$intPosIni+1,$intPosFim-$intPosIni-1));
                $this->SetFont('','');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            elseif (strpos($text,'[')!==false)
            {
                $this->Write(5,substr($text,0,strpos($text,'[')));
                $intPosIni = strpos($text,'[');
                $intPosFim = strpos($text,']');
                $w=$this->GetStringWidth('a')*($intPosFim-$intPosIni-1);
                $this->Cell($w,$this->FontSize+0.75,substr($text,$intPosIni+1,$intPosFim-$intPosIni-1),1,0,'');
                $this->WriteText(substr($text,$intPosFim+1,strlen($text)));
            }
            else
            {
                $this->Write(5,$text);
            }

        }
    }    
}

?>