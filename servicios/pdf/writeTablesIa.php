<?php
/**
 * writeTablesIa.php
 * Clase PDF_Table (extiende FPDF) para renderizar tablas HTML (thead/tbody/tr/th/td),
 * con colspan básico, alineación por celda, anchos porcentuales/px, saltos de página,
 * repetición de encabezado y cálculo de alto de fila por MultiCell.
 *
 * Requiere: fpdf.php cargado previamente (no se requiere aquí).
 */

class PDF_Table extends FPDF
{
    /** ------------------------
     *   Estado / Estilo tabla
     *  ------------------------ */
    protected $colWidths = [];                // anchos por columna (mm)
    protected $colAligns = [];                // alineaciones por columna: 'L','C','R'

    /** Bordes: puede ser string global ('LRBT') o array por columna */
    protected $colBorders = 'LRBT';           // string|array (no tipado para compatibilidad)

    protected $header        = [];            // etiquetas del header
    protected $headerAligns  = [];            // alineación por header
    protected $headerBorders = [];            // bordes por header
    protected $defaultBorder = 'LRBT';        // borde por defecto si nada está definido
    protected $cellPadding   = 1.5;           // padding interno (mm)
    protected $lineHeight    = 5.0;           // alto de línea de MultiCell
    protected $repeatHeader  = true;          // repetir header tras salto de página

    /** Recordar configuración por si se repite header en nueva página */
    protected $lastTableConfig = [];

    /** Tipografías */
    protected $headerFont = ['family' => 'Arial', 'style' => 'B', 'size' => 10];
    protected $bodyFont   = ['family' => 'Arial', 'style' => '',  'size' => 10];

    /** ------------------------
     *   Setters / Config
     *  ------------------------ */
    public function SetTablePadding(float $mm): void { $this->cellPadding = max(0, $mm); }
    public function SetLineHeight(float $mm): void   { $this->lineHeight  = max(2, $mm); }

    public function SetWidths(array $widths): void { $this->colWidths = $widths; }

    public function AutoWidths(int $numCols, float $totalWidth = null): void
    {
        $total = $totalWidth ?? ($this->w - $this->lMargin - $this->rMargin);
        $w = round($total / max(1, $numCols), 2);
        $this->colWidths = array_fill(0, $numCols, $w);
    }

    public function SetAligns(array $aligns): void { $this->colAligns = $aligns; }

    /**
     * Puede ser string global ('LRBT') o array por columna (['LRBT','','TB',...])
     * La prioridad final es: borde por celda > borde por columna > borde global > defaultBorder
     */
    public function SetBorders($borders): void
    {
        $this->colBorders = $borders; // string o array
    }

    public function SetHeader(array $labels, array $aligns = [], $borders = 'LRBT'): void
    {
        $this->header = $labels;
        $this->headerAligns  = $aligns;
        $this->headerBorders = is_array($borders) ? $borders : array_fill(0, count($labels), (string)$borders);

        $this->lastTableConfig = [
            'colWidths'      => $this->colWidths,
            'colAligns'      => $this->colAligns,
            'colBorders'     => $this->colBorders,
            'header'         => $this->header,
            'headerAligns'   => $this->headerAligns,
            'headerBorders'  => $this->headerBorders,
            'cellPadding'    => $this->cellPadding,
            'lineHeight'     => $this->lineHeight,
            'headerFont'     => $this->headerFont,
            'bodyFont'       => $this->bodyFont,
            'defaultBorder'  => $this->defaultBorder,
        ];
    }

    public function SetHeaderFont(string $family, string $style, float $size): void
    {
        $this->headerFont = compact('family','style','size');
    }

    public function SetBodyFont(string $family, string $style, float $size): void
    {
        $this->bodyFont = compact('family','style','size');
    }

    public function SetRepeatHeader(bool $repeat): void { $this->repeatHeader = $repeat; }

    /** ------------------------

     *   Dibujar Header de tabla
     *  ------------------------ */
    protected function drawHeader(): void
    {
        if (empty($this->header)) return;

        $this->SetFont($this->headerFont['family'], $this->headerFont['style'], $this->headerFont['size']);
        $this->SetFillColor(240,240,240);

        // Asegurar anchos
        if (empty($this->colWidths) || count($this->colWidths) < count($this->header)) {
            $this->AutoWidths(count($this->header));
        }

        // Calcular alto de fila del header según el mayor número de líneas
        $maxLines = 1;
        foreach ($this->header as $i => $txt) {
            $w = $this->colWidths[$i] ?? 30;
            $lines = $this->NbLines($w - 2*$this->cellPadding, (string)$txt);
            if ($lines > $maxLines) $maxLines = $lines;
        }
        $h = $this->lineHeight * $maxLines + 2*$this->cellPadding;

        $this->CheckPageBreak($h);

        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($this->header as $i => $txt) {
            $w = $this->colWidths[$i] ?? 30;
            $a = $this->headerAligns[$i] ?? 'L';
            $b = $this->headerBorders[$i] ?? $this->defaultBorder;

            // Borde y fondo
            if ($b) $this->Rect($x, $y, $w, $h, 'D');

            // Texto
            $this->SetXY($x + $this->cellPadding, $y + $this->cellPadding);
            $this->MultiCell($w - 2*$this->cellPadding, $this->lineHeight, (string)$txt, 0, $a, false);

            $x += $w;
            $this->SetXY($x, $y);
        }
        $this->Ln($h);

        // Volver a tipografía del cuerpo
        $this->SetFont($this->bodyFont['family'], $this->bodyFont['style'], $this->bodyFont['size']);
    }

    /** ------------------------
     *   Dibujar una fila (Row)
     *  ------------------------ */
    public function Row(array $rowData): void
    {
        // Expandir colspans y calcular anchos por celda
        [$cells, $widths] = $this->normalizeRow($rowData);

        // Calcular alto de la fila según el máximo de líneas
        $maxLines = 1;
        foreach ($cells as $i => $cell) {
            $txt = is_array($cell) ? ($cell['text'] ?? '') : (string)$cell;
            $w   = $widths[$i] - 2*$this->cellPadding;
            $lines = $this->NbLines($w, (string)$txt);
            if ($lines > $maxLines) $maxLines = $lines;
        }
        $h = $this->lineHeight * $maxLines + 2*$this->cellPadding;

        // Salto de página si no entra
        $this->CheckPageBreak($h, true);

        // Dibujar celdas
        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($cells as $i => $cell) {
            $txt   = is_array($cell) ? ($cell['text'] ?? '') : (string)$cell;
            $align = is_array($cell) ? ($cell['align'] ?? ($this->colAligns[$i] ?? 'L')) : ($this->colAligns[$i] ?? 'L');

            // Normalizar borde: prioridad celda > col > global > default
            $colB = is_array($this->colBorders)
                ? ($this->colBorders[$i] ?? $this->defaultBorder)
                : ($this->colBorders ?: $this->defaultBorder);

            $bdr   = is_array($cell) ? ($cell['border'] ?? $colB) : $colB;
            $fill  = (is_array($cell) && isset($cell['fill']) && is_array($cell['fill'])) ? $cell['fill'] : null;

            $w = $widths[$i];

            if ($fill) {
                $this->SetFillColor($fill[0], $fill[1], $fill[2]);
                $this->Rect($x, $y, $w, $h, 'F');
                if ($bdr) $this->Rect($x, $y, $w, $h, 'D');
                $this->SetFillColor(255,255,255);
            } else {
                if ($bdr) $this->Rect($x, $y, $w, $h, 'D');
            }

            $this->SetXY($x + $this->cellPadding, $y + $this->cellPadding);
            $this->MultiCell($w - 2*$this->cellPadding, $this->lineHeight, (string)$txt, 0, $align, false);

            $x += $w;
            $this->SetXY($x, $y);
        }
        $this->Ln($h);
    }

    protected function normalizeRow(array $rowData): array
    {
        $baseWidths = $this->colWidths;

        if (empty($baseWidths)) {
            // Si no hay anchos definidos, calcular según colspans de la fila
            $cols = 0;
            foreach ($rowData as $cell) {
                $cols += (is_array($cell) && isset($cell['colspan'])) ? max(1, (int)$cell['colspan']) : 1;
            }
            $this->AutoWidths($cols);
            $baseWidths = $this->colWidths;
        }

        $cellsExpanded  = [];
        $widthsExpanded = [];
        $colIndex = 0;

        foreach ($rowData as $cell) {
            $span = (is_array($cell) && isset($cell['colspan'])) ? max(1, (int)$cell['colspan']) : 1;

            $w = 0.0;
            for ($k=0; $k<$span; $k++) {
                $w += $baseWidths[$colIndex + $k] ?? end($baseWidths) ?? 30;
            }

            $cellsExpanded[]  = $cell;
            $widthsExpanded[] = $w;
            $colIndex += $span;
        }

        return [$cellsExpanded, $widthsExpanded];
    }

    /** ------------------------
     *   Salto de página + repetir header
     *  ------------------------ */
    protected function CheckPageBreak(float $h, bool $beforeRow = false): void
    {
        if ($this->GetY() + $h <= $this->PageBreakTrigger) return;

        // Nueva página
        $this->AddPage($this->CurOrientation);

        // Repetir header si corresponde
        if ($this->repeatHeader && !empty($this->header)) {
            if (!empty($this->lastTableConfig)) {
                $this->colWidths      = $this->lastTableConfig['colWidths'];
                $this->colAligns      = $this->lastTableConfig['colAligns'];
                $this->colBorders     = $this->lastTableConfig['colBorders'];
                $this->header         = $this->lastTableConfig['header'];
                $this->headerAligns   = $this->lastTableConfig['headerAligns'];
                $this->headerBorders  = $this->lastTableConfig['headerBorders'];
                $this->cellPadding    = $this->lastTableConfig['cellPadding'];
                $this->lineHeight     = $this->lastTableConfig['lineHeight'];
                $this->headerFont     = $this->lastTableConfig['headerFont'];
                $this->bodyFont       = $this->lastTableConfig['bodyFont'];
                $this->defaultBorder  = $this->lastTableConfig['defaultBorder'];
            }
            $this->drawHeader();
        }
    }

    /** ------------------------
     *   Conteo de líneas para MultiCell
     *  ------------------------ */
    protected function NbLines(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w) * 1000 / $this->FontSize;

        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ')  $sep = $i;
            $l += $cw[$c] ?? $this->FontSize;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    /** ------------------------
     *   Dibujar tabla completa (con header ya seteado o pasado)
     *  ------------------------ */
    public function Table(array $rows, ?array $header = null): void
    {
        if ($header !== null) {
            if (empty($this->colWidths)) $this->AutoWidths(count($header));
            if (empty($this->header)) $this->SetHeader($header);
        }

        if (!empty($this->header)) $this->drawHeader();

        $this->SetFont($this->bodyFont['family'], $this->bodyFont['style'], $this->bodyFont['size']);

        foreach ($rows as $row) {
            $this->Row($row);
        }
    }

    /** ==========================================================
     *   HTML TABLE avanzado (Word/CKEditor): thead/tbody, colspan
     *  ========================================================== */
    public function WriteHTMLRichTable(string $html, ?array $opts = null): void
    {
        $opts = $opts ?? [];
        $defaultAlign = $opts['default_align'] ?? 'L';
        $headerBold   = $opts['header_bold']   ?? true;

        // 1) Limpiar HTML de Word y normalizar saltos
        $clean = $this->sanitizeWordHtml($html);

        // 2) Parsear estructura de tabla
        $parsed = $this->parseTableHtml($clean, $defaultAlign);

        $headers   = $parsed['headers'];
        $rows      = $parsed['rows'];
        $colCount  = $parsed['colCount'];
        $colWidths = $parsed['colWidths'];

        if ($colCount <= 0) return;

        // 3) Configurar anchos
        if (!empty($colWidths)) {
            $this->SetWidths($colWidths);
        } else {
            $this->AutoWidths($colCount);
        }

        // 4) Header
        if (!empty($headers)) {
            $this->SetHeader($headers);
            if ($headerBold) {
                $this->SetHeaderFont($this->headerFont['family'], 'B', $this->headerFont['size']);
            }
        }

        // 5) Dibujar
        $this->Table($rows, !empty($headers) ? null : null);
    }

    /** ------------------------
     *   Helpers HTML -> estructura
     *  ------------------------ */
    protected function sanitizeWordHtml(string $html): string
    {
        $s = preg_replace('/\s+/u', ' ', $html);
        $s = preg_replace('/<!--\[if.*?endif\]-->/i', '', $s);
        $s = preg_replace('/\s*mso-[^:]+:[^;"\']+;?/i', '', $s);
        $s = preg_replace('/class="Mso[^"]*"/i', '', $s);
        $s = preg_replace('/style=""/i', '', $s);
        $s = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $s);
        $s = preg_replace('/<p\b[^>]*>/i', '', $s);
        $s = str_ireplace(['</p>'], "\n\n", $s);
        $s = str_replace('&nbsp;', ' ', $s);
        return $s;
    }

    protected function parseTableHtml(string $html, string $defaultAlign = 'L'): array
    {
        $headers = [];
        $rows    = [];
        $colCount = 0;
        $colWidthsPx = []; // hints por columna: ['value'=>float, 'percent'=>bool]
        $hasThead = false;

        if (!preg_match('/<table\b[^>]*>(.*?)<\/table>/i', $html, $mTable)) {
            return ['headers'=>[], 'rows'=>[], 'colCount'=>0, 'colWidths'=>[]];
        }
        $tableInner = $mTable[1];

        // THEAD
        if (preg_match('/<thead\b[^>]*>(.*?)<\/thead>/i', $tableInner, $mThead)) {
            $hasThead = true;
            $thead = $mThead[1];
            $headers = $this->parseHeaderRowFromBlock($thead, $colWidthsPx, $defaultAlign);
        }

        // Sin thead, intentar primera fila <th>
        if (!$hasThead && empty($headers)) {
            if (preg_match('/<tr\b[^>]*>(.*?)<\/tr>/i', $tableInner, $mFirstTr)) {
                $maybeHead = $mFirstTr[1];
                if (stripos($maybeHead, '<th') !== false) {
                    $headers = $this->parseHeaderRowFromBlock($maybeHead, $colWidthsPx, $defaultAlign);
                    // remover esa fila del body
                    $pos = strpos($tableInner, $mFirstTr[0]);
                    if ($pos !== false) {
                        $tableInner = substr_replace($tableInner, '', $pos, strlen($mFirstTr[0]));
                    }
                }
            }
        }

        // TBODY(s) o resto
        $bodies = [];
        if (preg_match_all('/<tbody\b[^>]*>(.*?)<\/tbody>/i', $tableInner, $mBodies)) {
            $bodies = $mBodies[1];
        } else {
            $tmp = $tableInner;
            if ($hasThead) $tmp = str_ireplace($mThead[0], '', $tmp);
            $bodies = [$tmp];
        }

        foreach ($bodies as $bodyHtml) {
            if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/i', $bodyHtml, $mTr)) {
                foreach ($mTr[1] as $trInner) {
                    $row = $this->parseTableRow($trInner, $defaultAlign, $colWidthsPx);
                    if (!empty($row)) $rows[] = $row;
                }
            }
        }

        // Máximo número de columnas lógicas contando colspans
        $counts = array_map([$this, 'spanCount'], is_array($rows) ? $rows : []);
        $counts[] = $this->spanCount($headers);
        $counts[] = 0;
        $colCount = max(...$counts);

        // Normalizar anchos (mm)
        $colWidths = [];
        if ($colCount > 0 && !empty($colWidthsPx)) {
            $colWidths = $this->normalizeColumnWidths($colWidthsPx, $colCount);
        }

        return compact('headers','rows','colCount','colWidths');
    }

    protected function parseHeaderRowFromBlock(string $blockHtml, array &$colWidthsPx, string $defaultAlign): array
    {
        $headers = [];
        if (preg_match('/<tr\b[^>]*>(.*?)<\/tr>/i', $blockHtml, $mHeadTr)) {
            $headers = $this->parseHeaderCells($mHeadTr[1], $colWidthsPx, $defaultAlign);
        } else {
            $headers = $this->parseHeaderCells($blockHtml, $colWidthsPx, $defaultAlign);
        }
        return $headers;
    }

    protected function parseHeaderCells(string $trInner, array &$colWidthsPx, string $defaultAlign): array
    {
        $headers = [];
        if (preg_match_all('/<th\b([^>]*)>(.*?)<\/th>/i', $trInner, $mTh)) {
            foreach ($mTh[2] as $i => $txtHtml) {
                $attrs = $mTh[1][$i] ?? '';
                $this->captureWidthHint($attrs, $colWidthsPx, count($headers));
                $text = trim($this->decodeHtml(strip_tags($txtHtml, '<br>')));
                $text = str_replace(["<br>", "<br/>", "<br />"], "\n", $text);
                $headers[] = $text;
            }
        }
        return $headers;
    }

    protected function parseTableRow(string $trInner, string $defaultAlign, array &$colWidthsPx): array
    {
        $row = [];
        $pattern = '/<(td|th)\b([^>]*)>(.*?)<\/\1>/i';
        if (preg_match_all($pattern, $trInner, $mCells)) {
            foreach ($mCells[3] as $i => $cellHtml) {
                $attrs = $mCells[2][$i] ?? '';
                $span  = $this->extractColspan($attrs);
                $al    = $this->extractAlign($attrs, $cellHtml, $defaultAlign);
                $this->captureWidthHint($attrs, $colWidthsPx, count($row));

                $text = trim($this->decodeHtml(strip_tags($cellHtml, '<br>')));
                $text = str_replace(["<br>", "<br/>", "<br />"], "\n", $text);

                $row[] = ['text'=>$text, 'align'=>$al, 'colspan'=>$span];
            }
        }
        return $row;
    }

    protected function extractColspan(string $attrs): int
    {
        if (preg_match('/colspan\s*=\s*["\']?(\d+)/i', $attrs, $m)) {
            return max(1, (int)$m[1]);
        }
        return 1;
    }

    protected function extractAlign(string $attrs, string $cellHtml, string $defaultAlign): string
    {
        if (preg_match('/\balign\s*=\s*["\']?(center|right|left)/i', $attrs, $m)) {
            return strtoupper($m[1][0]); // C/R/L
        }
        if (preg_match('/text-align\s*:\s*(center|right|left)/i', $attrs, $m)) {
            return strtoupper($m[1][0]);
        }
        return $defaultAlign;
    }

    protected function captureWidthHint(string $attrs, array &$colWidthsPx, int $colIndex): void
    {
        $w = null; $isPercent = false;
        if (preg_match('/\bwidth\s*=\s*["\']?\s*([\d.]+)\s*%/i', $attrs, $m)) {
            $w = (float)$m[1]; $isPercent = true;
        } elseif (preg_match('/\bwidth\s*=\s*["\']?\s*([\d.]+)/i', $attrs, $m)) {
            $w = (float)$m[1];
        } elseif (preg_match('/width\s*:\s*([\d.]+)\s*%/i', $attrs, $m)) {
            $w = (float)$m[1]; $isPercent = true;
        } elseif (preg_match('/width\s*:\s*([\d.]+)\s*px/i', $attrs, $m)) {
            $w = (float)$m[1];
        }
        if ($w !== null) {
            $colWidthsPx[$colIndex] = ['value'=>$w, 'percent'=>$isPercent];
        }
    }

    protected function spanCount($row): int
    {
        if (empty($row)) return 0;
        $sum = 0;
        foreach ($row as $cell) {
            if (is_array($cell)) $sum += (int)($cell['colspan'] ?? 1);
            else $sum += 1;
        }
        return $sum;
    }

    protected function normalizeColumnWidths(array $hints, int $colCount): array
    {
        $total = $this->w - $this->lMargin - $this->rMargin;

        $perc = array_fill(0, $colCount, null);
        $px   = array_fill(0, $colCount, null);

        foreach ($hints as $i => $h) {
            if ($i >= $colCount) continue;
            if (!is_array($h) || !isset($h['value'])) continue;
            if (!empty($h['percent'])) $perc[$i] = (float)$h['value'];
            else                        $px[$i]   = (float)$h['value'];
        }

        $widths = array_fill(0, $colCount, null);
        $sumAssigned = 0.0;

        // Porcentajes
        foreach ($perc as $i => $p) {
            if ($p !== null) {
                $widths[$i] = max(0.0, $total * ($p/100.0));
                $sumAssigned += $widths[$i];
            }
        }

        // px -> mm (96dpi aprox: 1px ≈ 0.264583mm)
        for ($i=0; $i<$colCount; $i++) {
            if ($px[$i] !== null && $widths[$i] === null) {
                $mm = $px[$i] * 0.264583;
                $widths[$i] = $mm;
                $sumAssigned += $mm;
            }
        }

        // Repartir lo que resta
        $remainingCols = [];
        for ($i=0; $i<$colCount; $i++) if ($widths[$i] === null) $remainingCols[] = $i;

        $rest = max(0.0, $total - $sumAssigned);
        $fill = (count($remainingCols) > 0) ? ($rest / count($remainingCols)) : 0.0;
        foreach ($remainingCols as $i) $widths[$i] = $fill;

        // Normalización por redondeo
        $sum = array_sum($widths);
        if ($sum > 0 && abs($sum - $total) > 0.01) {
            $factor = $total / $sum;
            foreach ($widths as $i => $w) $widths[$i] = $w * $factor;
        }
        return $widths;
    }

    protected function decodeHtml(string $s): string
    {
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
