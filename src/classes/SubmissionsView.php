<?php

/**
 * A submission for the Panel views
 */
class fwSubmissionsView
{
	/** "Today, 09:41", "Yesterday, 16:12", "12.11.2026" */
	public static function date(string $iso): string
	{
		$time = strtotime($iso);
		if ($time === false) return '';
		if (date('Y-m-d', $time) === date('Y-m-d')) return t('formwizard.date.today') . ', ' . date('H:i', $time);
		if (date('Y-m-d', $time) === date('Y-m-d', strtotime('-1 day'))) return t('formwizard.date.yesterday') . ', ' . date('H:i', $time);
		return date('d.m.Y', $time);
	}

	public static function detail(array $record): array
	{
		$reply = null;
		foreach ($record['fields'] as $f) {
			if ($f['type'] === 'fwemail' && $f['display'] !== '') { $reply = $f['display']; break; }
		}
		return [
			'id'       => $record['id'],
			'title'    => $record['title'],
			'date'     => date('d.m.Y, H:i', strtotime($record['created'])),
			'status'   => $record['status'],
			'form'     => $record['formName'],
			'page'     => $record['page'] ?? null,
			'language' => $record['language'] ?? null,
			'reply'    => $reply,
			'fields'   => array_map(fn ($f) => [
				'label' => $f['label'],
				'type'  => $f['type'],
				'value' => $f['display'],
			], array_values(array_filter($record['fields'], fn ($f) => $f['display'] !== ''))),
			'files'    => $record['files'] ?? [],
			'log'      => $record['log'] ?? [],
		];
	}

	/** May the user read and change submissions? (the access to the area) */
	public static function allowed(): bool
	{
		$user = kirby()->user();
		return $user !== null && $user->role()->permissions()->for('access', 'formwizard-submissions') !== false;
	}
}
