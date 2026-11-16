# CLAUDE.md

Guidance for Claude Code when working on this plugin.

## What This Is

**kirby-formwizard** is a Kirby CMS v5.1+ plugin: a form builder for projects built with kirby-pagewizard and kirby-projectwizard. Editors build forms from field types, a block shows a form on a page, the submissions land in the Panel. Plan and decisions: `PLAN.md`, mockup: `mockups/formwizard.html`.

## Architecture

- **Forms are pages.** A hidden container page (`content/forms/`, template `formwizard-forms`, created on the first visit of the Panel by `fwForms::ensureContainer()`), one child page per form (template `formwizard-form`, model `FormwizardFormPage`). Draft = not offered in the block yet, listed = active.
- **Fields are blocks.** The blocks field `fields` of a form; one block type per field type (`fwtext`, `fwemail`, … – list and icons in `fwForms::TYPES`, blueprints built in `fwFields`). Choice fields keep their options in a structure (`text` translatable, `value` not). The condition `when` is `"<block id>:<option value>"`, its options come from `page.fwConditions`.
- **The forms' URLs answer 404** (`src/extensions/routes.php`).
- **Panel menu:** one entry "Form Wizard" (area `formwizard`) with "Forms" and "Submissions" (areas `formwizard-forms`, `formwizard-submissions`) below it – shown only inside the Form Wizard (`fwForms::inArea()`), indented by `src/index.css`. The forms are pages of the `site` area; the plugin extends `site` so "Pages" isn't marked there.
- **Submissions** (step 5) will be JSON files under `content/.formwizard/`, not pages.
- **The mail server** lives in the Project Wizard (`pwMail`, step 6), shared with kirbyblock-contactform.

## Directories

- `src/classes/` — PHP classes (`fwForms`, `fwFields`, `fwUploads`, `FormwizardFormPage`)
- `src/extensions/` — Kirby extension points (areas, blueprints, hooks, routes, translations)
- `src/components/`, `src/views/` — Vue Panel components (kirbyup)
- `src/i18n/` — de/en, keys `formwizard.*`

## Commands

```bash
npx kirbyup src/index.js           # build index.js/index.css (committed)
npx kirbyup src/index.js --watch   # watch mode
```

## Conventions

As in the other kirbydesk plugins: comments in plain English, short; Panel texts German and English; the built `index.js`/`index.css` are committed.
