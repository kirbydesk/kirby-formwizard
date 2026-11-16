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
}
