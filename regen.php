<?php
// regen.php
// POST JSON { "name":"vis11.pdf" }  -> gibt JSON zurück: { ok: true, name: "...", password: "..." }
// GET / CLI -> bulk: prüft alle PDFs und ergänzt fehlende Passwörter (wie vorher)

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

// 1) robustes Einlesen des Requests (JSON oder form-data)
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    // falls kein JSON: versuche parse_str (form-urlencoded) oder $_POST
    parse_str($raw, $parsed);
    $body = is_array($parsed) && count($parsed) ? $parsed : $_POST;
}

// helper: hole name aus body / post / get
$name = $body['name'] ?? ($_POST['name'] ?? ($_GET['name'] ?? ''));

// Wenn POST mit name -> generiere (force) neues Passwort und antworte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim($name) !== '') {
    // sanitize_filename kommt aus lib.php
    $san = function_exists('sanitize_filename') ? sanitize_filename($name) : basename(trim($name));
    if ($san === false || $san === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ungültiger Dateiname']);
        exit;
    }

    list($ok, $res) = set_new_password($san); // set_new_password erzeugt ein neues PW und speichert es
    if ($ok) {
        echo json_encode(['ok' => true, 'name' => $san, 'password' => $res]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $res]);
        exit;
    }
}

// Sonst: Bulk-Mode (GET / CLI) -> ergänze fehlende Einträge für alle PDFs
$passwords = function_exists('load_passwords') ? load_passwords() : [];
$filesDir = defined('FILES_DIR') ? FILES_DIR : (__DIR__ . '/_files');

$generated = [];
$errors = [];

if (is_dir($filesDir)) {
    foreach (new DirectoryIterator($filesDir) as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'pdf') {
            $filename = $fileInfo->getFilename();
            $current = $passwords[$filename] ?? null;
            if (!is_string($current) || trim($current) === '' || strtolower(trim((string)$current)) === 'null') {
                // set_new_password muss existieren in lib.php
                list($ok, $res) = set_new_password($filename);
                if ($ok) {
                    $generated[$filename] = $res;
                    $passwords = load_passwords(); // reload
                } else {
                    $errors[$filename] = $res;
                }
            }
        }
    }
}

echo json_encode(['ok' => true, 'generated' => $generated, 'errors' => $errors, 'passwords' => $passwords]);
