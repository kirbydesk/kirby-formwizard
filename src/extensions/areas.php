<?php

use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Panel\Panel;

/**
 * The Panel menu: "Form Wizard" with its entries "Forms" and "Submissions"
 * below it – those are only shown while one is in the Form Wizard.
 * The forms are pages (in the "site" area): the menu marks them as part of
 * the Form Wizard, not of the pages. New submissions: a badge.
 */
$toForms = function () {
	fwForms::ensureContainer();
	Panel::go(fwForms::panelUrl());
};
$badge = fn () => ($n = fwSubmissions::countNew()) > 0 ? ['badge' => ['text' => (string) $n, 'theme' => 'notice']] : [];

return [
	// the forms are pages, but don't mark "Pages" as current there
	'site' => fn () => [
		'current' => fn ($current = null) => $current === 'site' && !fwForms::inFormsView(),
	],

	'formwizard' => fn () => [
		'label'   => t('formwizard.name', 'Form Wizard'),
		'icon'    => 'formwizard',
		'link'    => 'formwizard',
		// (closed: the badge here; open: at "Submissions")
		'menu'    => fn ($areas = [], $permissions = [], $current = null) => fwForms::inArea($current) ? true : ($badge() ?: true),
		'current' => fn () => false,
		'views'   => [
			['pattern' => 'formwizard', 'action' => $toForms],
		],
	],

	'formwizard-forms' => fn () => [
		'label'   => t('formwizard.forms', 'Forms'),
		'icon'    => 'list-bullet',
		'link'    => 'formwizard/forms',
		'menu'    => fn ($areas = [], $permissions = [], $current = null) => fwForms::inArea($current),
		'current' => fn ($current = null) => $current === 'formwizard-forms' || fwForms::inFormsView(),
		'views'   => [
			['pattern' => 'formwizard/forms', 'action' => $toForms],
		],
		// the form's own button: its submissions
		'buttons' => [
			'formwizard-submissions' => function (Page $page) {
				$count = fwSubmissions::count($page);
				$new   = fwSubmissions::countNew($page);
				return [
					'icon'  => 'email',
					'text'  => t('formwizard.submissions', 'Submissions') . ' (' . $count . ')',
					'link'  => 'formwizard/submissions?form=' . fwSubmissions::folder($page),
					'theme' => $new > 0 ? 'notice-icon' : null,
				];
			},
		],
	],

	'formwizard-submissions' => fn () => [
		'label'   => t('formwizard.submissions', 'Submissions'),
		'icon'    => 'email',
		'link'    => 'formwizard/submissions',
		'menu'    => fn ($areas = [], $permissions = [], $current = null) => fwForms::inArea($current) ? ($badge() ?: true) : false,
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

					return [
						'component'  => 'k-formwizard-submissions-view',
						'title'      => t('formwizard.submissions', 'Submissions'),
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
							'formLink'   => $form ? $form->panel()->url(true) : null,
						],
					];
				},
			],
		],
		// the CSV file of what is filtered (a link the browser downloads)
		'requests' => [
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
