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
		'fields'   => ['fields', 'captcha', 'buttontext', 'buttonwidth', 'buttonalign'],
		'after'    => [
			'success', 'successtext', 'successpage',
			'confirm', 'confirmcopy', 'confirmsubject', 'confirmtext',
		],
		'actions'  => [
			'action',
			'headlinemail', 'recipients', 'mailsubject',
			'headlinewebhook', 'webhookurl',
		],
	];

	/**
	 * May the user edit forms? (who may open the Project Wizard) – an access
	 * Kirby doesn't know counts as allowed: before Kirby 5.6 the areas'
	 * accesses are unknown in API requests, every admin would be refused
	 */
	public static function guard(): void
	{
		$user = kirby()->user();
		$permissions = $user?->role()->permissions();
		if (!$user || $permissions->for('access', 'panel') === false || $permissions->for('access', 'projectwizard', true) === false) {
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
		fwForms::activateDrafts();
		fwForms::adoptDefaultRecipient();
		$items = [];
		foreach (fwForms::forms()->sortBy('title', 'asc') as $form) {
			$new = fwSubmissions::countNew($form);
			$items[] = [
				'view'  => 'form:' . $form->slug(),
				'text'  => $form->title()->value(),
				'icon'  => 'formwizard',
				'badge' => $new ?: null,
			];
		}
		// (a new form: the button above the list of all)
		if ($items !== []) $items[] = '-';
		// (the submissions: each storing form in Kirby's menu on the left –
		// everyday work, also for editors without the Project Wizard)
		$items[] = ['view' => 'settings', 'text' => t('formwizard.settings'), 'icon' => 'settings'];
		return $items;
	}

	/* -------------- The list --------------*/

	public static function list(): array
	{
		fwForms::activateDrafts();
		$list = [];
		foreach (fwForms::forms()->sortBy('title', 'asc') as $form) {
			$list[] = [
				'slug'        => $form->slug(),
				'title'       => $form->title()->value(),
				'fields'      => $form->fwFieldCount(),
				'submissions' => fwSubmissions::count($form),
				'new'         => fwSubmissions::countNew($form),
				'folder'      => fwSubmissions::folder($form),
				// (an entry in Kirby's menu: only when it stores its submissions)
				'stores'      => $form->fwStores(),
				'usage'       => array_map(fn (Page $p) => $p->title()->value(), $form->fwUsage()),
			];
		}
		return $list;
	}

	/** A new form (offered in the blocks right away) – its slug */
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
			// (mailed to the sender's address – the mail server's – to start with)
			'content'  => array_merge(fwTemplates::content($template), ['title' => $title], self::startRecipients()),
		])->changeStatus('listed');
		return $slug;
	}

	/** A new form's recipients: the sender of Forms › E-Mail sending, if set up */
	private static function startRecipients(): array
	{
		if (!class_exists('pwMail') || !method_exists('pwMail', 'sender')) return [];
		$sender = implode(', ', pwMail::sender());
		return $sender !== '' ? ['recipients' => $sender] : [];
	}

	/* -------------- One form --------------*/

	/** A form for the editor: its fields by tab (with their endpoints) and values */
	public static function load(string $slug, ?string $language = null): array
	{
		$form   = self::adoptLegacy(self::form($slug));
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

		// the captcha: only with one set up (Forms › Settings), its name below
		if (isset($tabs['fields']['captcha'])) {
			$provider = fwCaptcha::setUp();
			if ($provider === null) unset($tabs['fields']['captcha']);
			else $tabs['fields']['captcha']['help'] = fwCaptcha::NAMES[$provider];
		}

		// what happens with a submission: older forms' from their two switches
		if (isset($tabs['actions']['action'])) $values['action'] = $form->fwActions();
		// where the stored submissions are and how long they stay (below the
		// actions, when stored)
		$days = fwSettings::get()['retention'];
		$storeInfo = tt($days > 0 ? 'formwizard.store.info' : 'formwizard.store.info.forever', null, ['days' => $days]);

		// the fields in another language: completed from the default language
		// (else required, width … looked unset there)
		if (isset($values['fields']) && is_array($values['fields']) && $language !== null && $language !== kirby()->defaultLanguage()?->code()) {
			$fixed = [];
			foreach ($props['fields']['fieldsets'] ?? [] as $type => $fieldset) {
				foreach ($fieldset['tabs'] ?? [] as $tab) {
					foreach ($tab['fields'] ?? [] as $name => $field) {
						if (($field['translate'] ?? true) === false) $fixed[$type][] = $name;
					}
				}
			}
			$values['fields'] = FormwizardFormPage::fwCompleted($values['fields'], Form::for($form, language: kirby()->defaultLanguage()?->code())->toFormValues()['fields'] ?? [], $fixed);
		}

		return [
			'slug'        => $form->slug(),
			'title'       => $form->title()->value(),
			'tabs'        => $tabs,
			'storeInfo'   => $storeInfo,
			// (the preview's line below an upload field: the types, the size)
			'uploads'     => [
				'groups'   => array_map(fn ($g) => array_map('strtoupper', array_slice($g['ext'], 0, 3)), fwUploads::GROUPS),
				'phpLimit' => round(fwUploads::phpLimit() / 1024 / 1024, 1),
			],
			'values'      => array_intersect_key($values, array_merge(...array_values($tabs))),
			'submissions' => fwSubmissions::count($form),
			'new'         => fwSubmissions::countNew($form),
			'folder'      => fwSubmissions::folder($form),
			'usage'       => array_map(fn (Page $p) => $p->title()->value(), $form->fwUsage()),
		];
	}

	/**
	 * Up to formwizard 0.1 the form had the privacy page and one choice for
	 * the uploads; now they are the fields' own (the consent's page, an
	 * upload field's "attach to the mail") – taken over once, before the
	 * editor saves the fields (Kirby writes the switch's default then);
	 * options get their value kept
	 */
	private static function adoptLegacy(FormwizardFormPage $form): FormwizardFormPage
	{
		$code    = kirby()->defaultLanguage()?->code();
		$content = $form->content($code);
		$page    = $content->get('privacyPage')->value();
		$uploads = $content->get('uploads')->value();
		$attach  = in_array($uploads, ['attach', 'both'], true);
		$blocks  = \Kirby\Data\Json::decode((string) $content->get('fields')->value() ?: '[]');
		$changed = $page !== '' || $uploads !== '';
		// options without a value of their own: their slug kept (by the
		// default language's rules – as the conditions on them were made),
		// so the Panel and every language of the website read the same one
		$rules = \Kirby\Toolkit\Str::$language;
		\Kirby\Toolkit\Str::$language = kirby()->defaultLanguage()?->rules() ?? [];
		foreach ($blocks as $i => $block) {
			if (!fwForms::hasOptions($block['type'] ?? '') || !is_array($block['content']['options'] ?? null)) continue;
			foreach ($block['content']['options'] as $j => $option) {
				$text = trim((string) ($option['text'] ?? ''));
				if ($text === '' || trim((string) ($option['value'] ?? '')) !== '') continue;
				$blocks[$i]['content']['options'][$j]['value'] = \Kirby\Toolkit\Str::slug($text);
				$changed = true;
			}
		}
		\Kirby\Toolkit\Str::$language = $rules;
		if (!$changed) return $form;
		// (the privacy page and the uploads' choice only when still there)
		foreach ($page !== '' || $uploads !== '' ? $blocks : [] as $i => $block) {
			$type = $block['type'] ?? '';
			if ($type === 'fwconsent' && $page !== '' && empty($block['content']['privacy'])) {
				$blocks[$i]['content']['privacy'] = \Kirby\Data\Yaml::decode($page);
			}
			if ($type === 'fwfile' && !array_key_exists('attach', $block['content'] ?? [])) {
				$blocks[$i]['content']['attach'] = $attach ? 'true' : 'false';
			}
		}
		$update = ['fields' => $blocks] + ($page !== '' || $uploads !== '' ? ['privacyPage' => null, 'uploads' => null] : []);
		try {
			return kirby()->impersonate('kirby', fn () => $form->update($update, $code));
		} catch (\Throwable $e) {
			return $form;
		}
	}

	/** The editor's values saved (and the name, if changed) */
	public static function save(string $slug, array $values, ?string $title = null, ?string $language = null): array
	{
		$form = self::form($slug);
		$allowed = array_merge(...array_values(self::TABS));
		$values = array_intersect_key(array_change_key_case($values, CASE_LOWER), array_flip($allowed));
		// mailed: at least one recipient; the webhook: its address (Kirby's
		// text for a required field)
		$actions = isset($values['action']) ? (is_array($values['action']) ? $values['action'] : array_map('trim', explode(',', (string) $values['action']))) : $form->fwActions();
		$recipients = $values['recipients'] ?? (string) $form->content()->get('recipients')->value();
		if (in_array('mail', $actions, true) && fwActions::addresses((string) $recipients) === []) {
			throw new \Kirby\Exception\InvalidArgumentException(message: t('formwizard.team.recipients') . ': ' . t('field.required'));
		}
		$url = $values['webhookurl'] ?? (string) $form->content()->get('webhookUrl')->value();
		if (in_array('webhook', $actions, true) && trim((string) $url) === '') {
			throw new \Kirby\Exception\InvalidArgumentException(message: t('formwizard.webhook.url') . ': ' . t('field.required'));
		}
		// one e-mail field per form
		if (isset($values['fields']) && is_array($values['fields']) && count(array_filter($values['fields'], fn ($b) => is_array($b) && ($b['type'] ?? '') === 'fwemail')) > 1) {
			throw new \Kirby\Exception\InvalidArgumentException(message: t('formwizard.email.once'));
		}
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

	/** A form renamed (in the language shown); its slug and keys stay – the new name */
	public static function rename(string $slug, string $title, ?string $language = null): string
	{
		$title = trim($title);
		if ($title === '') throw new \Kirby\Exception\InvalidArgumentException(message: t('formwizard.form.name.missing'));
		return self::form($slug)->changeTitle($title, $language)->title()->value();
	}

	/** A form deleted, with its submissions */
	public static function delete(string $slug): void
	{
		$form = self::form($slug);
		fwSubmissions::deleteForm($form);
		$form->delete(true);
	}

}
