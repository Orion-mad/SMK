<?php
declare(strict_types=1);
require_once __DIR__.'/../../inc/conect.php';
require_once __DIR__.'/../../inc/session_boot.php';
require_post();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$conn = DB::get();

$WINDOW_MIN = 10; $MAX_FAILS = 5; $LOCK_MIN = 15;
$ip = $_SERVER['REMOTE_ADDR'] ?? ''; 
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Aceptar JSON o form-url-encoded
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $_POST = $json + $_POST;
    }
}

// Aceptar alias de nombres (email/user y password/pass)
$email = trim((string)($_POST['email'] ?? $_POST['user'] ?? ''));
$pass  = (string)($_POST['password'] ?? $_POST['pass'] ?? '');

if ($email === '' || $pass === '') {
    http_response_code(400);
    echo 'Faltan credenciales';
    exit;
}

// Rate limit
$stmt=$conn->prepare("SELECT window_start,fail_count,locked_until FROM login_counters WHERE email=? LIMIT 1");
$stmt->bind_param('s',$email); $stmt->execute();
$counter=$stmt->get_result()->fetch_assoc();

$now=new DateTimeImmutable('now');
if ($counter && !empty($counter['locked_until'])) {
    $locked=new DateTimeImmutable($counter['locked_until']);
    if ($locked > $now) {
        $secs = max(1, $locked->getTimestamp() - $now->getTimestamp());
        header('Retry-After: '.$secs);
        http_response_code(429);
        echo 'Cuenta bloqueada. Reintentá en ~'.ceil($secs/60).' min';
        exit;
    }
}

// Usuario
$stmt=$conn->prepare("SELECT id,nombre,apellido,email,movil,password_hash,is_admin,is_active FROM users WHERE email=? LIMIT 1");
$stmt->bind_param('s',$email); $stmt->execute();
$u=$stmt->get_result()->fetch_assoc();

if(!$u || (int)$u['is_active']!==1 || !password_verify($pass,$u['password_hash'])) {
    // actualizar contador
    if ($counter) {
        $windowStart=new DateTimeImmutable($counter['window_start']);
        $diffMin=(int)floor(($now->getTimestamp() - $windowStart->getTimestamp())/60);
        if ($diffMin >= $WINDOW_MIN) {
            $stmt=$conn->prepare("UPDATE login_counters SET window_start=NOW(), fail_count=1, locked_until=NULL, last_ip=?, last_user_agent=? WHERE email=?");
            $stmt->bind_param('sss',$ip,$ua,$email);
        } else {
            $fails=((int)$counter['fail_count'])+1;
            if ($fails >= $MAX_FAILS) {
                $stmt=$conn->prepare("UPDATE login_counters SET fail_count=?, locked_until=DATE_ADD(NOW(), INTERVAL ? MINUTE), last_ip=?, last_user_agent=? WHERE email=?");
                $stmt->bind_param('iisss',$fails,$LOCK_MIN,$ip,$ua,$email);
            } else {
                $stmt=$conn->prepare("UPDATE login_counters SET fail_count=?, last_ip=?, last_user_agent=? WHERE email=?");
                $stmt->bind_param('isss',$fails,$ip,$ua,$email);
            }
        }
        $stmt->execute();
    } else {
        $stmt=$conn->prepare("INSERT INTO login_counters (email,window_start,fail_count,last_ip,last_user_agent) VALUES (?,NOW(),1,?,?)");
        $stmt->bind_param('sss',$email,$ip,$ua); $stmt->execute();
    }

    http_response_code(401);
    echo 'Email o clave inválidos';
    exit;
}

// OK: limpiar contador y sesión
$stmt=$conn->prepare("DELETE FROM login_counters WHERE email=?"); 
$stmt->bind_param('s',$email); 
$stmt->execute();

session_regenerate_id(true);
$_SESSION['uid']=(int)$u['id'];
$_SESSION['email']=$u['email'];
$_SESSION['nombre']=$u['nombre'];
$_SESSION['apellido']=$u['apellido'];
$_SESSION['is_admin']=(int)$u['is_admin'];

http_response_code(204); // sin cuerpo
