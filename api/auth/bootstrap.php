<?php
declare(strict_types=1);
require_once __DIR__.'/../../inc/conect.php';
require_once __DIR__.'/../../inc/session_boot.php';
require_get();
header('Content-Type: application/json; charset=utf-8');


$out=['ok'=>false];
if(!is_logged()) { echo json_encode($out); exit; }


$conn=DB::get();
$uid=(int)$_SESSION['uid']; $is_admin=(int)$_SESSION['is_admin'];


// Armar menú según admin o permisos
if($is_admin===1){
$sql="SELECT m.id AS module_id,m.code AS module_code,m.name AS module_name,m.icon,m.sort_order AS msort,
i.id AS item_id,i.code AS item_code,i.name AS item_name,i.route,i.sort_order AS isort
FROM modules m JOIN module_items i ON i.module_id=m.id
WHERE m.is_active=1 AND i.is_active=1
ORDER BY msort,isort";
$rs=$conn->query($sql);
} else {
$stmt=$conn->prepare("SELECT m.id AS module_id,m.code AS module_code,m.name AS module_name,m.icon,m.sort_order AS msort,
i.id AS item_id,i.code AS item_code,i.name AS item_name,i.route,i.sort_order AS isort,
p.can_create,p.can_edit,p.can_delete
FROM user_permissions p
JOIN module_items i ON i.id=p.item_id AND i.is_active=1
JOIN modules m ON m.id=i.module_id AND m.is_active=1
WHERE p.user_id=? ORDER BY msort,isort");
$stmt->bind_param('i',$uid); $stmt->execute(); $rs=$stmt->get_result();
}
$perms=[]; while($row=$rs->fetch_assoc()){
$mod=$row['module_code'];
if(!isset($perms[$mod])){
$perms[$mod]=[
'module_id'=>(int)$row['module_id'],
'module_code'=>$row['module_code'],
'module_name'=>$row['module_name'],
'icon'=>$row['icon'],
'items'=>[]
];
}
$perms[$mod]['items'][]=[
'item_id'=>(int)$row['item_id'],
'item_code'=>$row['item_code'],
'item_name'=>$row['item_name'],
'route'=>$row['route'],
'can_create'=>isset($row['can_create'])?(int)$row['can_create']:1,
'can_edit'=>isset($row['can_edit'])?(int)$row['can_edit']:1,
'can_delete'=>isset($row['can_delete'])?(int)$row['can_delete']:1,
];
}


$out=[
'ok'=>true,
'user'=>[
'id'=>$uid,
'email'=>$_SESSION['email'],
'nombre'=>$_SESSION['nombre'],
'apellido'=>$_SESSION['apellido'],
'is_admin'=>$is_admin
],
'menu'=>array_values($perms)
];


echo json_encode($out);

?>