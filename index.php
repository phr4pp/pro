<?php
session_start();
$master = "ToneAVD!2025Pro";

if (isset($_POST['pw'])) {
    if ($_POST['pw'] === $master) {
        $_SESSION['auth'] = true;
    } else {
        $error = "Falsches Passwort!";
    }
}

if (!isset($_SESSION['auth'])):
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Login</title></head>
<body>
<form method="post">
  <p>Passwort eingeben:</p>
  <input type="password" name="pw">
  <button type="submit">Login</button>
  <?php if (!empty($error)) echo "<p>$error</p>"; ?>
</form>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="de" dir="ltr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>AVD PDF-Liste</title>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<div id="wrapper">
<div id="list">
<h1>PDFs</h1>
<div id="filesContainer" class="scroll"></div>
</div>
<div id="upload">
<h1>Upload</h1>
<div id="dragndrop" class="dropzone" tabindex="0">PDF hierher ziehen</div>
<div id="uploadbutton">
<button id="btnPick">PDF auswählen…</button>
<input id="fileInput" type="file" accept="application/pdf" hidden />
</div>
<div id="status"></div>
</div>
</div>
<script src="app.js"></script>
</body>
</html>
