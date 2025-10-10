KURZANLEITUNG:
1) Ordnerstruktur in /pro/ hochladen:
- index.html, style.css, app.js, .htaccess, lib.php, list.php, regen.php, upload.php, view.php
- Verzeichnisse: _files/ (PDFs), _data/ (passwords.json) – je ein .htaccess mit "Deny from all"
2) Stelle sicher, dass PHP-Dateien laufen und Apache mod_rewrite aktiv ist.
3) Öffne https://www.walling.at/pro/ – lade ein PDF hoch.
4) Liste zeigt alle PDFs. Klick auf Dateinamen kopiert URL + Passwort.
5) Aufruf über https://www.walling.at/pro/DATEI.pdf → Passwort-Prompt → korrekte Eingabe zeigt die PDF.
