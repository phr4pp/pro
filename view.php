<? /* PURPOSE: Fragt Passwort ab und liefert PDF bei korrekter Eingabe. Wird per .htaccess für /pro/*.pdf aufgerufen. */ ?>
<?php
require __DIR__ . '/lib.php';
$fname = $_GET['f'] ?? '';
$fname = sanitize_filename($fname);
if (!$fname) { http_response_code(404); echo 'Datei nicht gefunden.'; exit; }
$path = FILES_DIR . '/' . $fname;
if (!file_exists($path)) { http_response_code(404); echo 'Datei nicht gefunden.'; exit; }


list($ok, $info) = ensure_password_entry($fname);
if (!$ok) { http_response_code(500); echo 'Passwortproblem.'; exit; }


$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$pass = $_POST['pass'] ?? '';
if ($pass && password_verify($pass, $info['hash'])) {
// Korrekt – PDF senden
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fname . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile($path);
exit;
} else {
$err = 'Passwort falsch, erneut eingeben';
}
}


// Formular anzeigen
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Passwort eingeben</title>
<style>
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; display: grid; place-items: center; min-height: 100vh; margin:0; background:#f7f7f9; }
.card { background: #fff; padding: 24px; border-radius: 12px; width: min(420px, 92vw); box-shadow: 0 6px 24px rgba(0,0,0,0.08); }
h1 { font-size: 18px; margin: 0 0 12px; }
form { display: grid; gap: 12px; }
input[type=password] { padding: 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 10px; width: 100%; }
button { padding: 12px; font-size: 16px; border-radius: 10px; border: 1px solid #0d6efd; background: #0d6efd; color: #fff; cursor: pointer; }
.err { color: #b00020; font-size: 14px; min-height: 18px; }
.hint { font-size: 12px; color: #666; }
</style>
</head>
<body>
<div class="card">
<h1>Passwort eingeben</h1>
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>">
<input type="password" name="pass" placeholder="Passwort" autofocus required />
<div class="err"><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
<button type="submit">Anzeigen</button>
<div class="hint">Datei: <?php echo htmlspecialchars($fname, ENT_QUOTES); ?></div>
</form>
</div>
</body>
</html>
