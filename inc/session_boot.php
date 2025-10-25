<?php
// Config de sesión + helpers comunes
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
// Si usás HTTPS, activá también cookie_secure
// ini_set('session.cookie_secure', '1');


if (session_status() === PHP_SESSION_NONE) {
session_start();
}


function require_post(){ if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); exit; } }
function require_get(){ if($_SERVER['REQUEST_METHOD']!=='GET'){ http_response_code(405); exit; } }
function is_logged(): bool { return !empty($_SESSION['uid']); }
?>