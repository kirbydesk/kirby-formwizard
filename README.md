# kirby-formwizard

Form builder for Kirby projects built with [kirby-pagewizard](https://github.com/kirbydesk/kirby-pagewizard) and [kirby-projectwizard](https://github.com/kirbydesk/kirby-projectwizard): build forms from field types in the Project Wizard, show them on pages with the block „Formular“, read the submissions in the Panel.

Plan and decisions: [PLAN.md](PLAN.md). Mockups: [mockups/](mockups/).

## Requirements

- Kirby 5.1+, PHP 8.2+ with `sodium` and `fileinfo`
- kirby-pagewizard and kirby-projectwizard (with the plugin tabs and the mail server – the versions of the same branch)

## Setting up

1. Install the plugin (`site/plugins/kirby-formwizard`). On the first visit of the Panel it creates the hidden page `forms` that holds the forms.
2. **Project Wizard › Blöcke:** activate the block „Formular“. Under *Blöcke › Formular* set the look of the fields (padding, corners, border, colours per variant, gaps).
3. **Project Wizard › Einstellungen › E-Mail-Versand:** the mail server (SMTP) the website sends through, sender and default recipient – send a test mail.
4. **Project Wizard › Formulare › Einstellungen:** retention of submissions, a captcha (optional), the limit of submissions.

## Building a form

**Project Wizard › Formulare** (a tab between „Blöcke“ and „Einstellungen“). The dropdown lists the forms, „Neues Formular“ (from a template: Kontakt, Rückruf, Bewerbung, Anmeldung – or empty), „Alle Formulare“, „Eingänge“ and „Einstellungen“.

A form has three tabs, a status (Entwurf: not offered in the block yet; Aktiv) and a preview in the project's look:

- **Felder** – the field types as blocks, sorted by dragging: Text, E-Mail, Telefon, Zahl, Datum, mehrzeiliger Text, Auswahlliste, eine Option, mehrere Optionen, Datei-Upload, verstecktes Feld (page address, URL parameter or a fixed value), Zwischentitel/Hinweis, Zustimmung. Each with label, key, required, width (half/full), help, placeholder and its own settings (length, range, options, file types and size …). **„Nur zeigen, wenn“:** a field shows only while a choice further up has a value. The key is made from the label when the field is first saved and kept after – placeholders, the export and the webhook rely on it.
- **Nach dem Absenden** – a thank-you text or a page; the **mail to the team** (the form's recipients – none: no mail to the team; subject with `{name}`, `{form}`, `{page}`, `{site}`); the **confirmation** to the sender (first e-mail field; own text, a copy of the details); **uploads** (kept with a stored submission; per upload field also attached to the mail); a **webhook** (JSON POST, e.g. CRM, Zapier, n8n).
- **Einstellungen** – the form's name, the button's text, the privacy page (link in „Zustimmung“), store submissions or only send, captcha on/off, close the form (a note instead).

On multilingual sites the editor has a language switch; keys, option values, conditions and addresses stay the same in every language.

## On the website

The block „Formular“: tagline, heading, text, layout and style as every block, the form (active forms only) and an own thank-you text. On the website:

- all fields, „Nur zeigen, wenn“ live (without JavaScript the fields stay visible; hidden fields aren't sent)
- checked on the server (required, e-mail, phone, number range, length, date, options, files by their content and size); errors at the fields, the values kept, sending once
- spam protection: always a trap field and at least 3 seconds; optionally a captcha (Friendly Captcha, Cloudflare Turnstile, hCaptcha) and a limit per visitor (counted by a hash of the IP address)
- the URLs of the forms themselves answer 404

## Submissions

Panel menu **„Eingänge“** (with the count of new ones) – also for users without access to the Project Wizard (permissions per role: the area `formwizard-submissions`):

- one list for all forms: filter by form and status, search, CSV export (opens in Excel)
- a submission: its fields, the files (download through the Panel only), the log (mails, webhook), „Erledigt“, „Antworten“, „Löschen“

Stored as JSON in `content/.formwizard/submissions/<form>/<id>.json`, uploads beside it in `<id>/`. Not reachable from the web, deleted after the retention period (default 180 days) with their files.

## Styling

CSS variables `--pwform-*` per colour variant, set in the Project Wizard (*Blöcke › Formular*). Overrides in `site/patches/css/kirby-formwizard/` as for every block. The markup uses data attributes: `form[data-formwizard]`, `[data-formwizard-field][data-type][data-width]`, `[data-formwizard-error]`, `[data-formwizard-message="sent|failed|invalid"]`.

## Options

```php
// site/config/config.php
'kirbydesk.kirby-formwizard' => [
	'slug'      => 'forms', // the page holding the forms
	'retention' => 180,     // days until set in the Project Wizard (0: for ever)
],
```

## Hooks

```php
'hooks' => [
	'formwizard.submit:before' => function ($form, $page, $values) {},
	'formwizard.submit:after'  => function ($form, $page, $submission) {},
	'formwizard.failed'        => function ($form, $error) {},
],
```

## Keys and secrets

- `site/config/mail.key` (Project Wizard) – encrypts the mail server's password
- `site/config/formwizard.key` – encrypts the captcha's secret key

Both are made on the first save and belong with the code; without them the stored secrets can't be read (the Panel then asks for them again).

## Good to know

- Saving in the Project Wizard writes the form directly (no Kirby drafts or locks): two people editing the same form at once overwrite each other.
- Unsaved changes: asked before leaving within „Formulare“ and when closing the browser – not when switching to another tab of the Project Wizard.
- Translations of a form are separate copies of its fields (as for all blocks in Kirby): a field added later in one language has to be added in the others too – or the form translated again (Translate Wizard).
- Uploads are limited by PHP too (`upload_max_filesize`, `post_max_size`); the website shows the limit that really applies.
- A captcha loads a script of its provider – name it in the privacy policy.
