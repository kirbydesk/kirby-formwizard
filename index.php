<?php

require_once __DIR__ . '/src/classes/Forms.php';
require_once __DIR__ . '/src/classes/Fields.php';
require_once __DIR__ . '/src/classes/Uploads.php';
require_once __DIR__ . '/src/classes/FormPage.php';

Kirby::plugin('kirbydesk/kirby-formwizard', [
	'options' => [
		// the slug of the page holding the forms (made on the first visit of the Panel)
		'slug' => 'forms',
	],

	/* -------------- Extensions --------------*/
	'areas'        => require __DIR__ . '/src/extensions/areas.php',
	'blueprints'   => require __DIR__ . '/src/extensions/blueprints.php',
	'hooks'        => require __DIR__ . '/src/extensions/hooks.php',
	'routes'       => require __DIR__ . '/src/extensions/routes.php',
	'translations' => require __DIR__ . '/src/extensions/translations.php',
	'pageModels'   => [
		'formwizard-form' => FormwizardFormPage::class,
	],
]);
