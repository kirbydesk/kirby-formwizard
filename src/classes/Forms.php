<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * The forms of the website: a hidden container page (template
 * formwizard-forms) with one child page per form (formwizard-form). The
 * fields of a form are blocks (fwtext, fwemail, …) in its blocks field
 * "fields" – sortable, translatable and edited as Kirby does it.
 */
class fwForms
{
	/**
	 * The field types: [icon, wide by default, has options]. The order is
	 * the order in the picker, the groups are those of the picker.
	 */
	/** A field's widths (as Kirby's, the half and the whole by name) */
	public const WIDTHS = ['1/4', '1/3', 'half', '2/3', '3/4', 'full'];

	public const TYPES = [
		'fwtext'       => ['text', false, false],
		'fwemail'      => ['email', false, false],
		'fwtel'        => ['phone', false, false],
		'fwnumber'     => ['order-num-asc', false, false],
		'fwdate'       => ['calendar', false, false],
		'fwtextarea'   => ['text-left', true, false],
		'fwselect'     => ['angle-dropdown', false, true],
		'fwradio'      => ['circle-nested', true, true],
		'fwcheckboxes' => ['checklist', true, true],
		'fwfile'       => ['upload', true, false],
		'fwhidden'     => ['hidden', true, false],
		'fwinfo'       => ['title', true, false],
		'fwconsent'    => ['check', true, false],
	];

	public const GROUPS = [
		'input'  => ['fwtext', 'fwemail', 'fwtel', 'fwnumber', 'fwdate', 'fwtextarea'],
		'choice' => ['fwselect', 'fwradio', 'fwcheckboxes'],
		'more'   => ['fwfile', 'fwhidden', 'fwinfo', 'fwconsent'],
	];

	/** Field types that take an answer (not the heading/hint) */
	public static function isInput(string $type): bool
	{
		return isset(self::TYPES[$type]) && $type !== 'fwinfo';
	}

	public static function hasOptions(string $type): bool
	{
		return self::TYPES[$type][2] ?? false;
	}

	/* -------------- The container --------------*/

	/** The slug of the container page (option: kirbydesk.kirby-formwizard.slug) */
	public static function slug(): string
	{
		return (string) option('kirbydesk.kirby-formwizard.slug', 'forms');
	}

	/**
	 * The forms' submission folders, read from the content folder – for the
	 * Panel's menu, whose entries Kirby takes while loading the plugins (no
	 * pages yet): the UUID from the text file, else the folder's slug
	 */
	public static function scannedFolders(): array
	{
		$folders = [];
		foreach (glob(kirby()->root('content') . '/' . self::slug() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
			$files = glob($dir . '/formwizard-form*.txt') ?: [];
			if ($files === []) continue;
			$uuid = '';
			foreach ($files as $file) {
				if (preg_match('/^Uuid:\s*(\S+)/m', (string) file_get_contents($file), $m)) $uuid = $m[1];
			}
			$slug = preg_replace('/^\d+_/', '', basename($dir));
			$folders[] = preg_replace('/[^a-z0-9_-]/i', '', $uuid !== '' ? $uuid : $slug);
		}
		return array_values(array_unique(array_filter($folders)));
	}

	/** The form whose submissions are in this folder */
	public static function byFolder(string $folder): ?Page
	{
		foreach (self::forms() as $form) {
			if (fwSubmissions::folder($form) === $folder) return $form;
		}
		return null;
	}

	public static function container(): ?Page
	{
		$page = page(self::slug());
		return $page?->intendedTemplate()->name() === 'formwizard-forms' ? $page : null;
	}

	/**
	 * Creates the container on the first visit of the Panel (unlisted, so
	 * it is in no navigation; its template answers with the error page).
	 */
	public static function ensureContainer(): ?Page
	{
		if ($page = self::container()) return $page;
		if (page(self::slug())) return null; // the slug is taken by another page

		$kirby = kirby();
		// (in the language of the user who opens the Panel first)
		$title = \Kirby\Toolkit\I18n::translate('formwizard.forms', 'Forms', $kirby->user()?->language() ?? 'en');
		try {
			return $kirby->impersonate('kirby', function () use ($kirby, $title) {
				$page = $kirby->site()->createChild([
					'slug'     => self::slug(),
					'template' => 'formwizard-forms',
					'content'  => ['title' => $title],
				]);
				return $page->changeStatus('unlisted');
			});
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Forms have no drafts any more (up to formwizard 0.1): one still a
	 * draft is made active once – it would no longer be offered in the blocks
	 */
	public static function activateDrafts(): void
	{
		$drafts = self::container()?->drafts()->filterBy('intendedTemplate', 'formwizard-form');
		foreach ($drafts ?? [] as $form) {
			try {
				kirby()->impersonate('kirby', fn () => $form->changeStatus('listed'));
			} catch (\Throwable $e) {
				// (stays a draft; tried again next time)
			}
		}
	}

	/**
	 * Up to formwizard 0.1 an empty recipient meant the project's default
	 * one (Project Wizard › Settings › Sending); now it means no mail to the
	 * team. Forms that relied on it get that address written in – once, so
	 * nothing changes for them
	 */
	public static function adoptDefaultRecipient(): void
	{
		if (fwSettings::done('recipients')) return;
		$kirby   = kirby();
		$default = class_exists('pwMail') ? implode(', ', pwMail::recipient()) : '';
		if ($default !== '') {
			foreach (self::forms() as $form) {
				$c = $form->content($kirby->defaultLanguage()?->code());
				if (!$form->fwMails() || trim((string) $c->get('recipients')->value()) !== '') continue;
				try {
					$kirby->impersonate('kirby', fn () => $form->update(['recipients' => $default], $kirby->defaultLanguage()?->code()));
				} catch (\Throwable $e) {
					return; // (tried again next time)
				}
			}
		}
		fwSettings::markDone('recipients');
	}

	/** All forms (drafts included) */
	public static function forms(): Pages
	{
		return self::container()?->childrenAndDrafts()->filterBy('intendedTemplate', 'formwizard-form') ?? new Pages([]);
	}

	/* -------------- The Panel --------------*/

	/** The Project Wizard's page of a form (its tab "Forms") */
	public static function editUrl(Page $form): string
	{
		return 'projectwizard?tab=forms&view=form:' . rawurlencode($form->slug());
	}

	/**
	 * A form opened as a page in the Panel (an old link, the search …):
	 * the slug of the form, '' for the container – null for any other page
	 */
	public static function formOfPanelPath(string $path): ?string
	{
		if (!preg_match('#^pages/' . preg_quote(self::slug(), '#') . '(?:\+([^/+]+))?(?:/|$)#', $path, $m)) return null;
		return $m[1] ?? '';
	}
}
