<?php
/* PURPOSE: Prüft alle PDF-Dateien im Ordner _files und generiert fehlende Passwörter */

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

// Pfad zum PDF-Ordner
$filesDir = __DIR__ . '/../_files/';

// Lade bestehende Passwörter
$passwordsFile = __DIR__ . '/passwords.json';
$passwords = [];

if (file_exists($passwordsFile)) {
    $passwords = json_decode(file_get_contents($passwordsFile), true);
    if (!is_array($passwords)) $passwords = [];
}

// Alle PDFs prüfen
$updated = false;
foreach (new DirectoryIterator($filesDir) as $fileInfo) {
    if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'pdf') {
        $filename = $fileInfo->getFilename();

        // Neues Passwort generieren, wenn es noch keins gibt oder ungültig ist
        if (!isset($passwords[$filename]) || empty($passwords[$filename]) || $passwords[$filename] === 'null') {
            $passwords[$filename] = bin2hex(random_bytes(8)); // 16 Zeichen
            $updated = true;
        }
    }
}

// Passwörter speichern, falls geändert
if ($updated) {
    if (file_put_contents($passwordsFile, json_encode($passwords, JSON_PRETTY_PRINT)) === false) {
        echo json_encode(['ok'=>false,'error'=>'Konnte passwords.json nicht speichern']);
        exit;
    }
}

echo json_encode(['ok'=>true, 'passwords'=>$passwords]);
?>
