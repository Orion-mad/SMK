<?php
declare(strict_types=1);
require_once __DIR__.'/../../inc/session_boot.php';
require_post();


header('Content-Type: text/plain; charset=utf-8');
$_SESSION=[];
if(ini_get('session.use_cookies')){
$p=session_get_cookie_params();
setcookie(session_name(),'/',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
http_response_code(204);
?>