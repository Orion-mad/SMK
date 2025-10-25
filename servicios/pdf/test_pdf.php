<?php
include($_SERVER['DOCUMENT_ROOT']."/inc/conect.php");
    $D        = $_REQUEST['A'] ?? null;
    if($D):
        $Doc    = $USERS->listar('codigo,documento','documentos',"WHERE id = {$D}");
        $J_Doc[$Doc[0]['codigo']]  = $Doc[0]['documento'];
        $W      = "WHERE id = '";
    else:
        $P      = $_REQUEST['P'] ?? null;    
        $D      = $_REQUEST['D'] ?? null;    
        $RCa    = $USERS->full_list('pacientes_presupuestos',"WHERE id = {$P}");
        $RCa    = $GUSERS->queryToArray($RCa);

        $J_Doc   = json_decode($RCa['documentacion'],true);
        $W      = "WHERE codigo = '";
    endif;
    $documento  = ($J_Doc[$D][1]);
    $cadathumb	= glob("../../img/logo/empresa/SYS-16029i/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);

require('fpdf.php');
class PDF extends FPDF
{

/* ---------------- utilitarios (ya los tenías) ---------------- */
function hex2dec($couleur = "#000000"){
    $R = substr($couleur, 1, 2);
    $rouge = hexdec($R);
    $V = substr($couleur, 3, 2);
    $vert = hexdec($V);
    $B = substr($couleur, 5, 2);
    $bleu = hexdec($B);
    $tbl_couleur = array();
    $tbl_couleur['R']=$rouge;
    $tbl_couleur['G']=$vert;
    $tbl_couleur['B']=$bleu;
    return $tbl_couleur;
}
function px2mm($px){
    return $px*25.4/72;
}
function txtentities($html){
    $trans = get_html_translation_table(HTML_ENTITIES);
    $trans = array_flip($trans);
    return strtr($html, $trans);
}

/* ---------------- clase PDF ---------------- */
    public $logo;
    public $cuit;
    public $direccion;
    public $empresa;
    public $cae;
    public $venceCae;

    // html parser state
    protected $issetcolor = false;

    function Header()
    {
        if (!empty($this->logo) && file_exists($this->logo)) {
            $this->Image($this->logo, 170, 5, 30);
        }
        $this->Ln(30);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
    }

    function Footer()
    {
        $this->SetY(-25);
        $this->SetFont('Arial', '', 9);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
        $this->Cell(0, 4, utf8_decode('CONMUTADOR: 0810-222-3995'), 0, 1,'C');
        $this->Cell(0, 4, utf8_decode('Sede L. de Zamora Manuel Castro 415 6068-0577'), 0, 1,'C');
        $this->Cell(0, 4,utf8_decode('Sede Banfield Av. H. Yrigoyen 7024 4202-9842/2205-1847'), 0, 1,'C');
        $this->Cell(0, 4,utf8_decode('Sede Lanús Av. H. Yrigoyen 4726 4249-0037'), 0, 1,'C');
        $this->Cell(0, 4,utf8_decode('contacto@assistire.com.ar/www.assistire.com.ar'), 0, 1,'C');
    }

    /* ---------------- Helpers para HTML -> PDF ---------------- */

    // Simplified fragment writer: soporta <br>, <p>, <b>, <i>, <u>, <font color=...>
    function writeHtmlFragment($html)
    {
        $html = str_replace(["\r","\n","\t"], ' ', $html);

        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*p\b[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/\s*p\s*>/i', "\n", $html);

        $html = preg_replace('/<\s*(b|strong)\b[^>]*>/i', '<B>', $html);
        $html = preg_replace('/<\/\s*(b|strong)\s*>/i', '</B>', $html);
        $html = preg_replace('/<\s*(i|em)\b[^>]*>/i', '<I>', $html);
        $html = preg_replace('/<\/\s*(i|em)\s*>/i', '</I>', $html);
        $html = preg_replace('/<\s*u\b[^>]*>/i', '<U>', $html);
        $html = preg_replace('/<\/\s*u\s*>/i', '</U>', $html);

        $html = preg_replace_callback('/<\s*font\b([^>]*)>/i', function($m){
            if (preg_match('/color\s*=\s*["\']?(#[0-9A-Fa-f]{3,6}|[A-Za-z0-9]+)["\']?/i', $m[1], $mc)) {
                return '<FONT COLOR="'.$mc[1].'">';
            }
            return '<FONT>';
        }, $html);
        $html = preg_replace('/<\/\s*font\s*>/i', '</FONT>', $html);

        // remove any other tags
        $html = preg_replace('/<(?!\/?(B|I|U|FONT)(\s+COLOR=[^>]*)?>)[^>]+>/i', '', $html);

        $html = $this->txtentities($html);

        $pos = 0;
        while ($pos < strlen($html)) {
            if (preg_match('/<(\/?)(B|I|U|FONT)(?:\s+COLOR=([^>]*))?>/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
                $matchPos = $m[0][1];
                $text = substr($html, $pos, $matchPos - $pos);
                if ($text !== '') $this->Write(5, utf8_decode($text));

                $closing = $m[1][0] === '/';
                $tag = strtoupper($m[2][0]);
                $color = isset($m[3]) ? $m[3][0] : null;
                if (!$closing) {
                    if ($tag == 'B') $this->SetFont('', 'B');
                    if ($tag == 'I') $this->SetFont('', 'I');
                    if ($tag == 'U') $this->SetFont('', 'U');
                    if ($tag == 'FONT' && $color) {
                        $c = hex2dec($color);
                        $this->SetTextColor($c['R'], $c['G'], $c['B']);
                        $this->issetcolor = true;
                    }
                } else {
                    if ($tag == 'B' || $tag == 'I' || $tag == 'U') $this->SetFont('', '');
                    if ($tag == 'FONT' && $this->issetcolor) {
                        $this->SetTextColor(0,0,0);
                        $this->issetcolor = false;
                    }
                }
                $pos = $matchPos + strlen($m[0][0]);
            } else {
                $text = substr($html, $pos);
                if ($text !== '') $this->Write(5, utf8_decode($text));
                break;
            }
        }
    }

    // WriteHTML: extrae tablas y manda fragmentos entre tablas a writeHtmlFragment()
    function WriteHTML($html)
    {
        if (trim($html) === '') return;
        $html = str_replace(["\r","\n","\t"], ' ', $html);

        if (!preg_match_all('/<\s*table\b[^>]*>.*?<\s*\/\s*table\s*>/is', $html, $mTables, PREG_OFFSET_CAPTURE)) {
            $this->writeHtmlFragment($html);
            return;
        }

        $lastPos = 0;
        foreach ($mTables[0] as $match) {
            $tableHtml = $match[0];
            $start = $match[1];
            $frag = substr($html, $lastPos, $start - $lastPos);
            if (trim($frag) !== '') $this->writeHtmlFragment($frag);
            $this->DrawTable($tableHtml);
            $lastPos = $start + strlen($tableHtml);
        }
        if ($lastPos < strlen($html)) {
            $frag = substr($html, $lastPos);
            if (trim($frag) !== '') $this->writeHtmlFragment($frag);
        }
    }

    /* ---------------- DrawTable (soporta colspan, width %, px, bgcolor) ---------------- */
    function DrawTable($tableHtml)
    {
        if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableHtml, $mRows)) return;
        $rowsHtml = $mRows[1];

        $table = array();
        $maxCols = 0;
        foreach ($rowsHtml as $rHtml) {
            $cells = array();
            if (preg_match_all('/<(td|th)\b([^>]*)>(.*?)<\/\1>/is', $rHtml, $mCells, PREG_SET_ORDER)) {
                foreach ($mCells as $c) {
                    $tag = strtolower($c[1]);
                    $attrStr = $c[2];
                    $content = $c[3];

                    $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
                    $raw = $content;
                    $text = strip_tags($content);
                    $text = $this->txtentities($text);

                    $align = 'L';
                    if (preg_match('/\balign\s*=\s*["\']?(left|center|right)["\']?/i', $attrStr, $ma)) {
                        $a = strtolower($ma[1]);
                        if ($a == 'center') $align = 'C';
                        elseif ($a == 'right') $align = 'R';
                    } elseif ($tag == 'th') $align = 'C';

                    $isHeader = ($tag == 'th');

                    $colspan = 1;
                    if (preg_match('/\bcolspan\s*=\s*["\']?(\d+)["\']?/i', $attrStr, $mc)) {
                        $colspan = max(1, intval($mc[1]));
                    }

                    $widthAttr = null;
                    if (preg_match('/\bwidth\s*=\s*["\']?([\d\.]+%|[\d\.]+px|[\d\.]+)["\']?/i', $attrStr, $mw)) {
                        $widthAttr = $mw[1];
                    } elseif (preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $attrStr, $ms)) {
                        if (preg_match('/width\s*:\s*([\d\.]+%|[\d\.]+px|[\d\.]+)/i', $ms[1], $ms2)) {
                            $widthAttr = $ms2[1];
                        }
                    }

                    $bgcolor = null;
                    if (preg_match('/\bbgcolor\s*=\s*["\']?(#[0-9A-Fa-f]{3,6}|[A-Za-z0-9]+)["\']?/i', $attrStr, $mb)) {
                        $bgcolor = $mb[1];
                    } elseif (isset($ms) && preg_match('/background-color\s*:\s*(#[0-9A-Fa-f]{3,6})/i', $ms[1], $ms3)) {
                        $bgcolor = $ms3[1];
                    }

                    $cells[] = array(
                        'raw' => $raw,
                        'text' => $text,
                        'align' => $align,
                        'header' => $isHeader,
                        'colspan' => $colspan,
                        'widthAttr' => $widthAttr,
                        'bgcolor' => $bgcolor,
                        'attrStr' => $attrStr
                    );
                }
            }
            $table[] = $cells;
            $countCols = 0;
            foreach ($cells as $c) $countCols += $c['colspan'];
            $maxCols = max($maxCols, $countCols);
        }

        if ($maxCols == 0) return;

        $usableWidth = $this->w - $this->lMargin - $this->rMargin;
        $colWidths = array_fill(0, $maxCols, 0);

        $foundWidths = false;
        foreach ($table as $row) {
            $pos = 0;
            foreach ($row as $cell) {
                if ($cell['widthAttr'] !== null) {
                    $foundWidths = true;
                    $wattr = $cell['widthAttr'];
                    if (strpos($wattr, '%') !== false) {
                        $perc = floatval(str_replace('%', '', $wattr));
                        $cw = $usableWidth * ($perc / 100.0);
                    } elseif (stripos($wattr, 'px') !== false) {
                        $px = floatval(str_ireplace('px', '', $wattr));
                        $cw = px2mm($px);
                    } else {
                        $px = floatval($wattr);
                        $cw = px2mm($px);
                    }
                    for ($k = 0; $k < $cell['colspan']; $k++) {
                        $colWidths[$pos + $k] += $cw / $cell['colspan'];
                    }
                }
                $pos += $cell['colspan'];
            }
        }

        if (!$foundWidths) {
            for ($i = 0; $i < $maxCols; $i++) $colWidths[$i] = $usableWidth / $maxCols;
        } else {
            $sumAssigned = array_sum($colWidths);
            $remaining = max(0, $usableWidth - $sumAssigned);
            $emptyCols = 0;
            foreach ($colWidths as $w) if ($w == 0) $emptyCols++;
            if ($emptyCols > 0) {
                $perEmpty = $remaining / $emptyCols;
                for ($i = 0; $i < count($colWidths); $i++) if ($colWidths[$i] == 0) $colWidths[$i] = $perEmpty;
            } else {
                if ($sumAssigned != $usableWidth && $sumAssigned > 0) {
                    $ratio = $usableWidth / $sumAssigned;
                    for ($i = 0; $i < count($colWidths); $i++) $colWidths[$i] *= $ratio;
                }
            }
        }

        $lineHeight = max(4, $this->FontSizePt * 0.35);

        foreach ($table as $row) {
            $pos = 0;
            $rowMaxHeight = 0;
            foreach ($row as $cell) {
                $cellWidth = 0;
                for ($k = 0; $k < $cell['colspan']; $k++) $cellWidth += $colWidths[$pos + $k];
                $cellInnerW = $cellWidth - 4;
                $txt = trim($cell['text']);
                $lines = 0;
                $parts = explode("\n", $txt);
                foreach ($parts as $p) {
                    if ($p === '') { $lines += 1; continue; }
                    $lines += $this->NbLines($cellInnerW, utf8_decode($p));
                }
                $cellHeight = $lines * $lineHeight + 4;
                if ($cellHeight > $rowMaxHeight) $rowMaxHeight = $cellHeight;
                $pos += $cell['colspan'];
            }

            if ($this->GetY() + $rowMaxHeight > $this->PageBreakTrigger) {
                $this->AddPage();
            }

            $x = $this->GetX();
            $y = $this->GetY();
            $pos = 0;
            foreach ($row as $cell) {
                $cellWidth = 0;
                for ($k = 0; $k < $cell['colspan']; $k++) $cellWidth += $colWidths[$pos + $k];

                if (!empty($cell['bgcolor'])) {
                    $c = hex2dec($cell['bgcolor']);
                    $this->SetFillColor($c['R'], $c['G'], $c['B']);
                    $fill = true;
                } else {
                    $fill = false;
                }

                if ($cell['header']) $this->SetFont('', 'B'); else $this->SetFont('', '');

                $this->SetLineWidth(0.2);
                $this->Rect($x, $y, $cellWidth, $rowMaxHeight);

                $this->SetXY($x + 2, $y + 2);
                $align = $cell['align'];
                $this->MultiCell($cellWidth - 4, $lineHeight, utf8_decode(trim($cell['text'])), 0, $align, $fill);

                $this->SetXY($x + $cellWidth, $y);
                $x += $cellWidth;
                $pos += $cell['colspan'];
            }

            $this->SetXY($this->lMargin, $y + $rowMaxHeight);
        }

        $this->Ln(3);
    }

    // NbLines
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            $ord = ord($c);
            if (!isset($cw[$ord])) {
                $l += $this->CurrentFont['cw'][ord(' ')];
            } else {
                $l += $cw[$ord];
            }
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
            if ($i < $nb) {
                if ($s[$i] == ' ') $sep = $i;
            }
        }
        return $nl;
    }

} // end class

/* ---------------- Ejemplo de uso ---------------- */

    $pdf = new PDF('P','mm','A4');
        if($cadathumb){
            $pdf->logo  = $cadathumb[0];
        }else{
            $pdf->logo  = "../../img/logo/orion.png";
        }

    $pdf->SetAuthor('M@D');
    $pdf->SetCreator('Sysmika');
    $pdf->AliasNbPages();

$pdf->AddPage();
$pdf->SetFont('Arial','',11);
// Ejemplo: $json puede venir de la DB, de file_get_contents, etc.
$documento = html_entity_decode($documento, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Tu HTML (tal como lo enviaste)
$html = $documento;

// Llamada correcta: usar WriteHTML (no usar Write)
$pdf->WriteHTML($html);

// OUTPUT: asegúrate de no imprimir nada antes de esto
$pdf->Output('I', 'presupuesto.pdf');
