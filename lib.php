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



// ------------------------
// BEGIN: Safe passwords helper (append to lib.php)
// ------------------------

// Sicherstellen, dass wir nicht vorhandene Funktionen überschreiben
if (!function_exists('with_passwords_lock')) {
    /**
     * Führt Callback unter exklusivem Lock aus. Callback bekommt aktuellen array und muss neuen array zurückgeben.
     * Returns array on success, null on failure.
     */
    function with_passwords_lock(callable $cb) {
        $dataDir = __DIR__; // Passwortdatei im Repo root (wie original)
        $passwordFile = $dataDir . '/passwords.json';
        $lockFile = $dataDir . '/passwords.lock';

        // Ensure file exists
        if (!file_exists($passwordFile)) {
            @file_put_contents($passwordFile, "{}");
        }

        // open lock file
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp === false) return null;

        if (!flock($lockFp, LOCK_EX)) { fclose($lockFp); return null; }

        // read current
        $raw = @file_get_contents($passwordFile);
        $current = @json_decode($raw, true);
        if (!is_array($current)) $current = [];

        // call callback
        $new = $cb($current);
        if (!is_array($new)) {
            flock($lockFp, LOCK_UN); fclose($lockFp);
            return null;
        }

        // write tmp then rename (atomic-ish)
        $tmp = $passwordFile . '.tmp';
        $enc = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($enc === false) { flock($lockFp, LOCK_UN); fclose($lockFp); return null; }
        if (@file_put_contents($tmp, $enc) === false) { flock($lockFp, LOCK_UN); fclose($lockFp); return null; }
        @rename($tmp, $passwordFile);

        flock($lockFp, LOCK_UN); fclose($lockFp);
        return $new;
    }
}

if (!function_exists('generate_password_safe')) {
    function generate_password_safe($len = 10) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($chars) - 1;
        $out = '';
        try {
            for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, $max)];
        } catch (Exception $e) {
            for ($i = 0; $i < $len; $i++) $out .= $chars[mt_rand(0, $max)];
        }
        return $out;
    }
}

/**
 * ensure_password_entry_safe($filename)
 * - wenn bereits ein gültiges password existiert -> unverändert zurückgeben
 * - sonst -> neues password erzeugen, unter Lock speichern und zurückgeben
 *
 * Valid-Check: existierender Eintrag muss ein non-empty string sein und nicht "null".
 */
if (!function_exists('ensure_password_entry_safe')) {
    function ensure_password_entry_safe($filename) {
        $filename = basename(urldecode((string)$filename));
        if ($filename === '') return null;

        // Retry loop für den seltenen Fall eines Race beim Schreiben (best effort)
        $attempts = 0;
        while ($attempts < 6) {
            $attempts++;
            $res = with_passwords_lock(function($current) use ($filename) {
                // gültiges password prüfen
                if (isset($current[$filename]) && is_string($current[$filename]) && $current[$filename] !== '' && strtolower($current[$filename]) !== 'null') {
                    // leave unchanged
                    return $current;
                }
                // Erzeuge neues Passwort
                $pw = generate_password_safe(10);
                $current[$filename] = $pw;
                return $current;
            });

            if (is_array($res)) {
                // lese finalen Wert zurück (non-locked quick read)
                $pwdata = @json_decode(@file_get_contents(__DIR__ . '/passwords.json'), true);
                if (is_array($pwdata) && isset($pwdata[$filename]) && is_string($pwdata[$filename]) && $pwdata[$filename] !== '' && strtolower($pwdata[$filename]) !== 'null') {
                    return $pwdata[$filename];
                }
            }

            // Backoff + jitter
            usleep(20000 + random_int(0,50000));
        }

        return null;
    }
}

// Convenience alias: falls ensure_password_entry bereits existiert in your code, don't overwrite it.
// But if it's missing or buggy, you can call ensure_password_entry_safe from upload.php.
if (!function_exists('ensure_password_entry')) {
    function ensure_password_entry($filename) {
        return ensure_password_entry_safe($filename);
    }
}

// ------------------------
// END: Safe passwords helper (append to lib.php)
// ------------------------
// ------------------------
// Safe Password Helper (append to lib.php)
// ------------------------

if (!function_exists('with_passwords_lock')) {
    function with_passwords_lock(callable $cb) {
        $passwordFile = __DIR__ . '/passwords.json';
        $lockFile = __DIR__ . '/passwords.lock';

        if (!file_exists($passwordFile)) {
            @file_put_contents($passwordFile, '{}');
        }

        $lockFp = @fopen($lockFile, 'c+');
        if (!$lockFp) return null;
        if (!flock($lockFp, LOCK_EX)) { fclose($lockFp); return null; }

        $data = @file_get_contents($passwordFile);
        $current = json_decode($data, true);
        if (!is_array($current)) $current = [];

        $new = $cb($current);
        if (!is_array($new)) { flock($lockFp, LOCK_UN); fclose($lockFp); return null; }

        $tmp = $passwordFile . '.tmp';
        $enc = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($enc === false) { flock($lockFp, LOCK_UN); fclose($lockFp); return null; }
        @file_put_contents($tmp, $enc);
        @rename($tmp, $passwordFile);

        flock($lockFp, LOCK_UN); fclose($lockFp);
        return $new;
    }
}

if (!function_exists('generate_password_safe')) {
    function generate_password_safe($len = 10) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($chars) - 1;
        $out = '';
        try {
            for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, $max)];
        } catch (Exception $e) {
            for ($i = 0; $i < $len; $i++) $out .= $chars[mt_rand(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('ensure_password_entry_safe')) {
    function ensure_password_entry_safe($filename) {
        $filename = basename(urldecode((string)$filename));
        if ($filename === '') return null;

        $attempts = 0;
        while ($attempts < 5) {
            $attempts++;
            $res = with_passwords_lock(function($current) use ($filename) {
                if (isset($current[$filename]) && is_string($current[$filename]) && $current[$filename] !== '' && strtolower($current[$filename]) !== 'null') {
                    return $current;
                }
                $current[$filename] = generate_password_safe(10);
                return $current;
            });
            if (is_array($res) && isset($res[$filename])) return $res[$filename];
            usleep(20000 + random_int(0,50000));
        }
        return null;
    }
}

if (!function_exists('ensure_password_entry')) {
    function ensure_password_entry($filename) {
        return ensure_password_entry_safe($filename);
    }
}

?>






