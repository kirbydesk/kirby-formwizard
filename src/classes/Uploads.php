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
}
