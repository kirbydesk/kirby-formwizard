<?php

return [
	// the container of the forms: made on the first visit of the Panel
	// (the path first: asking for the user would start a session on every page)
	'route:before' => function ($route, $path) {
		$slug = trim((string) option('panel.slug', 'panel'), '/');
		if ($path !== $slug && !str_starts_with((string) $path, $slug . '/')) return;
		if (kirby()->user()) fwForms::ensureContainer();
	},

	// the forms are edited in the Project Wizard, not as pages: a form opened
	// in the page view (an old link, the search) goes there
	'panel.route:before' => function ($route, $path) {
		$slug = fwForms::formOfPanelPath((string) $path);
		if ($slug === null) return;
		\Kirby\Panel\Panel::go('projectwizard?tab=forms&view=' . ($slug === '' ? 'settings' : 'form:' . rawurlencode($slug)));
	},
];
