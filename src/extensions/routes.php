<?php

return fn () => [
	// the forms are pages, but no pages of the website: their URLs answer
	// with the error page (404)
	[
		'pattern'  => [fwForms::slug(), fwForms::slug() . '/(:all)'],
		'language' => '*',
		'action'   => function () {
			return fwForms::container() ? site()->errorPage() : $this->next();
		},
	],

	// sending a form: the form posts here, the page shows the result after
	// the redirect (also without JavaScript); the language comes with it
	[
		'pattern' => 'formwizard/submit',
		'method'  => 'POST',
		'action'  => function () {
			$kirby = kirby();
			$data  = $kirby->request()->body()->toArray();
			if ($kirby->multilang() && ($lang = $kirby->language((string) ($data['language'] ?? '')))) {
				$kirby->setCurrentLanguage($lang->code());
				$kirby->setCurrentTranslation($lang->code());
			}
			$page    = page((string) ($data['page'] ?? ''));
			$id      = (string) ($data['block'] ?? '');
			$content = $page ? fwRender::findBlock($page, $id) : null;
			$form    = $content !== null ? fwRender::formOf($content['form'] ?? null) : null;
			if (!$page || $form === null || !csrf((string) ($data['csrf'] ?? ''))) {
				return go($page ? $page->url() : site()->url());
			}

			$result = fwSubmit::handle($page, $form, $data, fwSubmit::files());

			// sent: to the form's page of thanks, if it has one
			if ($result['status'] === 'sent' && $form->content()->get('success')->value() === 'page') {
				if ($target = $form->content()->get('successPage')->toPage()) return go($target->url());
			}
			$kirby->session()->set('formwizard.' . $id, $result);
			return go($page->url() . '#formwizard-' . $id);
		},
	],
];
