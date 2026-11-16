<?php

/**
 * The forms are pages, but no pages of the website: their URLs answer
 * with the error page (404)
 */
return fn () => [
	[
		'pattern'  => [fwForms::slug(), fwForms::slug() . '/(:all)'],
		'language' => '*',
		'action'   => function () {
			return fwForms::container() ? site()->errorPage() : $this->next();
		},
	],
];
