<?php

use Kirby\Toolkit\Str;

/**
 * Templates for a new form: its fields (and a few settings) to start
 * with – in the language of the Panel. Everything can be changed after.
 */
class fwTemplates
{
	/** [key, type, required, width, extra] per field; labels from formwizard.tpl.<template>.<key> or formwizard.sample.* */
	public const TEMPLATES = [
		'contact' => [
			'fields' => [
				['name', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['phone', 'fwtel', false, 'half'],
				['company', 'fwtext', false, 'half'],
				['subject', 'fwtext', false, 'full'],
				['message', 'fwtextarea', true, 'full'],
				['privacy', 'fwconsent', true, 'full'],
			],
		],
		'callback' => [
			'fields' => [
				['name', 'fwtext', true, 'half'],
				['phone', 'fwtel', true, 'half'],
				['time', 'fwradio', true, 'full', ['options' => ['morning', 'afternoon', 'any']]],
				['topic', 'fwtextarea', false, 'full'],
				['privacy', 'fwconsent', true, 'full'],
			],
		],
		'application' => [
			'fields' => [
				['about', 'fwinfo', false, 'full'],
				['firstname', 'fwtext', true, 'half'],
				['lastname', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['phone', 'fwtel', false, 'half'],
				['position', 'fwtext', true, 'half'],
				['start', 'fwdate', false, 'half'],
				['documents', 'fwinfo', false, 'full'],
				['cv', 'fwfile', true, 'full', ['accept' => 'documents', 'maxsize' => 10]],
				['more', 'fwfile', false, 'full', ['accept' => 'documents, images', 'maxsize' => 10, 'multiple' => true]],
				['message', 'fwtextarea', false, 'full'],
				['privacy', 'fwconsent', true, 'full'],
			],
			'content' => ['uploads' => 'store'],
		],
		'registration' => [
			'fields' => [
				['name', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['date', 'fwselect', true, 'half', ['options' => ['date1', 'date2']]],
				['persons', 'fwnumber', true, 'half', ['min' => 1, 'max' => 10]],
				['notes', 'fwtextarea', false, 'full'],
				['privacy', 'fwconsent', true, 'full'],
			],
			'content' => ['confirm' => 'true'],
		],
	];

	/** The templates for the Panel: [value, text] (the empty one first) */
	public static function options(): array
	{
		$list = [['value' => '', 'text' => t('formwizard.tpl.empty')]];
		foreach (array_keys(self::TEMPLATES) as $key) {
			$list[] = ['value' => $key, 'text' => t('formwizard.tpl.' . $key)];
		}
		return $list;
	}

	/** The content of a new form from a template (an unknown one: none) */
	public static function content(string $template): array
	{
		$def = self::TEMPLATES[$template] ?? null;
		if ($def === null) return [];

		$blocks = [];
		foreach ($def['fields'] as $row) {
			[$key, $type, $required, $width] = $row;
			$extra = $row[4] ?? [];
			$label = t('formwizard.tpl.' . $template . '.' . $key, t('formwizard.tpl.field.' . $key, ucfirst($key)));
			$content = ['label' => $label, 'width' => $width];
			if ($type !== 'fwinfo') $content['key'] = $key;
			if ($type !== 'fwinfo' && $type !== 'fwhidden') $content['required'] = $required;
			foreach ($extra as $name => $value) {
				$content[$name] = $name === 'options'
					? array_map(fn ($o) => ['text' => t('formwizard.tpl.' . $template . '.' . $key . '.' . $o, $o), 'value' => $o], $value)
					: $value;
			}
			$blocks[] = ['id' => Str::uuid(), 'type' => $type, 'isHidden' => false, 'content' => $content];
		}
		return array_merge(['fields' => json_encode($blocks, JSON_UNESCAPED_UNICODE)], $def['content'] ?? []);
	}
}
