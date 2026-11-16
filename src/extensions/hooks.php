<?php

return [
	// the container of the forms: made on the first visit of the Panel
	// (the path first: asking for the user would start a session on every page)
	'route:before' => function ($route, $path) {
		$slug = trim((string) option('panel.slug', 'panel'), '/');
		if ($path !== $slug && !str_starts_with((string) $path, $slug . '/')) return;
		if (kirby()->user()) fwForms::ensureContainer();
	},
];
