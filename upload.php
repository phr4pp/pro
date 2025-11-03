<?php
// upload.php - sichere Upload-Verarbeitung für pro
// erwartet: lib.php im selben Verzeichnis

require_once __DIR__ . '/lib.php';

// Konfiguration: Ordner für Dateien (PDFs)
$filesDir = __DIR__ . '/_files';
if (!is_dir($filesDir)) @mkdir($filesDir, 0755, true);

// Hilfs: JSON-Ausgabe
function json_ok($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $arr));
    exit;
}
function json_err($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => (string)$msg]);
    exit;
}

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Only POST allowed', 405);
}

// Es gibt zwei Upload-Modi in manchen Setups: HTML-Form upload oder PUT/CLI.
// Wir behandeln klassisches $_FILES['file'] zuerst.
if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $origName = $_FILES['file']['name'];
    $tmpName = $_FILES['file']['tmp_name'];
    $baseName = basename($origName);
    $target = $filesDir . DIRECTORY_SEPARATOR . $baseName;

    // Sicherheits-Check: akzeptiere nur PDF (optional)
    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        // Falls du andere Endungen erlauben willst, passe es an
        // json_err('Only PDF allowed');
        // Für Kompatibilität zum Original erlauben wir andere Dateiendungen auch:
        // (wenn du es strikt willst, entkommentiere obere Zeile)
    }

    // Move uploaded file into place.
    // Wichtig: Wenn eine bestehende Datei überschrieben wird, wollen wir das Passwort erhalten.
    // Wir führen move_uploaded_file zuerst durch. Danach rufen wir ensure_password_entry($baseName) auf.
    if (!@move_uploaded_file($tmpName, $target)) {
        json_err('move_uploaded_file failed');
    }

    // File ist jetzt auf Ziel. Stelle sicher, dass ein Passwort-Eintrag existiert.
    $pw = ensure_password_entry($baseName);
    if ($pw === null) {
        // Sehr unwahrscheinlicher Fehler: wir konnten kein Passwort anlegen.
        // Versuche eine einfache Recovery: wenn es schon einen Eintrag in passwords.json gibt, benutze ihn.
        $pwdata = load_passwords_quiet();
        if (isset($pwdata[$baseName]) && isset($pwdata[$baseName]['password']) && is_string($pwdata[$baseName]['password']) && $pwdata[$baseName]['password'] !== '') {
            $pw = $pwdata[$baseName]['password'];
        } else {
            // Letzte Fallback: generiere ein temporäres Passwort und schreibe es (best effort)
            $pw = generate_password(10);
            // Versuche nochmal zu speichern (best effort, ohne Lock wrapper)
            with_passwords_lock(function($current) use ($baseName, $pw) {
                $current[$baseName] = [
                    'password' => $pw,
                    'created' => date('c'),
                    'fallback' => true
                ];
                return $current;
            });
        }
    }

    json_ok(['file' => $baseName, 'password' => $pw]);
}

// Wenn kein $_FILES, prüfe ob 'name' & body present (regen.php / CLI like)
// Optional: handle PUT raw upload where body contains file and name param provided.
if (isset($_POST['name']) && !empty($_POST['name']) && !empty($_FILES) === false) {
    // optional: handle e.g. base64 / url fetch, but leave default
    json_err('No file uploaded (missing $_FILES)');
}

json_err('No file uploaded');
