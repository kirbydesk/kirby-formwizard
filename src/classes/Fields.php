<?php

use Kirby\Cms\Block;
use Kirby\Toolkit\Str;

/**
 * The field types of a form: their blueprints (one block type each) and
 * the reading of their settings
 */
class fwFields
{
	/* -------------- Reading --------------*/

	/** The options of a choice field: value => text (the value from the text when empty) */
	public static function options(Block $block): array
	{
		$options = [];
		foreach ($block->content()->get('options')->toStructure() as $row) {
			$text  = trim((string) $row->content()->get('text')->value());
			$value = trim((string) $row->content()->get('value')->value()) ?: Str::slug($text);
			if ($text === '' || $value === '' || isset($options[$value])) continue;
			$options[$value] = $text;
		}
		return $options;
	}

	/* -------------- Blueprints --------------*/

	/** The block blueprints of all field types: blocks/fwtext … */
	public static function blueprints(): array
	{
		$list = [];
		foreach (fwForms::TYPES as $type => $info) {
			$list['blocks/' . $type] = fn () => self::blueprint($type);
		}
		return $list;
	}

	public static function blueprint(string $type): array
	{
		[$icon, $wide, $hasOptions] = fwForms::TYPES[$type];

		$fields = [];
		$fields['label'] = [
			'label'    => $type === 'fwinfo' ? 'formwizard.field.title' : 'formwizard.field.label',
			'type'     => 'text',
			'required' => true,
			// (a new field: the name of its type, so the form can be published at once)
			'default'  => t('formwizard.type.' . $type),
			'width'    => $type === 'fwinfo' ? '1/1' : '1/2',
		];
		if ($type !== 'fwinfo') {
			$fields['key'] = [
				'label'     => 'formwizard.field.key',
				'type'      => 'slug',
				'translate' => false,
				'width'     => '1/2',
				'help'      => 'formwizard.field.key.help',
			];
		}

		// what is asked: placeholder, help, the type's own settings
		$fields = array_merge($fields, match ($type) {
			'fwtext'       => self::placeholder() + self::help() + self::length(),
			'fwemail'      => self::placeholder() + self::help(),
			'fwtel'        => self::placeholder() + self::help(),
			'fwnumber'     => self::placeholder() + self::help() + self::range(),
			'fwdate'       => self::help(),
			'fwtextarea'   => self::placeholder() + self::help() + self::length(),
			'fwselect'     => self::placeholder('formwizard.field.placeholder.select') + self::help(),
			'fwradio'      => self::help(),
			'fwcheckboxes' => self::help(),
			'fwfile'       => self::help() + self::file(),
			'fwhidden'     => self::hidden(),
			'fwinfo'       => self::info(),
			'fwconsent'    => self::consent(),
		});

		// required and width (a hidden field has neither, a heading isn't required)
		if (!in_array($type, ['fwhidden', 'fwinfo'], true)) {
			$fields['required'] = [
				'label'     => 'formwizard.field.required',
				'type'      => 'toggle',
				'default'   => $type === 'fwconsent',
				'translate' => false,
				'width'     => '1/2',
			];
		}
		if ($type !== 'fwhidden') {
			$fields['width'] = [
				'label'     => 'formwizard.field.width',
				'type'      => 'toggles',
				'default'   => $wide ? 'full' : 'half',
				'translate' => false,
				'width'     => '1/2',
				// (as Kirby's widths: a fraction each)
				'options'   => array_map(fn ($w) => ['value' => $w, 'text' => ['half' => '1/2', 'full' => '1/1'][$w] ?? $w], fwForms::WIDTHS),
			];
		}

		// all on one page: the options and the condition below headings
		if ($hasOptions) {
			$fields['headlineOptions'] = ['label' => 'formwizard.tab.options', 'type' => 'headline'];
			$fields += self::optionsField();
		}
		$fields['headlineCondition'] = ['label' => 'formwizard.tab.condition', 'type' => 'headline'];
		$fields += self::condition();

		return [
			'name'    => 'formwizard.type.' . $type,
			'icon'    => $icon,
			'fields'  => $fields,
		];
	}

	private static function placeholder(string $default = ''): array
	{
		return ['placeholder' => [
			'label'       => 'formwizard.field.placeholder',
			'type'        => 'text',
			'placeholder' => $default !== '' ? $default : null,
			'width'       => '1/2',
		]];
	}

	private static function help(): array
	{
		return ['help' => [
			'label' => 'formwizard.field.help',
			'type'  => 'text',
			'width' => '1/2',
		]];
	}

	private static function length(): array
	{
		return [
			'minlength' => ['label' => 'formwizard.field.minlength', 'type' => 'number', 'min' => 0, 'translate' => false, 'width' => '1/4'],
			'maxlength' => ['label' => 'formwizard.field.maxlength', 'type' => 'number', 'min' => 1, 'translate' => false, 'width' => '1/4'],
		];
	}

	private static function range(): array
	{
		return [
			'min'  => ['label' => 'formwizard.field.min', 'type' => 'number', 'step' => 'any', 'translate' => false, 'width' => '1/6'],
			'max'  => ['label' => 'formwizard.field.max', 'type' => 'number', 'step' => 'any', 'translate' => false, 'width' => '1/6'],
			'step' => ['label' => 'formwizard.field.step', 'type' => 'number', 'step' => 'any', 'min' => 0, 'translate' => false, 'width' => '1/6'],
		];
	}

	private static function file(): array
	{
		return [
			'accept' => [
				'label'     => 'formwizard.field.accept',
				'type'      => 'checkboxes',
				'columns'   => 3,
				'default'   => ['documents', 'images'],
				'translate' => false,
				'help'      => 'formwizard.field.accept.help',
				'options'   => array_map(
					fn ($group) => ['value' => $group, 'text' => ['*' => 'formwizard.field.accept.' . $group]],
					array_keys(fwUploads::GROUPS)
				),
			],
			'maxsize' => [
				'label'     => 'formwizard.field.maxsize',
				'type'      => 'number',
				'default'   => 10,
				'min'       => 1,
				'max'       => 100,
				'after'     => 'MB',
				'translate' => false,
				'width'     => '1/4',
			],
			'multiple' => [
				'label'     => 'formwizard.field.multiple',
				'type'      => 'toggle',
				'translate' => false,
				'width'     => '1/4',
			],
			// (only when the form mails: hidden in the editor otherwise)
			'attach' => [
				'label'     => 'formwizard.field.attach',
				'type'      => 'toggle',
				'translate' => false,
				'help'      => 'formwizard.field.attach.help',
			],
		];
	}

	private static function hidden(): array
	{
		return [
			'source' => [
				'label'     => 'formwizard.field.source',
				'type'      => 'select',
				'default'   => 'page',
				'required'  => true,
				'translate' => false,
				'width'     => '1/2',
				'options'   => [
					['value' => 'page', 'text' => ['*' => 'formwizard.field.source.page']],
					['value' => 'param', 'text' => ['*' => 'formwizard.field.source.param']],
					['value' => 'fixed', 'text' => ['*' => 'formwizard.field.source.fixed']],
				],
			],
			'value' => [
				'label' => 'formwizard.field.value',
				'type'  => 'text',
				'width' => '1/2',
				'help'  => 'formwizard.field.value.help',
				'when'  => ['source' => 'param'],
			],
			'fixed' => [
				'label' => 'formwizard.field.value',
				'type'  => 'text',
				'width' => '1/2',
				'when'  => ['source' => 'fixed'],
			],
		];
	}

	private static function info(): array
	{
		return ['text' => [
			'label'   => 'formwizard.field.text',
			'type'    => 'textarea',
			'buttons' => ['bold', 'italic', 'link'],
			'size'    => 'small',
		]];
	}

	private static function consent(): array
	{
		return [
			'text' => [
				'label'   => 'formwizard.field.text',
				'type'    => 'writer',
				'inline'  => true,
				'marks'   => ['bold', 'italic', 'link'],
				'help'    => 'formwizard.field.consent.help',
			],
			// the page the standard text links to
			'privacy' => [
				'label'     => 'formwizard.privacy',
				'type'      => 'pages',
				'multiple'  => false,
				'translate' => false,
				'help'      => 'formwizard.privacy.help',
			],
		];
	}

	private static function optionsField(): array
	{
		return ['options' => [
			'label'     => '',
			'type'      => 'structure',
			'sortable'  => true,
			// (at least one; its label hidden – the heading names it – see index.css)
			'required'  => true,
			'min'       => 1,
			'columns'   => ['text' => ['width' => '2/3'], 'value' => ['width' => '1/3']],
			'help'      => 'formwizard.field.options.help',
			'fields'    => [
				'text' => [
					'label'    => 'formwizard.field.option.text',
					'type'     => 'text',
					'required' => true,
				],
				'value' => [
					'label'     => 'formwizard.field.option.value',
					'type'      => 'slug',
					'translate' => false,
					// (from the text while typing: the value conditions keep)
					'sync'      => 'text',
					'help'      => 'formwizard.field.option.value.help',
				],
			],
		]];
	}

	private static function condition(): array
	{
		return ['when' => [
			'label'       => 'formwizard.field.when',
			'type'        => 'fwwhen',
			'placeholder' => 'formwizard.field.when.always',
			'translate'   => false,
			'help'        => 'formwizard.field.when.help',
			'options'     => [
				'type'  => 'query',
				'query' => 'page.fwConditions',
			],
		]];
	}
}
