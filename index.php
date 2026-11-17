<?php

// Ensure pwConfig is available when plugins are loaded in alphabetical order
// (manual installation has no composer autoloader – load order depends on filesystem)
if (!class_exists('pwConfig')) {
	$pagewizardConfig = __DIR__ . '/../kirby-pagewizard/src/helpers/blocks/config.php';
	if (file_exists($pagewizardConfig)) {
		require_once $pagewizardConfig;
	}
}

require_once __DIR__ . '/src/classes/Forms.php';
require_once __DIR__ . '/src/classes/Fields.php';
require_once __DIR__ . '/src/classes/Uploads.php';
require_once __DIR__ . '/src/classes/Spam.php';
require_once __DIR__ . '/src/classes/Render.php';
require_once __DIR__ . '/src/classes/Submit.php';
require_once __DIR__ . '/src/classes/Submissions.php';
require_once __DIR__ . '/src/classes/SubmissionsView.php';
require_once __DIR__ . '/src/classes/Editor.php';
require_once __DIR__ . '/src/classes/Actions.php';
require_once __DIR__ . '/src/classes/Settings.php';
require_once __DIR__ . '/src/classes/Captcha.php';
require_once __DIR__ . '/src/classes/Templates.php';
require_once __DIR__ . '/src/classes/FormPage.php';

// the block: a form on a page
if (class_exists('pwConfig')) pwConfig::register('pwform', __DIR__ . '/src/config');

Kirby::plugin('kirbydesk/kirby-formwizard', [
	'options' => [
		// the slug of the page holding the forms (made on the first visit of the Panel)
		'slug' => 'forms',
		// days a submission is kept (0: for ever)
		'retention' => 180,
		// (the counts of the submissions)
		'cache' => true,
		// sends mails: the Project Wizard shows Settings › Sending
		'mail' => true,
		// the tab "Forms" in the Project Wizard's header (the forms are built there)
		'projectwizard' => fn () => [
			'key'       => 'forms',
			'label'     => t('formwizard.forms', 'Forms'),
			'icon'      => 'formwizard',
			'component' => 'k-formwizard-pw',
			'menu'      => 'formwizard/pw/menu',
			'badge'     => fwSubmissions::countNew(),
		],
	],

	/* -------------- Extensions --------------*/
	'api'          => require __DIR__ . '/src/extensions/api.php',
	'areas'        => require __DIR__ . '/src/extensions/areas.php',
	'blueprints'   => require __DIR__ . '/src/extensions/blueprints.php',
	'hooks'        => require __DIR__ . '/src/extensions/hooks.php',
	'routes'       => require __DIR__ . '/src/extensions/routes.php',
	'snippets'     => [
		'blocks/pwform' => __DIR__ . '/snippets/index.php',
	],
	'translations' => require __DIR__ . '/src/extensions/translations.php',
	'pageModels'   => [
		'formwizard-form' => FormwizardFormPage::class,
	],
]);
