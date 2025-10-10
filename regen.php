<? /* PURPOSE: Passwort neu generieren für eine Datei (POST JSON: { name }) */ ?>
<?php
require __DIR__ . '/lib.php';
$body = json_decode(file_get_contents('php://input'), true);
$name = $body['name'] ?? '';
list($ok, $res) = set_new_password($name);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($ok ? ['ok'=>true] : ['ok'=>false,'error'=>$res]);
?>
