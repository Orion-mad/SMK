<?
include('../../inc/conect.php');
try{
    //require('fpdf.php');
    $T      = $_GET['T'] ?? null;
    $P      = $_GET['P'] ?? null;
    $DATOS  = $USERS->full_list($T,"WHERE id = {$P}");
    $DATOS  = $GUSERS->queryToArray($DATOS);
    $J_pre  = json_decode($DATOS['prestaciones']);
    
    $FULL  = $USERS->full_list('pacientes_presupuestos',"WHERE paciente = {$P}");
    $FULL  = $GUSERS->queryToArray($FULL);
    $J_trt  = json_decode($FULL['tratamiento']);
    $J_int  = json_decode($FULL['integracion']);
    
    $PV     = $USERS->full_list('puntos_venta',"WHERE id = {$FULL['punto_venta']}");
    $PV     = $GUSERS->queryToArray($PV);
    
    $OS     = $USERS->full_list('os',"WHERE id = {$DATOS['os']}");
    $OS     = $GUSERS->queryToArray($OS);
    
    $PRE     = $USERS->listar('nombre,valor','prestaciones',"WHERE id = {$J_pre[0]}");
    $PRE     = $GUSERS->queryToArray($PRE);
    $fecha  = new DateTime();
    $formateador = new IntlDateFormatter(
        'es_ES',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'America/Argentina/Buenos_Aires',
        IntlDateFormatter::GREGORIAN,
        "d 'de' MMMM 'de' y"
    );


    
//echo'<pre>';print_r($J_trt);echo'</pre>';die;
//echo'<pre>';print_r($J_int);echo'</pre>';die;
    
    $cadathumb	= glob("../../img/logo/empresa/SYS-16029i/{*.JPG,*.jpg,*.tiff,*.bmp,*.jpeg,*.gif,*.png}",GLOB_BRACE);
    $empresa = $GBL_U[0]['empresa'];
    require('Write2HTML.php');

    $pdf = new PDF('P','mm','A4');
    if($cadathumb){
        $pdf->logo  = $cadathumb[0];
    }else{
        $pdf->logo  = "../../img/logo/orion.png";
    }

    $pdf->SetAuthor  ='M@D';
    $pdf->SetCreator  ='Sysmika';
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Documento
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, utf8_decode($_SESSION['sede']. ', '. $formateador->format($fecha)), 0, 1,'R');
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(100, 5, utf8_decode('Señores: '.$OS['nombre']), 0, 1);
    $pdf->Cell(100, 10, utf8_decode('Mediante la presente pongo a su disposición el presupuesto correspondiente al tratamiento de '), 0, 1);
    $pdf->Cell(100, 5, utf8_decode($PRE['nombre']), 0, 1);
    $pdf->Ln(10);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, utf8_decode('Nombre y Apellido'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(140, 8, utf8_decode($DATOS['nombre_completo']), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, utf8_decode('Fecha de Nacimiento'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(140, 8, utf8_decode($GUSERS->fechaes($DATOS['nacimiento'])), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, utf8_decode('Edad'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(140, 8, utf8_decode($GUSERS->edad($DATOS['nacimiento']).' años'), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, utf8_decode('DNI'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(140, 8, utf8_decode($DATOS['dni']), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(50, 8, utf8_decode('Diagnostico'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->WriteHTML(utf8_decode($DATOS['diagnostico']));
    $pdf->Ln(10);
        
    $pdf->Cell(50, 8, utf8_decode('Cantidad de sesiones'), 0, 1);
$array = (array) $J_trt->terapias;
foreach($array as $k => $v):
    if($v ?? null):
        $TR     = $USERS->listar('nombre','terapias',"WHERE id = {$k}");
        $pdf->Cell(50, 8, '', 0, 0);
        $pdf->Cell(140, 8, utf8_decode($v.' sesiones de  '.$TR[0]['nombre']), 0, 1);
    endif;
endforeach;
    
    
    $pdf->Cell(0, 8, utf8_decode('Valor Mensual: $'.number_format($PRE['valor'],2,',','.')), 0, 1);
    $pdf->Cell(0, 8, utf8_decode('Periodo: enero a diciembre '.$NEXTY), 0, 1);
    $pdf->Ln(6);
    
    $pdf->Cell(38, 8, utf8_decode('Lunes'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('Martes'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('Miércoles'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('Jueves'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('Viernes'), 1, 0,'C');
    $pdf->Ln(8);
    
    
    $pdf->Cell(38, 8, utf8_decode('//'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('//'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('//'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('//'), 1, 0,'C');
    $pdf->Cell(38, 8, utf8_decode('//'), 1, 0,'C');
    
    
    $pdf->Ln(10);
    $pdf->Cell(0, 6, utf8_decode('Prestador'), 0, 1);
    $pdf->Cell(0, 6, utf8_decode('Razón Social:  '.$PV['razon_social']), 0, 1);
    $pdf->Cell(0, 6, utf8_decode('Dirección:  '.$PV['direccion']), 0, 1);
    $pdf->Cell(0, 6, utf8_decode('Teléfono:  '.$PV['telefono'].' Correo elecronico '.$PV['email']), 0, 1);
    
    
    //$pdf->WriteHTML(utf8_decode($RC['documento']));
    //$pdf->Ln(3);

    $pdf->Output();
} catch(Exception $e) {
    
    
    // Mostrar información completa del error
    echo '<div class="w-100 border-start border-3 border-warning bg-warning bg-opacity-10 p-2">Error: ' . utf8_decode($e->getMessage()) . '<br>';
    echo "Código: " . $e->getCode() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "</div>";
    
    // También registrar en log
    error_log("Error AFIP: " . $e->getMessage());
}


?>