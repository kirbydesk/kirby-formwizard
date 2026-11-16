<?php

use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

/**
 * The submissions: one JSON file each, in a folder per form –
 * content/.formwizard/submissions/<form>/<id>.json. Not reachable from
 * the web, not tied to a language, deleted after the retention period.
 *
 * A submission keeps the fields as they were when it was sent (label,
 * type, value, the text shown), so later changes to the form don't change
 * what arrived.
 */
class fwSubmissions
{
	/** An id: date, time and a random part – sortable, safe as a file name */
	private const ID = '/^\d{8}-\d{6}-[a-f0-9]{6}$/';

	public static function root(): string
	{
		return kirby()->root('content') . '/.formwizard/submissions';
	}

	/** The folder of a form: its UUID (without "page://"), else its slug */
	public static function folder(Page $form): string
	{
		$uuid = $form->uuid()?->id();
		return preg_replace('/[^a-z0-9_-]/i', '', $uuid ?: $form->slug());
	}

	/* -------------- Storing --------------*/

	/**
	 * A submission stored: the shown fields with their values. Returns it
	 * (with its id) – the actions use it, the files come in later.
	 */
	public static function store(Page $form, Page $page, array $fields, array $values): array
	{
		$entries = [];
		foreach ($fields as $key => $field) {
			if ($field['type'] === 'fwinfo' || !array_key_exists($key, $values)) continue;
			$value = $values[$key];
			$entries[] = [
				'key'     => $key,
				'label'   => $field['label'],
				'type'    => $field['type'],
				'value'   => $value,
				'display' => self::display($field, $value),
			];
		}

		$record = [
			'id'       => date('Ymd-His') . '-' . bin2hex(random_bytes(3)),
			'form'     => $form->uuid()?->toString() ?? $form->id(),
			'formId'   => self::folder($form),
			'formName' => $form->title()->value(),
			'created'  => date('c'),
			'status'   => 'new',
			'page'     => ['id' => $page->id(), 'title' => $page->title()->value(), 'url' => $page->url()],
			'language' => kirby()->language()?->code(),
			'fields'   => $entries,
			'files'    => [],
			'log'      => [],
		];
		$record['title']   = self::title($record);
		$record['summary'] = self::summary($record);

		if ($form->content()->get('store')->isEmpty() || $form->content()->get('store')->toBool()) {
			self::write($record);
		} else {
			$record['stored'] = false;
		}
		return $record;
	}

	/** The text of a value as shown: the options' texts, "yes" for a consent */
	public static function display(array $field, $value): string
	{
		if (is_array($value)) {
			return implode(', ', array_map(fn ($v) => $field['options'][$v] ?? $v, $value));
		}
		if (isset($field['options'])) return (string) ($field['options'][$value] ?? $value);
		if ($field['type'] === 'fwconsent') return $value === '1' ? '✓' : '';
		if ($field['type'] === 'fwdate' && $value !== '') {
			$d = \DateTime::createFromFormat('Y-m-d', $value);
			return $d ? $d->format('d.m.Y') : $value;
		}
		return (string) $value;
	}

	/** Who sent it: the field "name", else the first text field, else the e-mail */
	private static function title(array $record): string
	{
		$byKey = array_column($record['fields'], 'display', 'key');
		foreach (['name', 'vorname'] as $key) {
			if (!empty($byKey[$key])) {
				return trim($byKey[$key] . (($key === 'vorname' && !empty($byKey['nachname'])) ? ' ' . $byKey['nachname'] : ''));
			}
		}
		foreach ($record['fields'] as $f) if ($f['type'] === 'fwtext' && $f['display'] !== '') return $f['display'];
		foreach ($record['fields'] as $f) if ($f['type'] === 'fwemail' && $f['display'] !== '') return $f['display'];
		return '–';
	}

	/** What it is about: the first choice, else the start of the first long text */
	private static function summary(array $record): string
	{
		foreach ($record['fields'] as $f) {
			if (in_array($f['type'], ['fwselect', 'fwradio'], true) && $f['display'] !== '') return $f['display'];
		}
		foreach ($record['fields'] as $f) {
			if ($f['type'] === 'fwtextarea' && $f['display'] !== '') return \Kirby\Toolkit\Str::short(preg_replace('/\s+/', ' ', $f['display']), 60);
		}
		return '';
	}

	/* -------------- Reading and changing --------------*/

	private static function file(string $id, string $folder): string
	{
		return self::root() . '/' . $folder . '/' . $id . '.json';
	}

	public static function write(array $record): void
	{
		$file = self::file($record['id'], $record['formId']);
		Dir::make(dirname($file));
		F::write($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		self::forget();
	}

	/** All submissions, newest first */
	public static function all(): array
	{
		$list = [];
		foreach (glob(self::root() . '/*/*.json') ?: [] as $file) {
			$record = json_decode((string) file_get_contents($file), true);
			if (is_array($record) && preg_match(self::ID, $record['id'] ?? '')) $list[] = $record;
		}
		usort($list, fn ($a, $b) => strcmp($b['id'], $a['id']));
		return $list;
	}

	public static function find(string $id): ?array
	{
		if (!preg_match(self::ID, $id)) return null;
		$file = glob(self::root() . '/*/' . $id . '.json')[0] ?? null;
		$record = $file ? json_decode((string) file_get_contents($file), true) : null;
		return is_array($record) ? $record : null;
	}

	public static function setStatus(string $id, string $status): ?array
	{
		$record = self::find($id);
		if ($record === null || !in_array($status, ['new', 'done'], true)) return null;
		$record['status'] = $status;
		self::write($record);
		return $record;
	}

	/** A submission deleted, with its files */
	public static function delete(string $id): bool
	{
		$record = self::find($id);
		if ($record === null) return false;
		Dir::remove(self::root() . '/' . $record['formId'] . '/' . $id);
		F::remove(self::file($id, $record['formId']));
		self::forget();
		return true;
	}

	/* -------------- Counting (cached) --------------*/

	/** [form folder => [all, new]] */
	public static function counts(): array
	{
		$cache = kirby()->cache('kirbydesk.kirby-formwizard');
		$counts = $cache->get('counts');
		if (is_array($counts)) return $counts;
		$counts = [];
		foreach (self::all() as $record) {
			$counts[$record['formId']] ??= [0, 0];
			$counts[$record['formId']][0]++;
			if ($record['status'] === 'new') $counts[$record['formId']][1]++;
		}
		$cache->set('counts', $counts);
		return $counts;
	}

	public static function countNew(?Page $form = null): int
	{
		$counts = self::counts();
		if ($form) return $counts[self::folder($form)][1] ?? 0;
		return array_sum(array_column($counts, 1));
	}

	public static function count(?Page $form = null): int
	{
		$counts = self::counts();
		if ($form) return $counts[self::folder($form)][0] ?? 0;
		return array_sum(array_column($counts, 0));
	}

	private static function forget(): void
	{
		kirby()->cache('kirbydesk.kirby-formwizard')->remove('counts');
	}

	/* -------------- Retention --------------*/

	/** Days a submission is kept (0: for ever) */
	public static function retention(): int
	{
		// (Project Wizard › Forms › Settings, else the option)
		return fwSettings::get()['retention'];
	}

	/** Submissions older than the retention period deleted – at most once a day */
	public static function cleanup(bool $force = false): int
	{
		$days = self::retention();
		if ($days === 0) return 0;
		$cache = kirby()->cache('kirbydesk.kirby-formwizard');
		if (!$force && $cache->get('cleanup') === date('Y-m-d')) return 0;
		$cache->set('cleanup', date('Y-m-d'));

		$limit = date('Ymd', strtotime('-' . $days . ' days'));
		$removed = 0;
		foreach (self::all() as $record) {
			if (substr($record['id'], 0, 8) < $limit && self::delete($record['id'])) $removed++;
		}
		return $removed;
	}

	/* -------------- Export --------------*/

	/** The submissions as CSV (semicolons and UTF-8 with BOM: opens in Excel) */
	public static function csv(array $records): string
	{
		// the columns: every field key in the order met, after date, form, status
		$columns = [];
		foreach ($records as $record) {
			foreach ($record['fields'] as $f) $columns[$f['key']] ??= $f['label'];
		}
		$rows = [array_merge([t('formwizard.submission.date'), t('formwizard.form'), t('formwizard.submission.status'), t('formwizard.submission.page')], array_values($columns))];
		foreach ($records as $record) {
			$byKey = array_column($record['fields'], 'display', 'key');
			$row = [
				date('d.m.Y H:i', strtotime($record['created'])),
				$record['formName'],
				t('formwizard.status.' . $record['status']),
				$record['page']['url'] ?? '',
			];
			foreach (array_keys($columns) as $key) $row[] = $byKey[$key] ?? '';
			$rows[] = $row;
		}
		$out = fopen('php://temp', 'r+');
		foreach ($rows as $row) {
			// (a value starting with = + - @ is no formula in the spreadsheet)
			fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $row), ';', '"', '\\');
		}
		rewind($out);
		return "\xEF\xBB\xBF" . stream_get_contents($out);
	}
}
