<?php

use Kirby\Cms\Block;
use Kirby\Cms\Blocks;
use Kirby\Cms\Page;

/**
 * A form: its fields are the blocks of the field "fields"
 */
class FormwizardFormPage extends Page
{
	/** The fields of the form (blocks of the known field types) */
	public function fwFields(): Blocks
	{
		return $this->content()->get('fields')->toBlocks()->filter(fn (Block $b) => isset(fwForms::TYPES[$b->type()]));
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
