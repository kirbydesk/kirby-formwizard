<?php

use Kirby\Cms\Block;
use Kirby\Cms\Page;

/**
 * A form on the website: its fields as HTML. Conditional fields are in the
 * page (without JavaScript they stay visible); the script hides them while
 * their condition isn't met. After sending, the result comes from the
 * session (errors at the fields, the values entered, the thanks).
 */
class fwRender
{
	/** Autocomplete for text fields by their key */
	private const AUTOCOMPLETE = [
		'name' => 'name', 'vorname' => 'given-name', 'firstname' => 'given-name', 'first-name' => 'given-name',
		'nachname' => 'family-name', 'lastname' => 'family-name', 'last-name' => 'family-name',
		'firma' => 'organization', 'company' => 'organization', 'unternehmen' => 'organization',
		'strasse' => 'street-address', 'street' => 'street-address', 'adresse' => 'street-address', 'address' => 'street-address',
		'plz' => 'postal-code', 'zip' => 'postal-code', 'postleitzahl' => 'postal-code',
		'ort' => 'address-level2', 'stadt' => 'address-level2', 'city' => 'address-level2',
		'land' => 'country-name', 'country' => 'country-name',
	];

	/**
	 * The fields of a form as the website uses them: one entry per block,
	 * with a unique key, the condition resolved to the key of its field
	 */
	public static function fields(Page $form): array
	{
		$list = [];
		$keys = [];
		$byId = [];
		foreach ($form->fwFields() as $block) {
			$type = $block->type();
			$c    = $block->content();
			$key  = trim((string) $c->get('key')->value()) ?: \Kirby\Toolkit\Str::slug((string) $c->get('label')->value());
			if ($type === 'fwinfo') $key = 'info-' . $block->id();
			$key  = $key !== '' ? $key : $block->id();
			// (a key twice: the second one gets the block's id)
			if (isset($keys[$key])) $key .= '-' . substr($block->id(), 0, 6);
			$keys[$key] = true;

			$field = [
				'id'       => $block->id(),
				'type'     => $type,
				'key'      => $key,
				'label'    => (string) $c->get('label')->value(),
				'required' => $type !== 'fwinfo' && $type !== 'fwhidden' && $c->get('required')->toBool(),
				'width'    => $c->get('width')->value() === 'full' || ($c->get('width')->isEmpty() && fwForms::TYPES[$type][1]) ? 'full' : 'half',
				'block'    => $block,
				'when'     => null,
			];
			if (fwForms::hasOptions($type)) $field['options'] = fwFields::options($block);

			// the condition: a choice field further up and one of its values
			$when = (string) $c->get('when')->value();
			if (str_contains($when, ':')) {
				[$id, $value] = explode(':', $when, 2);
				if (isset($byId[$id])) $field['when'] = ['key' => $byId[$id], 'value' => $value];
			}
			$byId[$block->id()] = $key;
			$list[$key] = $field;
		}
		return $list;
	}

	/** Is a field shown with these values (its condition met)? */
	public static function visible(array $field, array $fields, array $values): bool
	{
		$when = $field['when'];
		if ($when === null) return true;
		// (the field of the condition hidden itself: this one too)
		if (isset($fields[$when['key']]) && !self::visible($fields[$when['key']], $fields, $values)) return false;
		$value = $values[$when['key']] ?? null;
		return is_array($value) ? in_array($when['value'], $value, true) : $value === $when['value'];
	}

	/**
	 * The form as HTML: the hidden fields for sending, the fields, the
	 * button. $state: the result of the last sending (errors, values).
	 */
	public static function form(Page $form, Block $block, Page $page, array $state = []): string
	{
		$fields = self::fields($form);
		$errors = $state['errors'] ?? [];
		$values = $state['values'] ?? [];
		$id     = 'formwizard-' . $block->id();
		$files  = array_filter($fields, fn ($f) => $f['type'] === 'fwfile') !== [];

		$html  = '<form data-formwizard method="post" action="' . url('formwizard/submit') . '" novalidate'
			. ($files ? ' enctype="multipart/form-data"' : '') . '>' . "\n";
		$html .= '<input type="hidden" name="csrf" value="' . csrf() . '">' . "\n";
		$html .= '<input type="hidden" name="page" value="' . esc($page->id(), 'attr') . '">' . "\n";
		$html .= '<input type="hidden" name="block" value="' . esc($block->id(), 'attr') . '">' . "\n";
		$html .= '<input type="hidden" name="language" value="' . esc(kirby()->language()?->code() ?? '', 'attr') . '">' . "\n";
		$html .= '<input type="hidden" name="stamp" value="' . fwSpam::stamp() . '">' . "\n";
		// (only bots fill this in)
		$html .= '<div data-formwizard-trap aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' . "\n";

		foreach ($fields as $field) {
			$html .= self::field($field, $form, $page, $id, $values[$field['key']] ?? null, $errors[$field['key']] ?? null);
		}

		$button = $form->content()->get('buttonText')->or(t('formwizard.button.default'))->value();
		$html .= '<div data-formwizard-submit data-field="buttons"><div data-field="button"><button type="submit"><span class="link-text">' . esc($button) . '</span></button></div></div>' . "\n";
		$html .= '</form>' . "\n";
		return $html;
	}

	/** One field: label, input, help, error */
	private static function field(array $f, Page $form, Page $page, string $prefix, $value, ?string $error): string
	{
		$c     = $f['block']->content();
		$type  = $f['type'];
		$key   = $f['key'];
		$name  = 'fw[' . $key . ']';
		$id    = $prefix . '-' . $key;
		$help  = trim((string) $c->get('help')->value());
		$star  = $f['required'] ? ' <span aria-hidden="true">*</span>' : '';
		$label = esc($f['label']);

		// the attributes every input shares: name, id, required, error, help
		$described = array_filter([$help !== '' ? $id . '-help' : null, $error ? $id . '-error' : null]);
		$attrs = ' id="' . $id . '" name="' . $name . '"'
			. ($f['required'] ? ' required aria-required="true"' : '')
			. ($error ? ' aria-invalid="true"' : '')
			. ($described ? ' aria-describedby="' . implode(' ', $described) . '"' : '');

		$wrap = fn (string $inner) => '<div data-formwizard-field data-type="' . substr($type, 2) . '" data-width="' . $f['width'] . '"'
			. ($f['when'] ? ' data-when="' . esc($f['when']['key'], 'attr') . '" data-when-value="' . esc($f['when']['value'], 'attr') . '"' : '')
			. ($error ? ' data-invalid' : '') . '>' . "\n" . $inner
			. ($help !== '' && $type !== 'fwinfo' ? '<span data-formwizard-help id="' . $id . '-help">' . esc($help) . '</span>' . "\n" : '')
			. ($error ? '<span data-formwizard-error id="' . $id . '-error">' . esc(self::error($error)) . '</span>' . "\n" : '')
			. '</div>' . "\n";

		$text = is_string($value) ? $value : '';

		switch ($type) {
			case 'fwinfo':
				$body = trim((string) $c->get('text')->value());
				return $wrap(($f['label'] !== '' ? '<h3>' . $label . '</h3>' . "\n" : '') . ($body !== '' ? kirbytext($body) . "\n" : ''));

			case 'fwhidden':
				$source = $c->get('source')->value() ?: 'page';
				$hidden = match ($source) {
					'param' => (string) get((string) $c->get('value')->value(), ''),
					'fixed' => (string) $c->get('fixed')->value(),
					default => $page->url(),
				};
				return '<input type="hidden" name="' . $name . '" value="' . esc($hidden, 'attr') . '">' . "\n";

			case 'fwtextarea':
				$len = self::length($c);
				return $wrap('<label for="' . $id . '">' . $label . $star . '</label>' . "\n"
					. '<textarea' . $attrs . ' rows="6"' . $len . self::placeholder($c) . '>' . esc($text) . '</textarea>' . "\n");

			case 'fwselect':
				$placeholder = trim((string) $c->get('placeholder')->value()) ?: t('formwizard.field.placeholder.select');
				$options = '<option value="">' . esc($placeholder) . '</option>';
				foreach ($f['options'] as $v => $t) {
					$options .= '<option value="' . esc($v, 'attr') . '"' . ($text === (string) $v ? ' selected' : '') . '>' . esc($t) . '</option>';
				}
				return $wrap('<label for="' . $id . '">' . $label . $star . '</label>' . "\n" . '<select' . $attrs . '>' . $options . '</select>' . "\n");

			case 'fwradio':
			case 'fwcheckboxes':
				$multiple = $type === 'fwcheckboxes';
				$chosen   = $multiple ? (is_array($value) ? $value : []) : [$text];
				$items    = '';
				$i        = 0;
				foreach ($f['options'] as $v => $t) {
					$oid = $id . '-' . (++$i);
					$items .= '<label for="' . $oid . '"><input type="' . ($multiple ? 'checkbox' : 'radio') . '" id="' . $oid . '" name="' . $name . ($multiple ? '[]' : '') . '" value="' . esc($v, 'attr') . '"'
						. (in_array((string) $v, array_map('strval', $chosen), true) ? ' checked' : '')
						. (!$multiple && $f['required'] ? ' required' : '') . '> <span>' . esc($t) . '</span></label>' . "\n";
				}
				return $wrap('<fieldset' . ($error ? ' aria-invalid="true"' : '') . ($described ? ' aria-describedby="' . implode(' ', $described) . '"' : '') . '>'
					. '<legend>' . $label . $star . '</legend>' . "\n" . '<div data-formwizard-options>' . "\n" . $items . '</div></fieldset>' . "\n");

			case 'fwfile':
				$accept = fwUploads::accept($c);
				$multi  = $c->get('multiple')->toBool();
				return $wrap('<label for="' . $id . '">' . $label . $star . '</label>' . "\n"
					. '<input type="file" id="' . $id . '" name="fwfiles[' . $key . '][]"' . ($multi ? ' multiple' : '')
					. ($accept !== '' ? ' accept="' . esc($accept, 'attr') . '"' : '')
					. ($f['required'] ? ' required aria-required="true"' : '') . ($error ? ' aria-invalid="true"' : '')
					. ($described ? ' aria-describedby="' . implode(' ', $described) . '"' : '') . '>' . "\n"
					. '<span data-formwizard-limits>' . esc(fwUploads::limits($c)) . '</span>' . "\n");

			case 'fwconsent':
				$writer = trim((string) $c->get('text')->value());
				$privacy = $form->content()->get('privacyPage')->toPage();
				$consent = $writer !== '' ? strip_tags($writer, '<a><strong><b><em><i>') : ($privacy
					? tt('formwizard.consent.link', null, ['link' => '<a href="' . $privacy->url() . '">' . esc(t('formwizard.consent.privacy')) . '</a>'])
					: esc(t('formwizard.consent.default')));
				return $wrap('<input type="checkbox" value="1"' . $attrs . ($value ? ' checked' : '') . '>' . "\n"
					. '<label for="' . $id . '">' . $consent . $star . '</label>' . "\n");

			default:
				$input = match ($type) {
					'fwemail'  => 'email',
					'fwtel'    => 'tel',
					'fwnumber' => 'number',
					'fwdate'   => 'date',
					default    => 'text',
				};
				$extra = match ($type) {
					'fwemail'  => ' autocomplete="email"',
					'fwtel'    => ' autocomplete="tel"',
					'fwnumber' => self::range($c),
					'fwtext'   => self::length($c) . (isset(self::AUTOCOMPLETE[$key]) ? ' autocomplete="' . self::AUTOCOMPLETE[$key] . '"' : ''),
					default    => '',
				};
				return $wrap('<label for="' . $id . '">' . $label . $star . '</label>' . "\n"
					. '<input type="' . $input . '"' . $attrs . ' value="' . esc($text, 'attr') . '"' . $extra . ($type !== 'fwdate' ? self::placeholder($c) : '') . '>' . "\n");
		}
	}

	private static function placeholder($c): string
	{
		$p = trim((string) $c->get('placeholder')->value());
		return $p !== '' ? ' placeholder="' . esc($p, 'attr') . '"' : '';
	}

	private static function length($c): string
	{
		$min = $c->get('minlength')->toInt();
		$max = $c->get('maxlength')->toInt();
		return ($min > 0 ? ' minlength="' . $min . '"' : '') . ($max > 0 ? ' maxlength="' . $max . '"' : '');
	}

	private static function range($c): string
	{
		$out = '';
		foreach (['min', 'max', 'step'] as $attr) {
			$v = $c->get($attr)->value();
			if ($v !== null && $v !== '' && is_numeric($v)) $out .= ' ' . $attr . '="' . $v . '"';
		}
		return $out;
	}

	/** The text of an error: "required", "email", "min:3" … */
	public static function error(string $code): string
	{
		[$name, $arg] = array_pad(explode(':', $code, 2), 2, '');
		return tt('formwizard.error.' . $name, null, ['value' => $arg]) ?? $code;
	}

	/** {{ key }} (also {key}) replaced by what was entered – $text is escaped already */
	public static function fill(string $text, array $values): string
	{
		return preg_replace_callback('/\{\{?\s*([a-z0-9_-]+)\s*\}?\}/i', fn ($m) => esc((string) ($values[$m[1]] ?? '')), $text);
	}

	/**
	 * The script for the conditions (once per page): a field shows while its
	 * condition is met, else it is hidden and not sent. And no sending twice.
	 */
	public static function script(): string
	{
		static $done = false;
		if ($done) return '';
		$done = true;
		$js = file_get_contents(dirname(__DIR__, 2) . '/assets/formwizard.js');
		return '<script>' . $js . '</script>' . "\n";
	}

	/* -------------- The block on its page --------------*/

	/** The block with this id on the page (also inside columns …): its content */
	public static function findBlock(Page $page, string $id): ?array
	{
		$found = null;
		$walk = function (array $list) use (&$walk, &$found, $id) {
			foreach ($list as $block) {
				if ($found !== null || !is_array($block)) continue;
				if (($block['id'] ?? '') === $id && ($block['type'] ?? '') === 'pwform') {
					$found = $block['content'] ?? [];
					return;
				}
				foreach ($block['content'] ?? [] as $value) {
					$inner = is_string($value) && str_starts_with(ltrim($value), '[{') ? json_decode($value, true) : (is_array($value) ? $value : null);
					if (is_array($inner) && array_is_list($inner)) $walk($inner);
				}
			}
		};
		foreach ($page->content()->toArray() as $value) {
			$list = is_string($value) && str_starts_with(ltrim($value), '[{') ? json_decode($value, true) : null;
			if (is_array($list)) $walk($list);
		}
		return $found;
	}

	/** The form a block shows: active forms only (a draft isn't shown) */
	public static function formOf($value): ?Page
	{
		$ref = is_array($value) ? ($value[0] ?? null) : $value;
		if (is_string($ref) && str_starts_with(ltrim($ref), '[')) {
			$ref = \Kirby\Data\Yaml::decode($ref)[0] ?? null;
		} elseif (is_string($ref) && str_starts_with(ltrim($ref), '- ')) {
			$ref = \Kirby\Data\Yaml::decode($ref)[0] ?? null;
		}
		$form = is_string($ref) && $ref !== '' ? kirby()->page($ref) : null;
		return $form instanceof FormwizardFormPage && $form->isListed() ? $form : null;
	}
}
