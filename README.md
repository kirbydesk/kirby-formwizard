# kirby-formwizard

Form builder for Kirby projects with the Pagewizard and the Project Wizard: build forms from field types, show them on pages with a block, read the submissions in the Panel.

**Status:** in development – see [PLAN.md](PLAN.md) and the mockup [mockups/formwizard.html](mockups/formwizard.html).

## What works so far

- **Project Wizard › Formulare** (a tab of its own between „Blöcke“ and „Einstellungen“): the dropdown lists the forms, „Neues Formular“, „Alle Formulare“, „Eingänge“ and „Einstellungen“. A form opens like a block there: its tabs *Felder*, *Nach dem Absenden*, *Einstellungen* (with the form's name), its status (Entwurf/Aktiv), its submissions, „Löschen“ – and a preview in the right column.
- **Kirby menu:** only „Eingänge“ (with the count of new ones) – for everyone who reads submissions, also without access to the Project Wizard.
- **Forms are pages** below a hidden page `forms` (created on the first visit of the Panel), but they are edited in the Project Wizard only; an old link to the page goes there.
- **13 field types** as blocks: Text, E-Mail, Telefon, Zahl, Datum, mehrzeiliger Text, Auswahlliste, eine Option, mehrere Optionen, Datei-Upload, verstecktes Feld, Zwischentitel/Hinweis, Zustimmung – each with label, key, required, width and „Nur zeigen, wenn“.
- **Block „Formular“** (`pwform`, activate it in the Project Wizard): tagline, heading, text, layout and style as the other blocks, plus the form (active forms only) and an own thank-you text. In the Panel it shows the form's fields; „Formular bearbeiten →“ leads to the Project Wizard.
- **On the website:** all field types, „Nur zeigen, wenn“ live (without JavaScript the fields stay visible), checked on the server after sending, errors at the fields, the values kept; then the thank-you text or the page of thanks. Invisible spam protection (a trap field, at least 3 seconds). The URLs of the forms answer 404.
- **Eingänge:** stored as JSON in `content/.formwizard/submissions/<form>/` (unless the form says „Nur versenden“): list over all forms, filter by form and status, search, CSV export; one submission with „Erledigt“, „Antworten“, „Löschen“. Deleted after the period set in *Formulare › Einstellungen* (default 180 days).
- **After sending** (tab *Nach dem Absenden*), in this order:
  - **Mail an das Team:** to the form's recipients, else the default recipient of the Project Wizard; „Je nach Auswahl an andere Adressen“ (e.g. Anliegen = Angebot → vertrieb@…). Subject with placeholders `{name}`, `{form}`, `{page}`, `{site}`; reply goes to the sender; text and HTML, with a link to the submission in the Panel.
  - **Bestätigung an den Absender** (to the first e-mail field): own subject and text, optionally with a copy of the details.
  - **Webhook:** the details as JSON (POST) to an address (CRM, Zapier, n8n).
  - **Uploads:** per form „Beim Eingang ablegen“ (protected in the submission's folder, downloaded through the Panel only, deleted with it), „Nur an die Mail hängen“ or both; the type is checked by the file's content, the size by the field (at most what PHP allows).
  - **Spam protection:** always a trap field and a minimum time; under *Formulare › Einstellungen* a captcha (Friendly Captcha, Cloudflare Turnstile, hCaptcha – site key and secret, the secret stored encrypted in `site/config/formwizard.key`), switched off per form, and a limit of submissions per visitor (e.g. 5 in 10 minutes, counted by a hash of the IP address).
  - Each writes a line into the submission's **log** (Panel). The mails go through the Project Wizard's mail server (*Einstellungen › E-Mail-Versand*). If the team's mail fails and the form stores nothing, the visitor sees an error instead of the thanks.

**Needs** kirby-projectwizard with the plugin tabs and the mail server (branch `claude/loving-fermi-q6ap63`).

## Styling

In the Project Wizard under *Blöcke › Formular* (as for the contact form): the fields' padding, corners and border, their colours per variant (background, border, text, focus), the gaps – with a live preview. The preview of a form in *Formulare* and of the block on a page shows the real fields in that look.


CSS variables `--pwform-*` per colour variant (field background, border, text, focus; gaps, padding, corners) – set in the Project Wizard once the block is there, overridable in `site/patches/css/kirby-formwizard/`.

## Options

```php
// site/config/config.php
'kirbydesk.kirby-formwizard' => [
	'slug'      => 'forms', // the page holding the forms
	'retention' => 180,     // days a submission is kept until set in the Project Wizard (0: for ever)
],
```

