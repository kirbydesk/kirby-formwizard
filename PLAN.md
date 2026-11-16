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
| Panel | **Formulare als Reiter im Project Wizard** (zwischen „Blöcke“ und „Einstellungen“), Unternavigation im Dropdown: die Formulare, Neues Formular, Alle Formulare, Eingänge, Einstellungen (Spamschutz, Aufbewahrung). Links im Kirby-Menü bleibt nur **„Eingänge“** – für Benutzer, die Eingänge lesen, aber den Project Wizard nicht sehen. Mockup: `mockups/formwizard-projectwizard.html` (ersetzt den Menüpunkt „Form Wizard“ aus `mockups/formwizard.html`) |
| Project Wizard | Gestaltung unter Blöcke › Formular. Spamschutz und Aufbewahrung als eigene Seite im Zahnrad-Menü, wie „Übersetzung“ beim Translate Wizard |
| Mailserver (SMTP) | Wird im Project Wizard angelegt, als allgemeine Projekt-Einstellung für Plugins, die Mails verschicken. Das Kontaktformular bleibt eigenständig mit seinem eigenen Versand |
| Ein Repo | `kirby-formwizard` bringt alles mit, auch den Block. `ProjectConfig::detectBlocks()` findet Blöcke schon heute über `pwConfig::register()`, egal wie der Ordner heißt |

## Technik

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

**Der Mailserver** ist eine eigene Einstellung des Project Wizard:
- **Klasse:** `pwMail` in `kirby-projectwizard` (`settings()`, `save()`, `ready()`, `send()`, `recipient()`, Test-Mail). Das Passwort wird verschlüsselt gespeichert.
- **Panel:** Seite „E-Mail-Versand“ im Zahnrad-Menü (nur für Admins) mit Server, Port, Verschlüsselung, Benutzer, Passwort, Absender, Absendername, Standard-Empfänger und Test-Mail.
- **Speicherort:** `content/.projectwizard/mail.json`, Schlüssel in `site/config/mail.key`.
- **Kontaktformular:** bleibt unverändert mit seinem eigenen Versand. Es wird nichts daraus übernommen.

## ✅ Umbau: Formulare in den Project Wizard

Schritt 2 hat die Formulare als Seiten unter einem eigenen Menüpunkt „Form Wizard“ gebaut. Das wird umgebaut:
- **Speicher bleibt:** Formulare bleiben Seiten (Übersetzung, Entwürfe, Sperren), Felder bleiben Blocks. Sie erscheinen aber nicht mehr als Seiten im Panel.
- **Project Wizard:** Reiter „Formulare“ mit Dropdown. Ein Formular sieht aus wie ein Block im Project Wizard: Reiter Felder · Nach dem Absenden · Einstellungen, rechts die Vorschau. Der Formular-Editor wird dafür in den Project Wizard eingebaut. Der Form Wizard meldet den Reiter beim Project Wizard an, damit nichts Formular-Eigenes fest in `Overview.vue` steht.
- **Kirby-Menü:** nur noch „Eingänge“ mit Zähler. Die Menüpunkte „Form Wizard“ und „Formulare“ fallen weg.
- **Formulare › Einstellungen:** Spamschutz und Aufbewahrung.

## Schritte

Jeder Schritt endet mit etwas, das sich im Panel und auf der Website ausprobieren lässt.

1. ✅ **Grundgerüst:** `composer.json`, `index.php`, kirbyup, Übersetzungen de/en, `CLAUDE.md`, README.
2. ✅ **Formulare bauen** (Eingänge und Verwendung in der Übersicht folgen mit Schritt 3 und 5):
   - Container und Formular-Seiten mit Blueprints, die 13 Feldtypen als Blocks
   - Menüpunkt „Form Wizard › Formulare“
   - Die Übersicht mit Feldern, Eingängen und Verwendung
3. ✅ **Formular auf der Website:**
   - Block `pwform` mit Tagline, Überschrift, Text, Layout und Stil wie die anderen Blöcke, dazu die Auswahl des Formulars
   - Das Snippet zeigt alle Feldtypen; Bedingungen funktionieren auch ohne JavaScript serverseitig, mit JavaScript live
4. ✅ **Absenden:**
   - Route, Prüfung auf dem Server, Weiterleitung nach dem Absenden (wie beim Kontaktformular, ohne JavaScript nutzbar)
   - Fehler am Feld, Danke-Text oder Weiterleitung
   - Unsichtbarer Spamschutz
5. ✅ **Eingänge:**
   - Speichern
   - „Form Wizard › Eingänge“ mit Liste, Filter, Suche, Ansicht, neu/erledigt, Löschen und CSV
   - Zähler im Menü, Rechte pro Benutzerrolle (bauen bzw. Eingänge lesen)
6. ✅ **Mailserver im Project Wizard:** `pwMail`, Seite „E-Mail-Versand“ (Branch `claude/loving-fermi-q6ap63` in kirby-projectwizard).
7. ✅ **Aktionen:** Mail ans Team mit Weiterleitung je nach Auswahl, Bestätigung, Webhook, Protokoll beim Eingang.
8. ✅ **Uploads:**
   - Feld mit Typen und Größe, Ablage beim Eingang (geschützt, Download im Panel) bzw. Anhang
   - Mit dem Eingang gelöscht
9. ✅ **Captcha und Begrenzung** (Schlüssel verschlüsselt in den Formular-Einstellungen statt in der `.env`):
   - Anbieter wählbar, Schlüssel über `secrets` in die `.env`; dafür `pwSecrets::PLUGINS` ergänzen oder auf Selbst-Anmeldung umstellen
   - Begrenzung über ein Kürzel der IP-Adresse
10. ✅ **Project Wizard:**
   - Seite „Formulare“ im Zahnrad-Menü (Spamschutz, Aufbewahrung)
   - Gestaltung und Vorschau unter Blöcke › Formular
   - Löschfrist automatisch beim Aufruf des Panels
11. ✅ **Feinschliff** (dazu: Sprachwahl im Formular-Editor):
    - Vorlagen für neue Formulare (Kontakt, Rückruf, Bewerbung, Anmeldung)
    - Formular schließen
    - Test mit Translate Wizard
    - README

## Testen

- Getestet wird an einem echten Projekt.
