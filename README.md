# kirby-formwizard

Form builder for Kirby projects with the Pagewizard and the Project Wizard: build forms from field types, show them on pages with a block, read the submissions in the Panel.

**Status:** in development – see [PLAN.md](PLAN.md) and the mockup [mockups/formwizard.html](mockups/formwizard.html).

## What works so far

- **Panel menu „Form Wizard“** with „Formulare“ and „Eingänge“ below it (shown while you're in the Form Wizard).
- **Formulare:** a hidden page `forms` holds them; it is created on the first visit of the Panel. Each form is a page of its own with the tabs *Felder*, *Nach dem Absenden* and *Einstellungen*. Draft: not offered in the block yet; Aktiv: offered.
- **13 field types** as blocks: Text, E-Mail, Telefon, Zahl, Datum, mehrzeiliger Text, Auswahlliste, eine Option, mehrere Optionen, Datei-Upload, verstecktes Feld, Zwischentitel/Hinweis, Zustimmung – each with label, key, required, width and „Nur zeigen, wenn“.
- The URLs of the forms answer with the error page (404).
- **Block „Formular“** (`pwform`, activate it in the Project Wizard): tagline, heading, text, layout and style as the other blocks, plus the form (active forms only) and an own thank-you text. In the Panel it shows the form's fields.
- **On the website:** all field types, „Nur zeigen, wenn“ live (without JavaScript the fields stay visible), checked on the server after sending (required, e-mail, phone, number range, length, date, options, files), errors at the fields, the values kept; then the thank-you text (`{{ key }}` replaced) or the page of thanks. Invisible spam protection (a trap field, at least 3 seconds).
- **Not yet:** a valid submission is neither stored nor mailed – that comes with the next steps.

## Styling

CSS variables `--pwform-*` per colour variant (field background, border, text, focus; gaps, padding, corners) – set in the Project Wizard once the block is there, overridable in `site/patches/css/kirby-formwizard/`.

## Options

```php
// site/config/config.php
'kirbydesk.kirby-formwizard' => [
	'slug' => 'forms', // the page holding the forms
],
```

The page `forms` also shows up in the Panel's page list of the site. To hide it there, leave the template `formwizard-forms` out of the site blueprint's pages section.
