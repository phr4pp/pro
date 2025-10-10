// FILE: /pro/app.js
// PURPOSE: Frontend-Logik (Listen, Clipboard, Upload, Passwort neu)

(() => {
const filesContainer = document.getElementById('filesContainer');
const status = document.getElementById('status');
const drop = document.getElementById('dragndrop');
const pick = document.getElementById('btnPick');
const input = document.getElementById('fileInput');


async function fetchList() {
filesContainer.innerHTML = 'Lade…';
const res = await fetch('list.php');
const data = await res.json();
renderList(data.files || []);
}


function renderList(files) {
if (!files.length) { filesContainer.innerHTML = '<div>Keine PDFs gefunden.</div>'; return; }
filesContainer.innerHTML = '';
for (const f of files) {
if (!f.name.toLowerCase().endsWith('.pdf')) continue;
const row = document.createElement('div');
row.className = 'file-row';


const left = document.createElement('div');
const a = document.createElement('span');
a.className = 'file-name';
a.textContent = f.name;
a.title = 'Klicken, um Link + Passwort zu kopieren';
a.addEventListener('click', async () => {
const text = `${location.origin}${location.pathname.replace(/\/[^/]*$/, '/')}${encodeURIComponent(f.name)}\nPasswort: ${f.password}`;
await navigator.clipboard.writeText(text);
toast(`In Zwischenablage kopiert: ${f.name}`);
});
left.appendChild(a);
const badge = document.createElement('span');
badge.className = 'badge';
badge.textContent = 'kopieren';
left.appendChild(badge);


const right = document.createElement('div');
const btn = document.createElement('button');
btn.className = 'icon-btn';
btn.title = 'Passwort neu generieren';
btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 0 1-9.9 1h-2.1A7.1 7.1 0 0 0 12 20a7 7 0 0 0 7-7c0-3.87-3.13-7-7-7z"/></svg>';
btn.addEventListener('click', async () => {
  if (!confirm(`Neues Passwort für \n${f.name}\n\nGenerieren?`)) return;

  try {
    const res = await fetch('regen.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: f.name })
    });

    if (!res.ok) {
      const txt = await res.text();
      console.error('Fehler vom Server:', txt);
      alert('Serverfehler: ' + res.status);
      return;
    }

    const out = await res.json();

    if (out.ok && out.password) {
      // Update direkt in der UI
      toast(`Neues Passwort generiert: ${out.password}`);
      // f.password updaten, damit fetchList() nicht nötig ist
      f.password = out.password;

      // Falls das Passwort im UI angezeigt wird, sofort aktualisieren
      const pwBadge = row.querySelector('.badge');
      if (pwBadge) pwBadge.textContent = 'kopieren'; // optional reset
      // Du könntest hier auch ein eigenes <span> für Passwort einfügen

    } else {
      console.error('Antwort ohne Passwort', out);
      alert(out.error || 'Fehler beim Aktualisieren');
    }

  } catch (err) {
    console.error('JS/Netzwerkfehler:', err);
    alert('Fehler beim Senden der Anfrage.');
  }
});

right.appendChild(btn);


row.appendChild(left);
row.appendChild(right);
filesContainer.appendChild(row);
}
}


function toast(msg) { status.textContent = msg; setTimeout(() => { status.textContent = ''; }, 3000); }


// Drag & Drop
;['dragenter','dragover'].forEach(evt => drop.addEventListener(evt, e => { e.preventDefault(); drop.style.borderColor = '#0d6efd'; }));
;['dragleave','drop'].forEach(evt => drop.addEventListener(evt, e => { e.preventDefault(); drop.style.borderColor = '#bbb'; }));
drop.addEventListener('drop', async e => {
const file = e.dataTransfer.files[0];
if (!file) return;
await uploadFile(file);
});


pick.addEventListener('click', () => input.click());
input.addEventListener('change', async () => { const file = input.files[0]; if (file) await uploadFile(file); input.value = ''; });


async function uploadFile(file) {
if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) { alert('Bitte eine PDF-Datei wählen.'); return; }
const fd = new FormData();
fd.append('pdf', file);
const res = await fetch('upload.php', { method: 'POST', body: fd });
const out = await res.json();
if (out.ok) { toast('Upload erfolgreich.'); fetchList(); }
else { alert(out.error || 'Upload fehlgeschlagen'); }
}


fetchList();
})();

