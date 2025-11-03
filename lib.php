<?php
// lib.php – überarbeitete Version (2025-11) auf Basis des Original-Repos phr4pp/pro
// Stellt sicher:
// 1) Bestehende PDFs behalten ihr Passwort beim Überschreiben
// 2) Neue PDFs bekommen ein neues Passwort
// 3) Parallele Uploads sind sicher (Dateisperre)
// 4) FILES_DIR ist immer definiert

define('FILES_DIR', __DIR__ . '/_files');
define('PASSWORDS_FILE', __DIR__ . '/passwords.json');

// ----------------------------------------------------------
// Hilfsfunktion: JSON-Datei sicher lesen
// ----------------------------------------------------------
function load_passwords() {
    if (!file_exists(PASSWORDS_FILE)) {
        file_put_contents(PASSWORDS_FILE, '{}');
    }

    $fp = fopen(PASSWORDS_FILE, 'r');
    if (!$fp) return [];

    // Datei beim Lesen kurz sperren
    flock($fp, LOCK_SH);
    $data = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $json = json_decode($data, true);
    if (!is_array($json)) $json = [];
    return $json;
}

// ----------------------------------------------------------
// Hilfsfunktion: JSON-Datei sicher speichern
// ----------------------------------------------------------
function save_passwords($passwords) {
    $fp = fopen(PASSWORDS_FILE, 'c+');
    if (!$fp) return false;

    // exklusive Sperre während Schreibvorgang
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($passwords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

// ----------------------------------------------------------
// Passwort für Datei holen oder neu erzeugen
// ----------------------------------------------------------
function get_password($filename) {
    $passwords = load_passwords();

    // Passwort existiert → behalten
    if (isset($passwords[$filename]) && !empty($passwords[$filename])) {
        return $passwords[$filename];
    }

    // Neues Passwort für neue Datei
    $newPass = generate_password();
    $passwords[$filename] = $newPass;
    save_passwords($passwords);
    return $newPass;
}

// ----------------------------------------------------------
// Passwort zufällig generieren
// ----------------------------------------------------------
function generate_password($length = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

// ----------------------------------------------------------
// Funktion: Passwort gezielt neu generieren (Button im UI)
// ----------------------------------------------------------
function regenerate_password($filename) {
    $passwords = load_passwords();
    $newPass = generate_password();
    $passwords[$filename] = $newPass;
    save_passwords($passwords);
    return $newPass;
}

// ----------------------------------------------------------
// Funktion: Passwort löschen (wenn Datei gelöscht wird)
// ----------------------------------------------------------
function delete_password($filename) {
    $passwords = load_passwords();
    unset($passwords[$filename]);
    save_passwords($passwords);
}
?>
