<?php

use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;

/**
 * The forms in the Project Wizard (its tab "Forms"): the dropdown, the list,
 * a form to edit – its fields as Kirby's form fields (with their API
 * endpoints, so the blocks field works as in a page), its values, saving.
 * The forms stay pages; they just aren't edited in the page view.
 */
class fwEditor
{
	/** The fields of a form by tab of the editor */
	public const TABS = [
		'fields'   => ['fields'],
		'after'    => [
			'success', 'successtext', 'successpage',
			'headlineteam', 'mailteam', 'recipients', 'routes', 'mailsubject',
			'headlineconfirm', 'confirm', 'confirmcopy', 'confirmsubject', 'confirmtext',
			'headlineuploads', 'uploads',
			'headlinewebhook', 'webhook', 'webhookurl',
		],
		'settings' => ['buttontext', 'privacypage', 'store', 'captcha', 'closed', 'closedtext'],
	];

	/** May the user edit forms? (who may open the Project Wizard) */
	public static function guard(): void
	{
		$user = kirby()->user();
		if (!$user || $user->role()->permissions()->for('access', 'projectwizard') === false) {
			throw new PermissionException(message: 'Not allowed.');
		}
	}

	public static function form(string $slug): FormwizardFormPage
	{
		$form = fwForms::container()?->findPageOrDraft($slug);
		if (!$form instanceof FormwizardFormPage) throw new NotFoundException(message: 'The form doesn’t exist.');
		return $form;
	}

	/* -------------- The tab's dropdown --------------*/

	public static function menu(): array
	{
		$items = [['heading' => t('formwizard.forms')]];
		foreach (fwForms::forms()->sortBy('title', 'asc') as $form) {
			$new = fwSubmissions::countNew($form);
			$items[] = [
				'view'  => 'form:' . $form->slug(),
				'text'  => $form->title()->value(),
				'icon'  => 'formwizard',
				'badge' => $new ?: null,
				'info'  => $form->isDraft() ? t('formwizard.status.draft') : null,
			];
		}
		$items[] = ['view' => 'new', 'text' => t('formwizard.form.new'), 'icon' => 'add'];
		$items[] = '-';
		$items[] = ['view' => 'list', 'text' => t('formwizard.forms.all'), 'icon' => 'list-bullet'];
		$items[] = ['link' => 'formwizard/submissions', 'text' => t('formwizard.submissions'), 'icon' => 'email', 'badge' => fwSubmissions::countNew() ?: null];
		$items[] = ['view' => 'settings', 'text' => t('formwizard.settings'), 'icon' => 'settings'];
		return $items;
	}

	/* -------------- The list --------------*/

	public static function list(): array
	{
		$list = [];
		foreach (fwForms::forms()->sortBy('title', 'asc') as $form) {
			$list[] = [
				'slug'        => $form->slug(),
				'title'       => $form->title()->value(),
				'status'      => $form->isDraft() ? 'draft' : 'listed',
				'closed'      => $form->content()->get('closed')->toBool(),
				'fields'      => $form->fwFieldCount(),
				'submissions' => fwSubmissions::count($form),
				'new'         => fwSubmissions::countNew($form),
				'folder'      => fwSubmissions::folder($form),
				'usage'       => array_map(fn (Page $p) => $p->title()->value(), $form->fwUsage()),
			];
		}
		return $list;
	}

	/** A new form (a draft) – its slug */
	public static function create(string $title, string $template = ''): string
	{
		$title = trim($title);
		if ($title === '') throw new \Kirby\Exception\InvalidArgumentException(message: t('formwizard.form.name.missing'));
		$container = fwForms::ensureContainer();
		if (!$container) throw new NotFoundException(message: 'No page for the forms.');
		$slug = Str::slug($title) ?: 'form';
		$base = $slug;
		for ($i = 2; $container->findPageOrDraft($slug); $i++) $slug = $base . '-' . $i;
		$container->createChild([
			'slug'     => $slug,
			'template' => 'formwizard-form',
			'draft'    => true,
			'content'  => array_merge(fwTemplates::content($template), ['title' => $title]),
		]);
		return $slug;
	}

	/* -------------- One form --------------*/

	/** A form for the editor: its fields by tab (with their endpoints) and values */
	public static function load(string $slug, ?string $language = null): array
	{
		$form   = self::form($slug);
		$editor = Form::for($form, language: $language);
		$props  = $editor->toProps();
		$values = $editor->toFormValues();
		$path   = 'pages/' . str_replace('/', '+', $form->id());

		$tabs = [];
		foreach (self::TABS as $tab => $names) {
			foreach ($names as $name) {
				if (!isset($props[$name])) continue;
				$field = $props[$name];
				$field['endpoints'] = [
					'field'   => $path . '/fields/' . $name,
					'section' => $path . '/sections/' . $tab,
					'model'   => $path,
				];
				$tabs[$tab][$name] = $field;
			}
		}

		return [
			'slug'        => $form->slug(),
			'title'       => $form->title()->value(),
			'status'      => $form->isDraft() ? 'draft' : 'listed',
			'tabs'        => $tabs,
			'values'      => array_intersect_key($values, array_merge(...array_values($tabs))),
			'submissions' => fwSubmissions::count($form),
			'new'         => fwSubmissions::countNew($form),
			'folder'      => fwSubmissions::folder($form),
			'usage'       => array_map(fn (Page $p) => $p->title()->value(), $form->fwUsage()),
		];
	}

	/** The editor's values saved (and the name, if changed) */
	public static function save(string $slug, array $values, ?string $title = null, ?string $language = null): array
	{
		$form = self::form($slug);
		$allowed = array_merge(...array_values(self::TABS));
		$values = array_intersect_key(array_change_key_case($values, CASE_LOWER), array_flip($allowed));
		if (isset($values['fields']) && is_array($values['fields'])) $values['fields'] = self::fixKeys($values['fields']);
		$form = $form->update($values, $language);
		if ($title !== null && trim($title) !== '' && trim($title) !== $form->title()->value()) {
			$form = $form->changeTitle(trim($title), $language);
		}
		return self::load($form->slug(), $language);
	}

	/**
	 * Keys and option values filled in from the label/text where empty –
	 * once: renaming a field later keeps its key (the placeholders, the
	 * export, the conditions stay as they are). A key twice gets a number.
	 */
	public static function fixKeys(array $blocks): array
	{
		$seen = [];
		foreach ($blocks as $i => $block) {
			if (!is_array($block) || !is_array($block['content'] ?? null)) continue;
			$c = $block['content'];
			if (($block['type'] ?? '') !== 'fwinfo') {
				$key = Str::slug((string) ($c['key'] ?? '')) ?: Str::slug((string) ($c['label'] ?? '')) ?: 'field';
				$base = $key;
				for ($n = 2; isset($seen[$key]); $n++) $key = $base . '-' . $n;
				$seen[$key] = true;
				$c['key'] = $key;
			}
			if (isset($c['options']) && is_array($c['options'])) {
				foreach ($c['options'] as $j => $option) {
					if (is_array($option) && trim((string) ($option['value'] ?? '')) === '') {
						$c['options'][$j]['value'] = Str::slug((string) ($option['text'] ?? ''));
					}
				}
			}
			$blocks[$i]['content'] = $c;
		}
		return $blocks;
	}

	public static function setStatus(string $slug, string $status): array
	{
		$form = self::form($slug);
		$form->changeStatus($status === 'listed' ? 'listed' : 'draft');
		return self::load($slug);
	}

	/** A form deleted (its submissions stay until they are deleted themselves) */
	public static function delete(string $slug): void
	{
		self::form($slug)->delete(true);
	}

}
