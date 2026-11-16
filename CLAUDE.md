# CLAUDE.md

Guidance for Claude Code when working on this plugin.

## What This Is

**kirby-formwizard** is a Kirby CMS v5.1+ plugin: a form builder for projects built with kirby-pagewizard and kirby-projectwizard. Editors build forms from field types, a block shows a form on a page, the submissions land in the Panel. Plan and decisions: `PLAN.md`, mockup: `mockups/formwizard.html`.

## Architecture

- **Forms are pages.** A hidden container page (`content/forms/`, template `formwizard-forms`, created on the first visit of the Panel by `fwForms::ensureContainer()`), one child page per form (template `formwizard-form`, model `FormwizardFormPage`). Draft = not offered in the block yet, listed = active.
- **Fields are blocks.** The blocks field `fields` of a form; one block type per field type (`fwtext`, `fwemail`, … – list and icons in `fwForms::TYPES`, blueprints built in `fwFields`). Choice fields keep their options in a structure (`text` translatable, `value` not). The condition `when` is `"<block id>:<option value>"`, its options come from `page.fwConditions`.
- **The forms' URLs answer 404** (`src/extensions/routes.php`).
- **The block `pwform`** (registered with `pwConfig::register`, config in `src/config/`): blueprint lazy via `pwBlueprint::main` (kirby-pagewizard loads after this plugin), snippet `snippets/index.php`, CSS `src/css/styles.css` (vars `--pwform-*`), Panel component `src/blocks/Form.vue` (fields via the API `formwizard/form`).
- **Website:** `fwRender` builds the form (conditions as `data-when`, script `assets/formwizard.js` inline once per page); it posts to `formwizard/submit`, `fwSubmit` checks it (only fields whose condition is met), the result goes into the session (`formwizard.<block id>`) and the page shows it after the redirect. `fwSpam`: trap field + signed time stamp.
- **Project Wizard tab "Forms":** the plugin option `projectwizard` registers the tab (kirby-projectwizard reads it in `ProjectConfig::pluginTabs()`): component `k-formwizard-pw` (`src/pw/Forms.vue`, views `list`, `new`, `settings`, `form:<slug>`), dropdown entries from the API `formwizard/pw/menu`. A form is edited with Kirby's `k-form` from `Form::for($page)->toProps()` with the page's field endpoints (`fwEditor`), saved with `$page->update()`; the preview (`src/pw/FormPreview.vue`) goes into the wizard's right column via `pw-portal`. Settings of all forms (retention) in `content/.projectwizard/formwizard.json`. Old Panel links to the form pages redirect there (`panel.route:before`). Address: `projectwizard?tab=forms&view=form:<slug>` (`fwForms::editUrl()`).
- **Panel menu:** only "Submissions" (area `formwizard-submissions`, with the count of new ones).
- **Submissions** are JSON files (`fwSubmissions`): `content/.formwizard/submissions/<form uuid>/<Ymd-His-rand>.json`, the fields as they were sent (label, type, value, display text). Counts cached in the plugin cache. Panel views in the area `formwizard-submissions` (list with filters in the query, detail), CSV as a Panel request, status/delete via the plugin API. Area closures (`menu`, `current`) must accept being called without arguments – Kirby resolves them when sending a view.
- **After sending:** `fwActions::run()` – team mail (routes `when` = `"<block id>:<value>"` as the fields' conditions, else `recipients`, else `pwMail::recipient()`), confirmation (first e-mail field), webhook (JSON POST); each a line in the submission's `log`. Placeholders in texts: `{key}` or `{{ key }}` – but in blueprint help/placeholder texts Kirby resolves braces itself, so those use single braces or real queries (`{{ page.title }}`).
- **Uploads** (`fwUploads`): checked in `fwSubmit::checkFiles()` (MIME by content), put in place by `keep()` – stored in `submissions/<form>/<id>/` or a temp folder for the mail only (`mode()`: store/attach/both; nothing stored → attach) – and `finish()` after the actions. Download only through the Panel request `formwizard/submissions/<id>/files/<name>`.
- **The mail server** lives in the Project Wizard (`pwMail`: `ready()`, `send([...])`, `recipient()`); kirbyblock-contactform keeps its own and is not touched.

## Directories

- `src/classes/` — PHP classes (`fwForms`, `fwFields`, `fwUploads`, `fwRender`, `fwSubmit`, `fwSpam`, `fwSubmissions`, `fwSubmissionsView`, `fwEditor`, `fwActions`, `FormwizardFormPage`)
- `snippets/`, `src/css/`, `assets/` — the block on the website
- `src/extensions/` — Kirby extension points (areas, blueprints, hooks, routes, translations)
- `src/components/`, `src/views/`, `src/pw/` — Vue Panel components (kirbyup); `src/pw/` the Project Wizard tab
- `src/i18n/` — de/en, keys `formwizard.*`

## Commands

```bash
npx kirbyup src/index.js           # build index.js/index.css (committed)
npx kirbyup src/index.js --watch   # watch mode
```

## Conventions

As in the other kirbydesk plugins: comments in plain English, short; Panel texts German and English; the built `index.js`/`index.css` are committed.
