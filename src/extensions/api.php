<?php

use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;

/**
 * For the Panel: the fields of a form (the preview of the block); a
 * submission marked new/done, deleted
 */
$guard = function () {
	if (!fwSubmissionsView::allowed()) throw new PermissionException(message: 'Not allowed.');
};

return [
	'routes' => [
		/* -------------- The Project Wizard's tab "Forms" --------------*/
		[
			'pattern' => 'formwizard/pw/menu',
			'method'  => 'GET',
			'action'  => function () {
				fwEditor::guard();
				return fwEditor::menu();
			},
		],
		[
			'pattern' => 'formwizard/pw/forms',
			'method'  => 'GET',
			'action'  => function () {
				fwEditor::guard();
				return fwEditor::list();
			},
		],
		[
			'pattern' => 'formwizard/pw/forms',
			'method'  => 'POST',
			'action'  => function () {
				fwEditor::guard();
				$request = kirby()->request();
				return ['slug' => fwEditor::create((string) $request->get('title'), (string) $request->get('template', ''))];
			},
		],
		[
			'pattern' => 'formwizard/pw/forms/(:any)',
			'method'  => 'GET',
			'action'  => function (string $slug) {
				fwEditor::guard();
				return fwEditor::load($slug, get('language'));
			},
		],
		[
			'pattern' => 'formwizard/pw/forms/(:any)',
			'method'  => 'POST',
			'action'  => function (string $slug) {
				fwEditor::guard();
				$body = kirby()->request()->body();
				return fwEditor::save($slug, (array) $body->get('values', []), $body->get('title'), $body->get('language'));
			},
		],
		[
			'pattern' => 'formwizard/pw/forms/(:any)/title',
			'method'  => 'POST',
			'action'  => function (string $slug) {
				fwEditor::guard();
				$body = kirby()->request()->body();
				return ['title' => fwEditor::rename($slug, (string) $body->get('title'), $body->get('language'))];
			},
		],
		[
			'pattern' => 'formwizard/pw/forms/(:any)',
			'method'  => 'DELETE',
			'action'  => function (string $slug) {
				fwEditor::guard();
				fwEditor::delete($slug);
				return ['deleted' => $slug];
			},
		],
		[
			'pattern' => 'formwizard/pw/settings',
			'method'  => 'GET',
			'action'  => function () {
				fwEditor::guard();
				return fwSettings::forPanel();
			},
		],
		[
			'pattern' => 'formwizard/pw/settings',
			'method'  => 'POST',
			'action'  => function () {
				fwEditor::guard();
				return fwSettings::save(kirby()->request()->body()->toArray());
			},
		],

		/* -------------- The block's preview, the submissions --------------*/
		[
			'pattern' => 'formwizard/form',
			'method'  => 'GET',
			'action'  => function () {
				$ref  = (string) get('form', '');
				$form = $ref !== '' ? kirby()->page($ref) : null;
				if (!$form instanceof FormwizardFormPage) return ['form' => null];
				$fields = [];
				foreach (fwRender::fields($form) as $field) {
					if ($field['type'] === 'fwhidden') continue;
					$c = $field['block']->content();
					$select = $field['type'] === 'fwselect';
					$fields[] = [
						'type'     => $field['type'],
						'label'    => $field['label'],
						'required' => $field['required'],
						'width'    => $field['width'],
						// (boxes for radio and checkboxes; a dropdown shows its first entry when clicked)
						'options'  => $select ? [] : array_values($field['options'] ?? []),
						'select'   => $select,
						'choice'   => $select ? (string) (array_values($field['options'] ?? [])[0] ?? '') : '',
						'placeholder' => (string) $c->get('placeholder')->value() ?: ($select ? t('formwizard.field.placeholder.select') : ''),
						'help'     => (string) $c->get('help')->value(),
						'limits'   => $field['type'] === 'fwfile' ? fwUploads::limits($c) : '',
						// (the text without its formatting marks, as in the form's preview)
						'text'     => match ($field['type']) {
							'fwinfo'    => preg_replace('/[*_#]+/', '', (string) $c->get('text')->value()),
							// (a consent: its text as on the website, without the link)
							'fwconsent' => trim(strip_tags((string) $c->get('text')->value())) ?: ($c->get('privacy')->toPage() ? tt('formwizard.consent.link', null, ['link' => t('formwizard.consent.privacy')]) : t('formwizard.consent.default')),
							default     => '',
						},
						'when'     => $field['when'] !== null,
					];
				}
				return ['form' => [
					'title'  => $form->title()->value(),
					'status' => $form->status(),
					'button' => $form->content()->get('buttonText')->or(t('formwizard.button.default'))->value(),
					'buttonLayout' => fwRender::buttonLayout($form),
					'link'   => fwForms::editUrl($form),
					'fields' => $fields,
				]];
			},
		],
		[
			'pattern' => 'formwizard/submissions/(:any)/status',
			'method'  => 'POST',
			'action'  => function (string $id) use ($guard) {
				$guard();
				$record = fwSubmissions::setStatus($id, (string) kirby()->request()->get('status'));
				if ($record === null) throw new NotFoundException(message: 'Not found.');
				return ['status' => $record['status']];
			},
		],
		[
			'pattern' => 'formwizard/submissions/(:any)',
			'method'  => 'DELETE',
			'action'  => function (string $id) use ($guard) {
				$guard();
				if (!fwSubmissions::delete($id)) throw new NotFoundException(message: 'Not found.');
				return ['deleted' => $id];
			},
		],
	],
];
