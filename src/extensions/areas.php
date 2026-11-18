<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Panel\Panel;

/**
 * The Panel menu: each form that stores its submissions, by its name – for
 * everyone who reads them, also without the Project Wizard (the forms are
 * built there, in its tab "Forms"). New submissions: a badge. The views
 * (all submissions, filtered by form; one submission) in one area without
 * an entry of its own.
 */
$badge = fn (Page $form) => ($n = fwSubmissions::countNew($form)) > 0 ? ['badge' => ['text' => (string) $n, 'theme' => 'notice']] : [];

// (the form of the submissions shown: the list's filter or the one opened)
$shownFolder = function (): ?string {
	$request = kirby()->request();
	if (($folder = (string) $request->get('form', '')) !== '') return $folder;
	if (preg_match('#formwizard/submissions/([^/]+)$#', $request->path()->toString(), $m)) return fwSubmissions::find($m[1])['formId'] ?? null;
	return null;
};

$areas = [];
foreach (fwForms::scannedFolders() as $folder) {
	$areas['formwizard-form-' . strtolower($folder)] = fn () => [
		'label'   => ($form = fwForms::byFolder($folder)) ? $form->title()->value() : t('formwizard.submissions', 'Submissions'),
		'icon'    => 'email',
		'link'    => 'formwizard/submissions?form=' . rawurlencode($folder),
		'menu'    => function () use ($folder, $badge) {
			$form = fwForms::byFolder($folder);
			if (!$form instanceof FormwizardFormPage || !$form->fwStores() || !fwSubmissionsView::allowed()) return false;
			return $badge($form) ?: true;
		},
		'current' => fn ($current = null) => $current === 'formwizard-submissions' && $shownFolder() === $folder,
	];
}

return $areas + [
	'formwizard-submissions' => fn () => [
		'label'   => t('formwizard.submissions', 'Submissions'),
		// (above the list and a submission: the name of their form)
		'breadcrumbLabel' => ($folder = $shownFolder()) && ($form = fwForms::byFolder($folder)) ? $form->title()->value() : t('formwizard.submissions', 'Submissions'),
		'icon'    => 'email',
		'link'    => 'formwizard/submissions',
		'menu'    => false,
		'views'   => [
			// all submissions: filtered by form, status, a search; 30 a page
			[
				'pattern' => 'formwizard/submissions',
				'action'  => function () {
					fwSubmissions::cleanup();
					$request = kirby()->request();
					$filter  = [
						'form'   => (string) $request->get('form', ''),
						'status' => in_array($request->get('status'), ['new', 'done'], true) ? $request->get('status') : '',
						'q'      => trim((string) $request->get('q', '')),
					];
					$all   = fwSubmissions::all();
					$items = array_values(array_filter($all, function ($r) use ($filter) {
						if ($filter['form'] !== '' && $r['formId'] !== $filter['form']) return false;
						if ($filter['status'] !== '' && $r['status'] !== $filter['status']) return false;
						if ($filter['q'] !== '') {
							$haystack = mb_strtolower($r['title'] . ' ' . $r['summary'] . ' ' . implode(' ', array_column($r['fields'], 'display')));
							if (!str_contains($haystack, mb_strtolower($filter['q']))) return false;
						}
						return true;
					}));

					// the forms to filter by: the existing ones, then those only submissions know
					$forms = [];
					foreach (fwForms::forms() as $form) $forms[fwSubmissions::folder($form)] = $form->title()->value();
					foreach ($all as $r) $forms[$r['formId']] ??= $r['formName'];

					$limit = 30;
					$total = count($items);
					$page  = max(1, min((int) $request->get('page', 1), (int) ceil(max($total, 1) / $limit)));
					$items = array_slice($items, ($page - 1) * $limit, $limit);

					$name = $filter['form'] !== '' ? ($forms[$filter['form']] ?? null) : null;
					return [
						'component'  => 'k-formwizard-submissions-view',
						'title'      => $name ?? t('formwizard.submissions', 'Submissions'),
						'props'      => [
							'items' => array_map(fn ($r) => [
								'id'      => $r['id'],
								'date'    => fwSubmissionsView::date($r['created']),
								'title'   => $r['title'],
								'form'    => $r['formName'],
								'summary' => $r['summary'],
								'status'  => $r['status'],
								'files'   => count($r['files'] ?? []),
							], $items),
							'forms'      => array_map(fn ($id, $name) => ['value' => $id, 'text' => $name], array_keys($forms), $forms),
							'filter'     => $filter,
							// (one form's: its name as heading, no choice of form)
							'formName'   => $name,
							'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
							'retention'  => fwSubmissions::retention(),
							'csv'        => Panel::url('formwizard/submissions.csv') . ($filter['form'] !== '' ? '?form=' . rawurlencode($filter['form']) : ''),
						],
					];
				},
			],
			// one submission
			[
				'pattern' => 'formwizard/submissions/(:any)',
				'action'  => function (string $id) {
					$record = fwSubmissions::find($id);
					if ($record === null) Panel::go('formwizard/submissions');
					$form = kirby()->page($record['form']);
					return [
						'component'  => 'k-formwizard-submission-view',
						'title'      => $record['title'],
						'breadcrumb' => [['label' => $record['title'], 'link' => 'formwizard/submissions/' . $id]],
						'props'      => [
							'submission' => fwSubmissionsView::detail($record),
							// back to its form's list
							'back'       => ['link' => 'formwizard/submissions?form=' . rawurlencode($record['formId']), 'text' => $form instanceof Page ? $form->title()->value() : $record['formName']],
							'formLink'   => $form instanceof FormwizardFormPage ? fwForms::editUrl($form) : null,
						],
					];
				},
			],
		],
		// the CSV file of what is filtered (a link the browser downloads);
		// an uploaded file of a submission (only through the Panel)
		'requests' => [
			'formwizard/submissions/file' => [
				'pattern' => 'formwizard/submissions/(:any)/files/(:any)',
				'action'  => function (string $id, string $name) {
					$record = fwSubmissions::find($id);
					$name   = rawurldecode($name);
					foreach ($record['files'] ?? [] as $file) {
						if ($file['name'] !== $name) continue;
						$path = fwSubmissions::root() . '/' . $record['formId'] . '/' . $id . '/' . $file['name'];
						if (is_file($path)) return Response::download($path, $file['name']);
					}
					return new Response('Not found', 'text/plain', 404);
				},
			],
			'formwizard/submissions.csv' => [
				'pattern' => 'formwizard/submissions.csv',
				'action'  => function () {
					$form = (string) kirby()->request()->get('form', '');
					$records = array_values(array_filter(fwSubmissions::all(), fn ($r) => $form === '' || $r['formId'] === $form));
					$name = 'submissions-' . ($records[0]['formName'] ?? 'all') . '-' . date('Y-m-d');
					return new Response(fwSubmissions::csv($records), 'text/csv', 200, [
						'Content-Disposition' => 'attachment; filename="' . \Kirby\Toolkit\Str::slug($name) . '.csv"',
						'Cache-Control'       => 'no-store',
					]);
				},
			],
		],
	],
];
