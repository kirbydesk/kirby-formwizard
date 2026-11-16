# Plan — kirby-formwizard

Ein Formular-Baukasten für Kirby-Projekte mit Pagewizard und Project Wizard. Redakteure bauen Formulare aus Feldtypen, ein Block zeigt sie auf der Seite, die Eingänge landen im Panel. Das Mockup steht in `mockups/formwizard.html`.

Vorbild für die Ideen: [Kirby DreamForm](https://github.com/tobimori/kirby-dreamform). Formulare sind dort Seiten, nach dem Absenden laufen Aktionen, Guards schützen vor Spam. DreamForm hat eine kommerzielle Lizenz, deshalb übernehmen wir nur Ideen, keinen Code.

## Entschieden

| Thema | Entscheidung |
|---|---|
| Was es ist | Ein Baukasten für Formulare, ein Schritt pro Formular, keine mehrstufigen Formulare |
| Wo Formulare liegen | Zentral; der Block wählt ein Formular aus. Ein Formular kann auf mehreren Seiten stehen |
| Feldtypen | Text, E-Mail, Telefon, Zahl, Datum, mehrzeiliger Text, Auswahlliste, eine Option, mehrere Optionen, Datei-Upload, verstecktes Feld, Zwischentitel/Hinweis, Zustimmung |
| Bedingungen | „Nur zeigen, wenn“: eine Bedingung je Feld (ein Auswahlfeld weiter oben hat einen bestimmten Wert) |
| Eingänge | Werden gespeichert (pro Formular abschaltbar), mit Liste, Filter, Ansicht, neu/erledigt, CSV-Export und automatischer Löschfrist |
| Nach dem Absenden | Danke-Text oder Weiterleitung; dazu Aktionen der Reihe nach: Mail ans Team (je nach Auswahl an eine andere Adresse), Bestätigung an den Absender, Webhook |
| Uploads | Pro Formular wählbar: nur als Anhang, beim Eingang ablegen oder beides. Dateitypen und Größe pro Feld |
| Spamschutz | Immer an: verstecktes Feld, Mindestzeit, CSRF-Schutz. Wahlweise ein Captcha (Friendly Captcha, Cloudflare Turnstile, hCaptcha) und eine Begrenzung der Sendungen |
| Mehrsprachigkeit | Texte der Formulare pro Sprache |
| Kontaktformular | `kirbyblock-contactform` bleibt unverändert |
| Panel | Ein Menüpunkt „Form Wizard“ mit den Unterpunkten „Formulare“ und „Eingänge“ |
| Project Wizard | Gestaltung unter Blöcke › Formular. Spamschutz, Versand und Aufbewahrung als eigene Seite im Zahnrad-Menü, wie „Übersetzung“ beim Translate Wizard |
| Ein Repo | `kirby-formwizard` bringt alles mit, auch den Block. Dafür muss `ProjectConfig::detectBlocks()` auch Blöcke außerhalb von `kirbyblock-*` finden |

## Vorschlag zur Technik (noch zu bestätigen)

**Formulare sind Kirby-Seiten.** Sie liegen in einem versteckten Container (`content/forms/`, Vorlage `formwizard-forms`), jedes Formular ist eine Unterseite (`formwizard-form`). Die Felder sind ein Blocks-Feld, und jeder Feldtyp ist ein Block (`fwtext`, `fwemail`, …). Das bringt aus Kirby mit, ohne dass wir es selbst bauen:
- Sortieren per Ziehen, Feldtyp-Auswahl, Bearbeiten im Drawer, Kopieren und Einfügen
- Übersetzung pro Sprache; der Translate Wizard übersetzt Blocks schon heute
- Entwürfe, Änderungen veröffentlichen, Sperren bei gleichzeitiger Bearbeitung

**Eingänge sind keine Seiten,** sondern JSON-Dateien unter `content/.formwizard/submissions/<formular-uuid>/<id>.json`, Uploads daneben. Gründe:
- Sie sind nicht öffentlich erreichbar.
- Sie hängen nicht an der Sprache.
- Sie bleiben schnell, auch bei vielen Eingängen.
- Sie lassen sich leicht nach der Löschfrist entfernen.

Die Liste unter „Eingänge“ ist eine eigene Panel-Ansicht (Vue).

**Der Mailserver** ist heute in `kirbyblock-contactform` (`content/.projectwizard/contactform.json`, Schlüssel in `site/config/contactform.key`). Für den Form Wizard gibt es zwei Möglichkeiten:
- **a)** Den Mailserver in den Pagewizard ziehen (`pwMail`), den beide nutzen. Das Kontaktformular liest dann von dort; die bestehenden Einstellungen werden einmalig übernommen. Das ist eine kleine Änderung am Kontaktformular.
- **b)** Der Form Wizard liest die Einstellungen des Kontaktformulars mit, falls installiert, sonst hat er einen eigenen Mailserver.

Empfehlung: **a**.

## Schritte

Jeder Schritt endet mit etwas, das sich im Panel und auf der Website ausprobieren lässt.

1. **Grundgerüst:** `composer.json`, `index.php`, kirbyup, Übersetzungen de/en, `CLAUDE.md`, README.
2. **Formulare bauen:**
   - Container und Formular-Seiten mit Blueprints, die 13 Feldtypen als Blocks
   - Menüpunkt „Form Wizard › Formulare“
   - Die Übersicht mit Feldern, Eingängen und Verwendung
3. **Formular auf der Website:**
   - Block `pwform` mit Tagline, Überschrift, Text, Layout und Stil wie die anderen Blöcke, dazu die Auswahl des Formulars
   - Das Snippet zeigt alle Feldtypen; Bedingungen funktionieren auch ohne JavaScript serverseitig, mit JavaScript live
   - `detectBlocks()` im Project Wizard erweitern
4. **Absenden:**
   - Route, Prüfung auf dem Server, Weiterleitung nach dem Absenden (wie beim Kontaktformular, ohne JavaScript nutzbar)
   - Fehler am Feld, Danke-Text oder Weiterleitung
   - Unsichtbarer Spamschutz
5. **Eingänge:**
   - Speichern
   - „Form Wizard › Eingänge“ mit Liste, Filter, Suche, Ansicht, neu/erledigt, Löschen und CSV
   - Zähler im Menü, Rechte pro Benutzerrolle (bauen bzw. Eingänge lesen)
6. **Aktionen:** Mailserver (siehe oben), Mail ans Team mit Weiterleitung je nach Auswahl, Bestätigung, Webhook, Protokoll beim Eingang.
7. **Uploads:**
   - Feld mit Typen und Größe, Ablage beim Eingang (geschützt, Download im Panel) bzw. Anhang
   - Mit dem Eingang gelöscht
8. **Captcha und Begrenzung:**
   - Anbieter wählbar, Schlüssel über `secrets` in die `.env`; dafür `pwSecrets::PLUGINS` ergänzen oder auf Selbst-Anmeldung umstellen
   - Begrenzung über ein Kürzel der IP-Adresse
9. **Project Wizard:**
   - Seite „Formulare“ im Zahnrad-Menü (Spamschutz, Versand, Aufbewahrung)
   - Gestaltung und Vorschau unter Blöcke › Formular
   - Löschfrist automatisch beim Aufruf des Panels
10. **Feinschliff:**
    - Vorlagen für neue Formulare (Kontakt, Rückruf, Bewerbung, Anmeldung)
    - Formular schließen
    - Test mit Translate Wizard
    - README

## Offen

- Technik oben bestätigen: Formulare als Seiten mit Feldern als Blocks, Eingänge als JSON.
- Mailserver: a oder b.
- Block-Erkennung im Project Wizard: Wie soll der Form Wizard sich melden? Vorschlag: Jedes Plugin, das `pwConfig::register()` aufruft, zählt als Block-Plugin, unabhängig vom Ordnernamen.
- Ein Testprojekt: In dieser Umgebung gibt es keine Kirby-Installation. Ich kann eine frische per Composer aufsetzen und die Plugins verlinken.
