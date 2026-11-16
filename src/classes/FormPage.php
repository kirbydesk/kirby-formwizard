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

	/** "8 fields" (for the list of the forms) */
	public function fwInfo(): string
	{
		$count = $this->fwFieldCount();
		return tt('formwizard.form.info.fields', null, ['count' => $count]);
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
