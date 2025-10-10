<? /* FILE: /pro/lib.php
// PURPOSE: Helferfunktionen (Passwort-Generator, Storage) */ ?>
<?php
const DATA_DIR = __DIR__ . '/_data';
const FILES_DIR = __DIR__ . '/_files';
const PASS_FILE = DATA_DIR . '/passwords.json';


if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0755, true);
if (!file_exists(PASS_FILE)) file_put_contents(PASS_FILE, json_encode(new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


function load_passwords() {
$raw = file_get_contents(PASS_FILE);
$data = json_decode($raw, true);
return is_array($data) ? $data : [];
}


function save_passwords($arr) {
file_put_contents(PASS_FILE, json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}


function sanitize_filename($name) {
$name = basename($name);
if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) return false;
if (strtolower(substr($name, -4)) !== '.pdf') return false;
return $name;
}


function generate_password($length = 24) {
$digits = '123456789'; // keine 0
$lower = 'abcdefghijklmnopqrstuvwxyz';
$upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$special= '§$%&?!';


// Mindestens je 1 aus jeder Kategorie
$pwd = [];
$pwd[] = $digits[random_int(0, strlen($digits)-1)];
$pwd[] = $lower[random_int(0, strlen($lower)-1)];
$pwd[] = $upper[random_int(0, strlen($upper)-1)];
$pwd[] = $special[random_int(0, strlen($special)-1)];


$all = $digits . $lower . $upper . $special;
for ($i = 4; $i < $length; $i++) {
$pwd[] = $all[random_int(0, strlen($all)-1)];
}
// zufällig mischen
for ($i = count($pwd)-1; $i > 0; $i--) {
$j = random_int(0, $i);
$tmp = $pwd[$i]; $pwd[$i] = $pwd[$j]; $pwd[$j] = $tmp;
}
return implode('', $pwd);
}


function ensure_password_entry($filename) {
$filename = sanitize_filename($filename);
if (!$filename) return [false, 'Ungültiger Dateiname'];
$data = load_passwords();
if (!isset($data[$filename])) {
$pass = generate_password();
$data[$filename] = [
'password' => $pass,
'hash' => password_hash($pass, PASSWORD_DEFAULT),
'updated' => time()
];
save_passwords($data);
}
return [true, $data[$filename]];
}


function set_new_password($filename) {
    $passwordsFile = __DIR__ . '/passwords.json';
    $passwords = [];

    // Bestehende Passwörter laden
    if (file_exists($passwordsFile)) {
        $passwords = json_decode(file_get_contents($passwordsFile), true);
        if (!is_array($passwords)) {
            $passwords = [];
        }
    }

    // Prüfen, ob kein Passwort existiert oder Wert leer / null / ungültig ist
    if (
        !isset($passwords[$filename]) ||
        !$passwords[$filename] ||
        $passwords[$filename] === 'null' ||
        trim($passwords[$filename]) === ''
    ) {
        $passwords[$filename] = bin2hex(random_bytes(8)); // neues Passwort (16 Zeichen)
    }

    // JSON speichern
    $saved = file_put_contents($passwordsFile, json_encode($passwords, JSON_PRETTY_PRINT));

    if ($saved === false) {
        return [false, 'Konnte Passwortdatei nicht speichern'];
    }

    return [true, $passwords[$filename]];
}


?>


