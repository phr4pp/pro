<?php

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');



if (!isset($_FILES['pdf'])) { echo json_encode(['ok'=>false,'error'=>'Keine Datei übergeben']); exit; }

$f = $_FILES['pdf'];



if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'error'=>'Upload-Fehler code '.$f['error']]); exit; }



$original = basename($f['name']);

if (strtolower(substr($original,-4)) !== '.pdf') { echo json_encode(['ok'=>false,'error'=>'Nur PDF-Dateien erlaubt']); exit; }



$san = sanitize_filename($original);

if ($san === false) { echo json_encode(['ok'=>false,'error'=>'Ungültiger Dateiname. Nur A-Z a-z 0-9 . _ - erlaubt.']); exit; }



if (!is_dir(FILES_DIR) && !mkdir(FILES_DIR, 0755, true)) { echo json_encode(['ok'=>false,'error'=>'Zielverzeichnis fehlt und konnte nicht erstellt werden']); exit; }



$target = FILES_DIR . '/' . $san;

if (move_uploaded_file($tmpName, $target)) {
    // sichere Passwort-Zuweisung
$pw = null;
if (function_exists('ensure_password_entry')) {
    $pw = ensure_password_entry(basename($target));
}

// Rückgabe an Client
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'file' => basename($target), 'password' => $pw]);
exit;

}




// Passwort sicherstellen

list($ok, $res) = ensure_password_entry($san);

if (!$ok) { echo json_encode(['ok'=>false,'error'=>'Passwort konnte nicht erzeugt werden: '.$res]); exit; }



echo json_encode(['ok'=>true, 'file'=>$san]);

?>



