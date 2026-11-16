<?php

use Kirby\Cms\Page;
use Kirby\Toolkit\V;

/**
 * Sending a form: the values checked against its fields (only the fields
 * whose condition is met), then handed on. Returns the result for the page
 * after the redirect: ['status' => sent|invalid|failed, 'errors', 'values'].
 */
class fwSubmit
{
	public static function handle(Page $page, Page $form, array $data, array $files = []): array
	{
		$fields = fwRender::fields($form);
		$input  = is_array($data['fw'] ?? null) ? $data['fw'] : [];

		// the values as entered (trimmed; several options as a list)
		$values = [];
		foreach ($fields as $key => $field) {
			if ($field['type'] === 'fwinfo' || $field['type'] === 'fwfile') continue;
			$raw = $input[$key] ?? null;
			$values[$key] = $field['type'] === 'fwcheckboxes'
				? array_values(array_filter(array_map(fn ($v) => trim((string) $v), is_array($raw) ? $raw : []), fn ($v) => $v !== ''))
				: (is_array($raw) ? '' : trim((string) $raw));
		}

		// hidden fields: the page and a fixed value from the server, not from the form
		foreach ($fields as $key => $field) {
			if ($field['type'] !== 'fwhidden') continue;
			$c = $field['block']->content();
			$source = $c->get('source')->value() ?: 'page';
			if ($source === 'page') $values[$key] = $page->url();
			if ($source === 'fixed') $values[$key] = (string) $c->get('fixed')->value();
			if ($source === 'param') $values[$key] = mb_substr($values[$key] ?? '', 0, 200);
		}

		// spam gets "sent" without anything happening
		if (fwSpam::isSpam($data)) return ['status' => 'sent'];
		if ($form->content()->get('closed')->toBool()) return ['status' => 'closed'];

		$errors = [];
		$shown  = [];
		foreach ($fields as $key => $field) {
			if ($field['type'] === 'fwinfo') continue;
			if (!fwRender::visible($field, $fields, $values)) {
				unset($values[$key]);
				continue;
			}
			$shown[$key] = $field;
			$error = $field['type'] === 'fwfile'
				? self::checkFiles($field, $files[$key] ?? [])
				: self::check($field, $values[$key] ?? '');
			if ($error !== null) $errors[$key] = $error;
		}

		// (the values of a hidden field, the files: not back into the form)
		$back = array_filter($values, fn ($k) => ($fields[$k]['type'] ?? '') !== 'fwhidden', ARRAY_FILTER_USE_KEY);
		if ($errors !== []) return ['status' => 'invalid', 'errors' => $errors, 'values' => $back];

		// the uploads of the fields shown: their names as the field's value
		$uploads = [];
		foreach ($shown as $key => $field) {
			if ($field['type'] !== 'fwfile') continue;
			$list = array_values(array_filter($files[$key] ?? [], fn ($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK));
			$values[$key] = array_map(fn ($f) => fwUploads::safeName($f['name']), $list);
			if ($list !== []) $uploads[$key] = $list;
		}

		try {
			kirby()->trigger('formwizard.submit:before', ['form' => $form, 'page' => $page, 'values' => $values]);
			// stored (unless the form says no), the files put in place, then
			// the mails and the webhook – their results in the submission's log
			$submission = fwSubmissions::store($form, $page, $shown, $values);
			$submission['files'] = fwUploads::keep($form, $submission, $uploads);
			$submission = fwActions::run($form, $page, $submission);
			$submission['files'] = fwUploads::finish($submission['files']);
			$team = $submission['team'];
			unset($submission['team']);
			if ($submission['stored'] ?? true) fwSubmissions::write($submission);
			kirby()->trigger('formwizard.submit:after', ['form' => $form, 'page' => $page, 'submission' => $submission]);
		} catch (\Throwable $e) {
			kirby()->trigger('formwizard.failed', ['form' => $form, 'error' => $e->getMessage()]);
			return ['status' => 'failed', 'values' => $back];
		}
		// the team's mail failed and nothing was stored: the request would be lost
		if ($team === false && ($submission['stored'] ?? true) === false) {
			return ['status' => 'failed', 'values' => $back];
		}
		return ['status' => 'sent', 'values' => self::forThanks($values)];
	}

	/** An answer checked: null when fine, else the error ("required", "email", "min:3" …) */
	public static function check(array $field, $value): ?string
	{
		$c     = $field['block']->content();
		$empty = is_array($value) ? $value === [] : $value === '';
		if ($empty) return $field['required'] ? ($field['type'] === 'fwconsent' ? 'consent' : 'required') : null;

		switch ($field['type']) {
			case 'fwemail':
				return V::email($value) ? null : 'email';
			case 'fwtel':
				return preg_match('/^[0-9 +()\/.\-]{3,30}$/', $value) ? null : 'tel';
			case 'fwnumber':
				if (!is_numeric(str_replace(',', '.', $value))) return 'number';
				$n   = (float) str_replace(',', '.', $value);
				$min = $c->get('min')->value();
				$max = $c->get('max')->value();
				if (is_numeric($min) && $n < (float) $min) return 'min:' . $min;
				if (is_numeric($max) && $n > (float) $max) return 'max:' . $max;
				return null;
			case 'fwdate':
				$d = \DateTime::createFromFormat('Y-m-d', $value);
				return $d && $d->format('Y-m-d') === $value ? null : 'date';
			case 'fwtext':
			case 'fwtextarea':
				$len = mb_strlen($value);
				$min = $c->get('minlength')->toInt();
				$max = $c->get('maxlength')->toInt();
				if ($min > 0 && $len < $min) return 'minlength:' . $min;
				if ($max > 0 && $len > $max) return 'maxlength:' . $max;
				return null;
			case 'fwselect':
			case 'fwradio':
				return isset($field['options'][$value]) ? null : 'option';
			case 'fwcheckboxes':
				return array_diff($value, array_map('strval', array_keys($field['options']))) === [] ? null : 'option';
			case 'fwconsent':
				return $value === '1' ? null : 'consent';
		}
		return null;
	}

	/** The files of an upload field checked (size, type) – step 8 stores them */
	public static function checkFiles(array $field, array $files): ?string
	{
		$files = array_values(array_filter($files, fn ($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
		if ($files === []) return $field['required'] ? 'required' : null;

		$c = $field['block']->content();
		if (count($files) > 1 && !$c->get('multiple')->toBool()) return 'file';
		$max  = fwUploads::maxBytes($c);
		$mime = [];
		foreach (fwUploads::groups($c) as $group) $mime = array_merge($mime, fwUploads::GROUPS[$group]['mime']);

		foreach ($files as $file) {
			if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > $max) {
				return 'filesize:' . round($max / 1024 / 1024, 1) . ' MB';
			}
			if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return 'file';
			$type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
			if (!in_array($type, $mime, true)) return 'filetype';
		}
		return null;
	}

	/** The uploads by field: [key => [file, file …]] from PHP's $_FILES['fwfiles'] */
	public static function files(): array
	{
		$raw = $_FILES['fwfiles'] ?? null;
		if (!is_array($raw) || !is_array($raw['name'] ?? null)) return [];
		$list = [];
		foreach ($raw['name'] as $key => $names) {
			foreach ((array) $names as $i => $name) {
				$list[$key][] = [
					'name'     => (string) $name,
					'type'     => (string) ($raw['type'][$key][$i] ?? ''),
					'tmp_name' => (string) ($raw['tmp_name'][$key][$i] ?? ''),
					'error'    => (int) ($raw['error'][$key][$i] ?? UPLOAD_ERR_NO_FILE),
					'size'     => (int) ($raw['size'][$key][$i] ?? 0),
				];
			}
		}
		return $list;
	}

	/** For {{ key }} in the thanks: the short values only (no message text kept in the session) */
	private static function forThanks(array $values): array
	{
		return array_filter(
			array_map(fn ($v) => is_array($v) ? implode(', ', $v) : $v, $values),
			fn ($v) => mb_strlen($v) <= 80
		);
	}
}
