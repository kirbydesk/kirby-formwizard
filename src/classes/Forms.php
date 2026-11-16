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

	/** All forms (drafts included) */
	public static function forms(): Pages
	{
		return self::container()?->childrenAndDrafts()->filterBy('intendedTemplate', 'formwizard-form') ?? new Pages([]);
	}

	/* -------------- The Panel menu --------------*/

	/** The path in the Panel without its slug, e.g. "pages/forms+contact" */
	public static function panelPath(): string
	{
		$slug = trim((string) option('panel.slug', 'panel'), '/');
		$path = trim(kirby()->request()->path()->toString(), '/');
		return str_starts_with($path . '/', $slug . '/') ? substr($path, strlen($slug) + 1) : '';
	}

	/** In the Panel at the forms: the container, a form or a part of it (files, a block …) */
	public static function inFormsView(): bool
	{
		return (bool) preg_match('#^pages/' . preg_quote(self::slug(), '#') . '(\+|/|$)#', self::panelPath());
	}

	/** Anywhere in the Form Wizard: its menu entries are open there */
	public static function inArea(?string $current): bool
	{
		return in_array($current, ['formwizard', 'formwizard-forms', 'formwizard-submissions'], true) || self::inFormsView();
	}

	/** The Panel link to the forms */
	public static function panelUrl(): string
	{
		return 'pages/' . self::slug();
	}
}
