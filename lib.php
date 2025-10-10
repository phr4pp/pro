<?php
/* FILE: /pro/lib.php
// PURPOSE: Helferfunktionen (Passwort-Generator, Storage)
*/

const ROOT_DIR  = __DIR__;                  // /pro
const DATA_DIR  = ROOT_DIR . '/_data';      // /pro/_data
const FILES_DIR = ROOT_DIR . '/_files';     // /pro/_files'
const PASS_FILE = DATA_DIR . '/passwords.json'; // /pro/_data/passwords.json


// --- Initialisierung ---
if (!is_dir(DATA_DIR))  mkdir(DATA_DIR, 0755, true);
if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0755, true);
if (!file_exists(PASS_FILE)) file_put_contents(PASS_FILE, json_encode(new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


// ----------------------------------------------------------
// Hilfsfunktionen
// ----------------------------------------------------------

function sanitize_filename($name) {
    $name = basename(trim($name));
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) return false;
    if (strtolower(substr($name, -4)) !== '.pdf') return false;
    return $name;
}

function generate_password($length = 24) {
    $digits  = '123456789';
    $lower   = 'abcdefghijklmnopqrstuvwxyz';
    $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $special = '§$%&?!';

    $pwd = [];
    $pwd[] = $digits[random_int(0, strlen($digits) - 1)];
    $pwd[] = $lower[random_int(0, strlen($lower) - 1)];
    $pwd[] = $upper[random_int(0, strlen($upper) - 1)];
    $pwd[] = $special[random_int(0, strlen($special) - 1)];

    $all = $digits . $lower . $upper . $special;
    for ($i = 4; $i < $length; $i++) {
        $pwd[] = $all[random_int(0, strlen($all) - 1)];
    }

    // shuffle
    for ($i = count($pwd) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$pwd[$i], $pwd[$j]] = [$pwd[$j], $pwd[$i]];
    }

    return implode('', $pwd);
}

// ----------------------------------------------------------
// Passwort-Management
// ----------------------------------------------------------

function load_passwords() {
    $raw = file_get_contents(PASS_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_passwords($arr) {
    // Atomar schreiben, um beschädigte Dateien zu vermeiden
    $json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    $tmp = PASS_FILE . '.tmp';
    $bytes = file_put_contents($tmp, $json, LOCK_EX);
    if ($bytes === false) return false;

    if (!rename($tmp, PASS_FILE)) {
        @unlink($tmp);
        return false;
    }

    return true;
}

// Erstellt fehlende Passwörter, lässt bestehende unberührt
function ensure_password_entry($filename) {
    $filename = sanitize_filename($filename);
    if (!$filename) return [false, 'Ungültiger Dateiname'];

    $data = load_passwords();

    // Wenn bereits ein gültiges Passwort existiert → behalten
    if (
        isset($data[$filename]['password']) &&
        is_string($data[$filename]['password']) &&
        trim(strtolower($data[$filename]['password'])) !== '' &&
        trim(strtolower($data[$filename]['password'])) !== 'null'
    ) {
        return [true, $data[$filename]];
    }

    // Neues Passwort erzeugen
    $pass = generate_password();
    $data[$filename] = [
        'password' => $pass,
        'hash'     => password_hash($pass, PASSWORD_DEFAULT),
        'updated'  => time()
    ];

    if (!save_passwords($data)) {
        return [false, 'Konnte Passwortdatei nicht speichern'];
    }

    return [true, $data[$filename]];
}

// Erzwingt immer ein neues Passwort (z. B. „Passwort neu generieren“-Button)
function set_new_password($filename) {
    $filename = sanitize_filename($filename);
    if (!$filename) return [false, 'Ungültiger Dateiname'];

    $data = load_passwords();
    $pass = generate_password();

    $data[$filename] = [
        'password' => $pass,
        'hash'     => password_hash($pass, PASSWORD_DEFAULT),
        'updated'  => time()
    ];

    if (!save_passwords($data)) {
        return [false, 'Konnte Passwortdatei nicht speichern'];
    }

    return [true, $data[$filename]];
}

?>

