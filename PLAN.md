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
| Project Wizard | Gestaltung unter Blöcke › Formular. Spamschutz und Aufbewahrung als eigene Seite im Zahnrad-Menü, wie „Übersetzung“ beim Translate Wizard |
| Mailserver (SMTP) | Wird im Project Wizard angelegt, als allgemeine Projekt-Einstellung für alle Plugins, die Mails verschicken (Form Wizard, Kontaktformular) |
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

**Der Mailserver** zieht aus `kirbyblock-contactform` in den Project Wizard:
- **Klasse:** `pwMail` in `kirby-projectwizard` (`settings()`, `save()`, `ready()`, `send()`, Test-Mail). Das Passwort bleibt verschlüsselt wie bisher.
- **Panel:** eine eigene Seite „E-Mail-Versand“ im Zahnrad-Menü mit Server, Port, Verschlüsselung, Benutzer, Passwort, Absender, Standard-Empfänger und Test-Mail. Das ist die heutige `ContactformMail.vue`, nur allgemein.
- **Speicherort:** `content/.projectwizard/mail.json`, Schlüssel in `site/config/mail.key`.
- **Übernahme:** Gibt es noch keine `mail.json`, werden die Einstellungen aus `contactform.json` übernommen, samt Schlüssel aus `contactform.key`. Bestehende Projekte senden ohne Zutun weiter.
- **Kontaktformular:** Es nutzt `pwMail`. Der Tab „Versand“ unter Blöcke › Kontaktformular verweist auf die neue Seite. Empfänger, Betreff und Bestätigung pro Block bleiben, wie sie sind.

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
6. **Mailserver im Project Wizard:** `pwMail`, Seite „E-Mail-Versand“, Übernahme aus dem Kontaktformular, Kontaktformular darauf umstellen.
7. **Aktionen:** Mail ans Team mit Weiterleitung je nach Auswahl, Bestätigung, Webhook, Protokoll beim Eingang.
8. **Uploads:**
   - Feld mit Typen und Größe, Ablage beim Eingang (geschützt, Download im Panel) bzw. Anhang
   - Mit dem Eingang gelöscht
9. **Captcha und Begrenzung:**
   - Anbieter wählbar, Schlüssel über `secrets` in die `.env`; dafür `pwSecrets::PLUGINS` ergänzen oder auf Selbst-Anmeldung umstellen
   - Begrenzung über ein Kürzel der IP-Adresse
10. **Project Wizard:**
   - Seite „Formulare“ im Zahnrad-Menü (Spamschutz, Aufbewahrung)
   - Gestaltung und Vorschau unter Blöcke › Formular
   - Löschfrist automatisch beim Aufruf des Panels
11. **Feinschliff:**
    - Vorlagen für neue Formulare (Kontakt, Rückruf, Bewerbung, Anmeldung)
    - Formular schließen
    - Test mit Translate Wizard
    - README

## Testen

- Getestet wird an einem echten Projekt.
