<?php
/* regen.php
   Wenn POST JSON { "name":"vis11.pdf" } -> generiert Passwort nur für diese Datei
   Wenn GET / CLI -> prüft alle PDFs in _files und erzeugt fehlende Passwörter
*/
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

// Pfade
$filesDir = files_dir_path();
$passwordsFile = passwords_file_path();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = $body['name'] ?? '';
    list($ok, $res) = set_new_password($name);
    if ($ok) {
        echo json_encode(['ok' => true, 'name' => normalize_filename($name), 'password' => $res]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $res]);
    }
    exit;
}

// sonst: Bulk-Update (GET oder CLI)
$passwords = load_passwords();
$generated = [];
$errors = [];

if ($filesDir === false) {
    echo json_encode(['ok' => false, 'error' => 'Dateiordner nicht gefunden']);
    exit;
}

foreach (new DirectoryIterator($filesDir) as $fileInfo) {
    if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'pdf') {
        $filename = $fileInfo->getFilename();
        $current = $passwords[$filename] ?? null;
        if (!is_string($current) || trim($current) === '' || strtolower(trim((string)$current)) === 'null') {
            list($ok, $res) = set_new_password($filename);
            if ($ok) {
                $generated[$filename] = $res;
                // reload passwords array so we return up-to-date map
                $passwords = load_passwords();
            } else {
                $errors[$filename] = $res;
            }
        }
    }
}

echo json_encode(['ok' => true, 'generated' => $generated, 'errors' => $errors, 'passwords' => $passwords]);
