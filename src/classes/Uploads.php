<?php

/**
 * Uploads of a form: the groups of file types a file field accepts
 */
class fwUploads
{
	/** The groups of file types: the MIME types and the extensions for the browser's picker */
	public const GROUPS = [
		'documents' => [
			'mime' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'text/plain'],
			'ext'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt'],
		],
		'images' => [
			'mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic'],
			'ext'  => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic'],
		],
		'archives' => [
			'mime' => ['application/zip', 'application/x-zip-compressed'],
			'ext'  => ['zip'],
		],
		'audio' => [
			'mime' => ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm'],
			'ext'  => ['mp3', 'm4a', 'ogg', 'wav', 'webm'],
		],
		'video' => [
			'mime' => ['video/mp4', 'video/quicktime', 'video/webm'],
			'ext'  => ['mp4', 'mov', 'webm'],
		],
	];

	/** The groups a file field accepts (all when none is chosen) */
	public static function groups($content): array
	{
		$chosen = $content->get('accept')->split(',');
		$chosen = array_values(array_intersect($chosen, array_keys(self::GROUPS)));
		return $chosen !== [] ? $chosen : array_keys(self::GROUPS);
	}

	/** For the browser's file picker: ".pdf,.docx,…" */
	public static function accept($content): string
	{
		$ext = [];
		foreach (self::groups($content) as $group) {
			foreach (self::GROUPS[$group]['ext'] as $e) $ext[] = '.' . $e;
		}
		return implode(',', array_unique($ext));
	}

	/** The largest file in bytes (the field's setting, at most what PHP takes) */
	public static function maxBytes($content): int
	{
		$mb = $content->get('maxsize')->toInt() ?: 10;
		return min($mb * 1024 * 1024, self::phpLimit());
	}

	/** PHP's own limit for an upload */
	public static function phpLimit(): int
	{
		$bytes = fn (string $v) => (int) $v * match (strtolower(substr(trim($v), -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
		$limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);
		return $limits ? min($limits) : PHP_INT_MAX;
	}

	/** "PDF, DOCX … · max. 10 MB" for the website */
	public static function limits($content): string
	{
		$ext = [];
		foreach (self::groups($content) as $group) {
			foreach (array_slice(self::GROUPS[$group]['ext'], 0, 3) as $e) $ext[] = strtoupper($e);
		}
		$mb = round(self::maxBytes($content) / 1024 / 1024, 1);
		return implode(', ', array_unique($ext)) . ' · ' . tt('formwizard.upload.max', null, ['size' => str_replace('.0', '', (string) $mb) . ' MB']);
	}

	/* -------------- Keeping and attaching --------------*/

	/** A safe file name: letters, digits, dashes – the extension kept (lowercase) */
	public static function safeName(string $name): string
	{
		$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$base = \Kirby\Toolkit\Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
		return substr($base, 0, 80) . ($ext !== '' && preg_match('/^[a-z0-9]{1,8}$/', $ext) ? '.' . $ext : '');
	}

	/**
	 * The uploads of a submission put in place: stored in its folder
	 * (content/.formwizard/submissions/<form>/<id>/) or in the temp folder
	 * for the mail only. Returns the entries: field, name, file, size, type,
	 * stored, path (for the mail; dropped before the submission is written).
	 */
	public static function keep(\Kirby\Cms\Page $form, array $submission, array $uploads): array
	{
		$stored = $submission['stored'] ?? true;
		$dir    = $stored
			? fwSubmissions::root() . '/' . $submission['formId'] . '/' . $submission['id']
			: (kirby()->root('cache') ?? sys_get_temp_dir()) . '/formwizard-' . $submission['id'];
		$entries = [];
		$names   = [];
		foreach ($uploads as $key => $files) {
			foreach ($files as $file) {
				$name = self::safeName($file['name']);
				for ($n = 2; isset($names[$name]); $n++) $name = pathinfo(self::safeName($file['name']), PATHINFO_FILENAME) . '-' . $n . '.' . pathinfo($name, PATHINFO_EXTENSION);
				$names[$name] = true;
				\Kirby\Filesystem\Dir::make($dir);
				$path = $dir . '/' . $name;
				if (!move_uploaded_file($file['tmp_name'], $path) && !(is_file($file['tmp_name']) && rename($file['tmp_name'], $path))) continue;
				$entries[] = [
					'field'  => $key,
					'name'   => $name,
					'size'   => filesize($path),
					'type'   => (new \finfo(FILEINFO_MIME_TYPE))->file($path),
					'stored' => $stored,
					'path'   => $path,
				];
			}
		}
		return $entries;
	}

	/** After the mails: the files not stored removed, the paths dropped from the entries */
	public static function finish(array $entries): array
	{
		$dirs = [];
		foreach ($entries as $i => $entry) {
			if (!$entry['stored']) {
				@unlink($entry['path']);
				$dirs[dirname($entry['path'])] = true;
				unset($entries[$i]);
				continue;
			}
			unset($entries[$i]['path']);
		}
		foreach (array_keys($dirs) as $dir) @rmdir($dir);
		return array_values($entries);
	}

	/** "412 KB", "1.2 MB" */
	public static function size(int $bytes): string
	{
		return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
	}
}
