<?php

use Kirby\Panel\Panel;

/**
 * The Panel menu: "Form Wizard" with its entries "Forms" and "Submissions"
 * below it – those are only shown while one is in the Form Wizard.
 * The forms are pages (in the "site" area): the menu marks them as part of
 * the Form Wizard, not of the pages.
 */
$toForms = function () {
	fwForms::ensureContainer();
	Panel::go(fwForms::panelUrl());
};
$open = fn ($areas, $permissions, $current) => fwForms::inArea($current);

return [
	// the forms are pages, but don't mark "Pages" as current there
	'site' => fn () => [
		'current' => fn ($current) => $current === 'site' && !fwForms::inFormsView(),
	],

	'formwizard' => fn () => [
		'label'   => t('formwizard.name', 'Form Wizard'),
		'icon'    => 'formwizard',
		'link'    => 'formwizard',
		'menu'    => true,
		// (inside, the entries below it are marked)
		'current' => fn () => false,
		'views'   => [
			['pattern' => 'formwizard', 'action' => $toForms],
		],
	],

	'formwizard-forms' => fn () => [
		'label'   => t('formwizard.forms', 'Forms'),
		'icon'    => 'list-bullet',
		'link'    => 'formwizard/forms',
		'menu'    => $open,
		'current' => fn ($current) => $current === 'formwizard-forms' || fwForms::inFormsView(),
		'views'   => [
			['pattern' => 'formwizard/forms', 'action' => $toForms],
		],
	],

	'formwizard-submissions' => fn () => [
		'label'   => t('formwizard.submissions', 'Submissions'),
		'icon'    => 'email',
		'link'    => 'formwizard/submissions',
		'menu'    => $open,
		'views'   => [
			[
				'pattern' => 'formwizard/submissions',
				'action'  => fn () => [
					'component' => 'k-formwizard-submissions-view',
					'title'     => t('formwizard.submissions', 'Submissions'),
					'breadcrumb' => [
						['label' => t('formwizard.submissions', 'Submissions'), 'link' => 'formwizard/submissions'],
					],
				],
			],
		],
	],
];
