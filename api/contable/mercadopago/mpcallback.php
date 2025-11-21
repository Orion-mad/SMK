<?php
$NOSESS = true;
require_once("../../inc/concect_pagos.php");
include_once("../../inc/classes/mercadopago.php");   	
$Q_MP     = $mysqli->query("SELECT MP_clave,MP_id FROM empresa WHERE id = 1")or die($mysqli->error);
$MP       = $Q_MP->fetch_assoc();

$mp				= new MP($MP['MP_id'], $MP['MP_clave']);
$accessToken 	= $mp->get_access_token();
//echo'<pre>1';print_r($accessToken);echo'</pre>';die;
///6290047430
/*
$filters = array(
                    "external_reference" => '48-1620205250'
                );
$RS=  $mp->search_payment($filters, 0, 1);
echo'<pre>';print_r($RS);echo'</pre>';die;
echo'<pre>';print_r( $mp->get_payment_info($_GET["id"]));echo'</pre>';
*/
$payment_info = $mp->get_payment_info($_GET["id"]);
//echo $payment_info["response"]["status"];
/*
*/
$enc    = json_encode($payment_info);
$file   = fopen("response.txt", "a") or die("Se produjo un error al abrir el archivo");
fwrite($file, date("d-m-Y")." MPconsulta # ".$enc."\r\n");
fclose($file);
echo'<pre>';print_r( $mp->get_payment_info($_GET["id"]));echo'</pre>';
//
//die;
// Get the payment reported by the IPN. Glossary of attributes response in https://developers.mercadopago.com
// Show payment information
$mysqli->query("INSERT INTO mp SET data='".$enc."'")or die($mysqli->error);
if ($payment_info["status"] == 200) {
$mysqli->query("UPDATE pagos_mp SET estado='".$payment_info["response"]["status"]."', comision='".$payment_info['response']['transaction_details']['net_received_amount']."' WHERE mpcod='".$payment_info["response"]["external_reference"]."'")or die($mysqli->error);
    $lst = $CNSLTS->listar('monto,estado,aviso,cliente,mpcod,compra,fecha','pagos_mp',"WHERE mpcod = '".$payment_info["response"]["external_reference"]."'");
    $cli = $CNSLTS->listar('nombre,apellido,mail','clientes',"WHERE id = '".$lst[0]['cliente']."'");
                /*
                $A_cpra = json_decode($lst[0]["compra"]);trim($cpr,',')
                $cpr    = '';
                foreach($A_cpra[1] as $K => $V){ $cpr .= $V.','; }
                */

    $template = file_get_contents('../../tmpls/mail_notificaciones.tpl');
    
     $template = str_replace(
        array("<!-- #{Subject} -->", "<!-- #{SiteName} -->", "<!-- #{Title} -->"),
        array('Pago exitoso', 'SYSMIKA','Notificación'),
        $template);
//echo'<pre>';var_dump($result);echo'</pre>';//die;
    $info   = "Estimado  ".$cli[0]['nombre']." ".$cli[0]['apellido']." (".$cli[0]['mail'].") , El pago de ".$lst[0]["compra"]." con fecha ".$lst[0]['fecha'].", por un monto de $ ".$lst[0]['monto']." fue procesado con exito";
    $template = str_replace("<!-- #{MessageDescription} -->", $info, $template); 
    $data   = "<small>Operación MercadoPago: ".$lst[0]['mpcod']." , Estado: ".$lst[0]['estado']." <br>solicitado el ".$lst[0]['fecha'].", monto $ ".$lst[0]['monto']." </small>";
    $template = str_replace("<!-- #{MessageState} -->", $data, $template);
    
  if(($payment_info["response"]["status"] == 'approved')  and ($lst[0]['aviso'] == 'no')) {
////////////////////// ENVIOS MAIL DE CONFIRMACION //////////////////////////
	$mail->IsSMTP();      
	$mail->Host 	= "mail.sysmika.com.ar";  // specify main and backup server
	$mail->SMTPAuth = true;     // turn on SMTP authentication
	$mail->Username = "noreply@sysmika.com.ar";  // SMTP username
	$mail->Password = "0800miguel60"; // SMTP password
	$mail->From 	= 'admin@sysmika.com';
	$mail->FromName = 'Sysmika wc';        // remitente
    $mail->AddAddress($cli[0]['mail'], $cli[0]['nombre'].' '.$cli[0]['apellido']);        // destinatario
	$mail->AddBCC('admin@sysmika.com');        // copia oculta
	//$mail->AddReplyTo("admin@sysmika.com", "Sysmika Web Concept");    // responder a
	$mail->WordWrap = 50;     // set word wrap to 50 characters
	$mail->IsHTML(true);     // set email
	$mail->CharSet = 'UTF-8';
	$mail->Subject = 'Notificación Sysmika WC';
    $mail->MsgHTML($template);
    $mail->Send();
    $mysqli->query("UPDATE pagos_mp SET aviso = 'si' WHERE mpcod='".$payment_info["response"]["external_reference"]."'")or die($mysqli->error);
  }
}

?>

