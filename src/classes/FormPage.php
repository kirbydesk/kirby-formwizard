<?php

use Kirby\Cms\Block;
use Kirby\Cms\Blocks;
use Kirby\Cms\Page;

/**
 * A form: its fields are the blocks of the field "fields"
 */
class FormwizardFormPage extends Page
{
	public const ACTIONS = ['store', 'mail', 'webhook'];

	/**
	 * What happens with a submission: any of store, mail, webhook – up to
	 * formwizard 0.1 three switches (store, mailTeam, webhook); stored and
	 * mailed both off is taken as mail, so no data is stored that wasn't
	 */
	public function fwActions(): array
	{
		$c = $this->content();
		$value = trim((string) $c->get('action')->value());
		if ($value !== '') {
			$list = array_map('trim', explode(',', $value === 'both' ? 'store, mail' : $value));
			$list = array_values(array_intersect(self::ACTIONS, $list));
			if ($list !== []) return $list;
		}
		$store   = $c->get('store')->isEmpty() || $c->get('store')->toBool();
		$mail    = $c->get('mailTeam')->isEmpty() || $c->get('mailTeam')->toBool();
		$webhook = $c->get('webhook')->toBool();
		if (!$store && !$mail) $mail = true;
		return array_keys(array_filter(['store' => $store, 'mail' => $mail, 'webhook' => $webhook]));
	}

	public function fwStores(): bool
	{
		return in_array('store', $this->fwActions(), true);
	}

	public function fwMails(): bool
	{
		return in_array('mail', $this->fwActions(), true);
	}

	public function fwWebhook(): bool
	{
		return in_array('webhook', $this->fwActions(), true);
	}

	/**
	 * The upload fields whose files go with the team's mail (their switch;
	 * up to formwizard 0.1 one choice for the form: uploads attach or both).
	 * Not stored: all of them – the mail is where they go.
	 */
	public function fwAttaches(string $key, bool $stored): bool
	{
		if (!$stored) return true;
		foreach (fwRender::fields($this) as $field) {
			if ($field['key'] !== $key || $field['type'] !== 'fwfile') continue;
			$own = $field['block']->content()->get('attach');
			if ($own->isNotEmpty()) return $own->toBool();
		}
		return in_array((string) $this->content()->get('uploads')->value(), ['attach', 'both'], true);
	}

	/** The fields of the form (blocks of the known field types) */
	public function fwFields(): Blocks
	{
		$field = $this->content()->get('fields');
		return Blocks::factory($this->fwFieldsData(), ['parent' => $this, 'field' => $field])
			->filter(fn (Block $b) => isset(fwForms::TYPES[$b->type()]));
	}

	/**
	 * The fields' blocks as stored, in another language completed from the
	 * default one: what isn't translated (required, key, width …) is kept in
	 * the default language only
	 */
	public function fwFieldsData(?string $language = null): array
	{
		$read = fn (?string $code) => \Kirby\Data\Json::decode((string) $this->content($code)->get('fields')->value() ?: '[]');
		$kirby = $this->kirby();
		$default = $kirby->defaultLanguage()?->code();
		$language ??= $kirby->language()?->code();
		$blocks = $read($language);
		if (!$kirby->multilang() || $language === null || $language === $default) return $blocks;
		return self::fwCompleted($blocks, $read($default));
	}

	/**
	 * Blocks of a language with what is missing taken from the same blocks
	 * of the default language – and the fields not translated ($fixed:
	 * type => names) always from there (the Panel fills them with defaults)
	 */
	public static function fwCompleted(array $blocks, array $defaults, array $fixed = []): array
	{
		$byId = [];
		foreach ($defaults as $block) {
			if (is_array($block) && isset($block['id'])) $byId[$block['id']] = $block['content'] ?? [];
		}
		foreach ($blocks as $i => $block) {
			$base = is_array($block) ? ($byId[$block['id'] ?? ''] ?? null) : null;
			if (!is_array($base) || !is_array($block['content'] ?? null)) continue;
			$content = $block['content'] + $base;
			foreach ($fixed[$block['type'] ?? ''] ?? [] as $name) {
				if (array_key_exists($name, $base)) $content[$name] = $base[$name];
			}
			// the options: row by row (their values aren't translated)
			if (is_array($content['options'] ?? null) && is_array($base['options'] ?? null)) {
				foreach ($content['options'] as $j => $option) {
					if (is_array($option) && is_array($base['options'][$j] ?? null)) $content['options'][$j] = $option + $base['options'][$j];
				}
			}
			$blocks[$i]['content'] = $content;
		}
		return $blocks;
	}

	/** How many fields take an answer (for the overview) */
	public function fwFieldCount(): int
	{
		return $this->fwFields()->filter(fn (Block $b) => fwForms::isInput($b->type()))->count();
	}

	/** "Fields: 8 · on: Contact, Services" (for the list of the forms) */
	public function fwInfo(): string
	{
		$info  = tt('formwizard.form.info.fields', null, ['count' => $this->fwFieldCount()]);
		if ($count = fwSubmissions::count($this)) {
			$new   = fwSubmissions::countNew($this);
			$info .= ' · ' . tt('formwizard.form.info.submissions', null, ['count' => $count]) . ($new ? ' (' . tt('formwizard.form.info.new', null, ['count' => $new]) . ')' : '');
		}
		$pages = $this->fwUsage();
		if ($pages !== []) {
			$titles = array_map(fn (Page $p) => $p->title()->value(), array_slice($pages, 0, 3));
			$info  .= ' · ' . tt('formwizard.form.info.usage', null, ['pages' => implode(', ', $titles) . (count($pages) > 3 ? ' …' : '')]);
		}
		return $info;
	}

	/**
	 * The pages with a block showing this form (their content in the
	 * default language searched for the form's id; once per request)
	 */
	public function fwUsage(): array
	{
		static $index = null;
		if ($index === null) {
			$index = [];
			foreach (site()->index(true) as $page) {
				if (str_starts_with($page->intendedTemplate()->name(), 'formwizard-')) continue;
				$raw = implode("\n", array_filter($page->content()->toArray(), 'is_string'));
				if (str_contains($raw, 'pwform')) $index[] = [$page, str_replace('\\/', '/', $raw)];
			}
		}
		$uuid = $this->uuid()?->toString();
		$list = [];
		foreach ($index as [$page, $raw]) {
			if (($uuid && str_contains($raw, $uuid)) || str_contains($raw, '"' . $this->id() . '"')) $list[] = $page;
		}
		return $list;
	}

	/**
	 * The options of the choice fields, for the condition of a field:
	 * "<block id>:<option value>" => "Label = Option"
	 */
	public function fwConditions(): array
	{
		$list = [];
		foreach ($this->fwFields() as $block) {
			if (!fwForms::hasOptions($block->type())) continue;
			$label = $block->content()->get('label')->value() ?: t('formwizard.type.' . $block->type());
			foreach (fwFields::options($block) as $value => $text) {
				$list[$block->id() . ':' . $value] = $label . ' = ' . $text;
			}
		}
		return $list;
	}
}
