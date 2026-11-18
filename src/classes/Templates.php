<?php

use Kirby\Toolkit\Str;

/**
 * Templates for a new form: its fields (and a few settings) to start
 * with – in the language of the Panel. Everything can be changed after.
 */
class fwTemplates
{
	/** [key, type, required, width, extra] per field; labels from formwizard.tpl.<template>.<key> or formwizard.tpl.field.<key>;
	 *  extra 'when' => '<key>:<value>' shows the field only with that choice further up */
	public const TEMPLATES = [
		// the simple one (as the contact form block was)
		'contact' => [
			'fields' => [
				['name', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['phone', 'fwtel', false, 'half'],
				['message', 'fwtextarea', true, 'full'],
				['privacy', 'fwconsent', true, 'full'],
			],
		],
		'contactfull' => [
			'fields' => [
				['salutation', 'fwselect', true, 'half', ['options' => ['ms', 'mr', 'mx']]],
				['title', 'fwtext', false, 'half'],
				['firstname', 'fwtext', true, 'half'],
				['lastname', 'fwtext', true, 'half'],
				['company', 'fwtext', false, 'half'],
				['function', 'fwtext', false, 'half'],
				['email', 'fwemail', true, 'half'],
				['phone', 'fwtel', false, 'half'],
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
				['salutation', 'fwselect', true, 'half', ['options' => ['ms', 'mr', 'mx']]],
				['title', 'fwtext', false, 'half'],
				['firstname', 'fwtext', true, 'half'],
				['lastname', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['phone', 'fwtel', false, 'half'],
				['position', 'fwtext', true, 'half'],
				['start', 'fwdate', false, 'half'],
				['salary', 'fwtext', false, 'half'],
				['profile', 'fwtext', false, 'half'],
				['source', 'fwselect', false, 'half', ['options' => ['website', 'jobportal', 'referral', 'social', 'other']]],
				['documents', 'fwinfo', false, 'full'],
				['cv', 'fwfile', true, 'full', ['accept' => 'documents', 'maxsize' => 10]],
				['more', 'fwfile', false, 'full', ['accept' => 'documents, images', 'maxsize' => 10, 'multiple' => true]],
				['message', 'fwtextarea', false, 'full'],
				['privacy', 'fwconsent', true, 'full'],
				// (kept for later positions: only with its own consent)
				['talentpool', 'fwconsent', false, 'full'],
			],
			// (the applicant wants to know it arrived)
			'content' => ['confirm' => 'true'],
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
		// an example with every field type and a few conditions (one on
		// checkboxes, one leading to two fields)
		'project' => [
			'fields' => [
				['about', 'fwinfo', false, 'full'],
				['salutation', 'fwselect', true, 'half', ['options' => ['ms', 'mr', 'mx']]],
				['company', 'fwtext', false, 'half'],
				['firstname', 'fwtext', true, 'half'],
				['lastname', 'fwtext', true, 'half'],
				['email', 'fwemail', true, 'half'],
				['contactway', 'fwradio', true, 'half', ['options' => ['email', 'phone']]],
				['phone', 'fwtel', true, 'half', ['when' => 'contactway:phone']],
				['calltime', 'fwselect', false, 'half', ['options' => ['morning', 'afternoon', 'evening'], 'when' => 'contactway:phone']],
				['project', 'fwinfo', false, 'full'],
				['kind', 'fwradio', true, 'full', ['options' => ['website', 'relaunch', 'shop', 'other']]],
				['url', 'fwtext', true, 'half', ['when' => 'kind:relaunch']],
				['pages', 'fwnumber', false, 'half', ['min' => 1, 'max' => 500]],
				['features', 'fwcheckboxes', false, 'full', ['options' => ['multilingual', 'blog', 'booking', 'newsletter', 'members']]],
				['languages', 'fwnumber', false, 'half', ['min' => 2, 'max' => 20, 'when' => 'features:multilingual']],
				['budget', 'fwselect', false, 'half', ['options' => ['under5', '5to10', '10to25', 'over25', 'open']]],
				['deadline', 'fwdate', false, 'half'],
				['message', 'fwtextarea', true, 'full'],
				['files', 'fwfile', false, 'full', ['accept' => 'documents, images', 'maxsize' => 10, 'multiple' => true]],
				// (where it was sent from: the page's address)
				['source', 'fwhidden', false, 'full', ['source' => 'page']],
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

		// (the blocks' ids first: a condition names the field by its key)
		$ids = [];
		foreach ($def['fields'] as $row) $ids[$row[0]] = Str::uuid();

		$blocks = [];
		foreach ($def['fields'] as $row) {
			[$key, $type, $required, $width] = $row;
			$extra = $row[4] ?? [];
			$when  = $extra['when'] ?? null;
			unset($extra['when']);
			$label = t('formwizard.tpl.' . $template . '.' . $key, t('formwizard.tpl.field.' . $key, ucfirst($key)));
			$content = ['label' => $label, 'width' => $width];
			if ($type !== 'fwinfo') $content['key'] = $key;
			if ($type !== 'fwinfo' && $type !== 'fwhidden') $content['required'] = $required;
			foreach ($extra as $name => $value) {
				$content[$name] = $name === 'options'
					? array_map(fn ($o) => ['text' => t('formwizard.tpl.' . $template . '.' . $key . '.' . $o, t('formwizard.tpl.field.' . $key . '.' . $o, $o)), 'value' => $o], $value)
					: $value;
			}
			if ($when !== null) {
				[$on, $value] = explode(':', $when, 2);
				if (isset($ids[$on])) $content['when'] = $ids[$on] . ':' . $value;
			}
			$blocks[] = ['id' => $ids[$key], 'type' => $type, 'isHidden' => false, 'content' => $content];
		}
		return array_merge(['fields' => json_encode($blocks, JSON_UNESCAPED_UNICODE)], $def['content'] ?? []);
	}
}
