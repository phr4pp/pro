<?php
// lib.php
// Robust password management for pro project
// Replaces/implements loading, atomic saving and safe ensure/set password operations
// Requirements: PHP 7+

// ---- Konfiguration (anpassen falls dein Repo andere Pfade nutzt) ----
define('DATA_DIR', __DIR__ . '/_data');                 // Verzeichnis in Repo (default _data)
define('PASSWORDS_FILE', DATA_DIR . '/passwords.json'); // zentrale JSON-Datei
define('PASSWORDS_LOCK', DATA_DIR . '/passwords.lock'); // Lockfile für flock

// ---- Hilfsfunktionen ----

/**
 * Stelle sicher, dass DATA_DIR existiert und beschreibbar ist.
 */
function ensure_data_dir() {
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
}

/**
 * Führe eine callback-Closure unter exklusivem Lock auf PASSWORDS_LOCK aus.
 * Die Closure erhält ein Array (die aktuelle passwords.json parsed) und muss das neue Array zurückgeben.
 * Die Funktion sorgt für Lesen, Übergabe an die Callback und atomaren Write.
 *
 * Returns: array|null -> bei Erfolg das aktuelle (neu geschriebene) Password-Array, bei Fehler null.
 */
function with_passwords_lock(callable $cb) {
    ensure_data_dir();

    // Öffne Lockfile (create if not exists)
    $lockFp = @fopen(PASSWORDS_LOCK, 'c+');
    if ($lockFp === false) {
        // can't open lock file
        return null;
    }

    // Exclusives blockierendes Lock
    if (!flock($lockFp, LOCK_EX)) {
        fclose($lockFp);
        return null;
    }

    // Innerhalb des Locks: sicher lesen, ausführen, schreiben
    $current = [];
    if (is_readable(PASSWORDS_FILE)) {
        $raw = @file_get_contents(PASSWORDS_FILE);
        $decoded = @json_decode($raw, true);
        if (is_array($decoded)) $current = $decoded;
        else $current = [];
    } else {
        $current = [];
    }

    // Rufe Callback an - muss ein Array zurückgeben (neue Daten) oder null bei Abbruch
    $new = $cb($current);
    if (!is_array($new)) {
        // Release Lock und return null (Abbruch)
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        return null;
    }

    // Schreibe sicher in tmp und rename
    $tmpFile = PASSWORDS_FILE . '.tmp';
    $encoded = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        return null;
    }

    $ok = @file_put_contents($tmpFile, $encoded);
    if ($ok === false) {
        // Release lock
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        return null;
    }

    // Optional: flush to disk (best effort)
    clearstatcache(true, $tmpFile);

    // Atomischer Rename
    @rename($tmpFile, PASSWORDS_FILE);

    // Release Lock
    flock($lockFp, LOCK_UN);
    fclose($lockFp);

    return $new;
}

/**
 * Lese passwords.json (schnelle nicht-locked Lesung).
 * Verwende dies nur für nicht-kritische Lesungen; für konsistente Lese+Write benutze with_passwords_lock.
 */
function load_passwords_quiet() {
    if (!is_readable(PASSWORDS_FILE)) return [];
    $raw = @file_get_contents(PASSWORDS_FILE);
    $decoded = @json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Generiere ein sicheres, gut lesbares Passwort (Buchstaben+Zahlen, ohne leicht verwechselbare Zeichen).
 */
function generate_password($len = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($chars) - 1;
    $out = '';
    try {
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
    } catch (Exception $e) {
        // Fallback falls random_int nicht verfügbar
        for ($i = 0; $i < $len; $i++) $out .= $chars[mt_rand(0, $max)];
    }
    return $out;
}

/**
 * Ensure password entry for $filename:
 * - If an existing valid password entry for the filename exists -> return it (no change)
 * - If entry missing or invalid -> create a new password, store it and return it
 *
 * This function uses with_passwords_lock() so that concurrent calls are serialized.
 * Returns string(password) on success, or null on fatal error.
 */
function ensure_password_entry($filename) {
    $filename = basename((string)$filename);
    if ($filename === '') return null;

    $attempts = 0;
    while ($attempts < 5) {
        $attempts++;
        $result = with_passwords_lock(function($current) use ($filename) {
            // Validate current entry for $filename
            if (isset($current[$filename]) && is_array($current[$filename])) {
                if (isset($current[$filename]['password']) && is_string($current[$filename]['password']) && $current[$filename]['password'] !== '' && strtolower($current[$filename]['password']) !== 'null') {
                    // valid password exists -> do nothing
                    return $current;
                }
            }
            // Create new password entry
            $pw = generate_password(10);
            $current[$filename] = [
                'password' => $pw,
                'created' => date('c'),
            ];
            return $current;
        });

        if (is_array($result)) {
            // read back resulting password safely
            $final = load_passwords_quiet();
            if (isset($final[$filename]) && isset($final[$filename]['password']) && is_string($final[$filename]['password'])) {
                return $final[$filename]['password'];
            } else {
                // strange: didn't find it - retry
                usleep(30000);
                continue;
            }
        } else {
            // lock or write failed - small backoff then retry
            usleep(50000 + random_int(0, 50000));
            continue;
        }
    }

    // failed after retries
    return null;
}

/**
 * Setze explizit ein neues Passwort (z.B. durch UI-Button).
 * Gibt das neue Passwort zurück oder null bei Fehler.
 */
function set_new_password($filename) {
    $filename = basename((string)$filename);
    if ($filename === '') return null;

    $result = with_passwords_lock(function($current) use ($filename) {
        $pw = generate_password(12);
        $current[$filename] = [
            'password' => $pw,
            'created' => date('c'),
            'regenerated' => true
        ];
        return $current;
    });

    if (is_array($result) && isset($result[$filename]['password'])) {
        return $result[$filename]['password'];
    }
    return null;
}

/**
 * Optional: Remove entry (if you want to delete a file entry)
 */
function remove_password_entry($filename) {
    $filename = basename((string)$filename);
    if ($filename === '') return false;

    $result = with_passwords_lock(function($current) use ($filename) {
        if (isset($current[$filename])) unset($current[$filename]);
        return $current;
    });

    return is_array($result);
}
