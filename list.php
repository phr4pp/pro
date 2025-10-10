<?php
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

$out = ['files'=>[]];
$files = [];
if (is_dir(FILES_DIR)) {
  $files = array_values(array_filter(scandir(FILES_DIR), fn($f)=>preg_match('/\.pdf$/i',$f)));
} else {
  // Fallback: Suche im aktuellen Ordner (nur zu Debugging)
  $files = array_values(array_filter(scandir(__DIR__), fn($f)=>preg_match('/\.pdf$/i',$f)));
}

$data = load_passwords();

foreach ($files as $f) {
  if (!isset($data[$f])) {
    // versuche Eintrag anzulegen; wenn das scheitert, bleibt password null
    ensure_password_entry($f);
    $data = load_passwords();
  }
  $out['files'][] = ['name'=>$f, 'password'=> $data[$f]['password'] ?? null];
}

$out['passfile_exists'] = file_exists(PASS_FILE);
$out['passfile_writable'] = is_writable(PASS_FILE);
echo json_encode($out, JSON_UNESCAPED_SLASHES);
?>
