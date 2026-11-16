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
				return ['slug' => fwEditor::create((string) kirby()->request()->get('title'))];
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
			'pattern' => 'formwizard/pw/forms/(:any)',
			'method'  => 'DELETE',
			'action'  => function (string $slug) {
				fwEditor::guard();
				fwEditor::delete($slug);
				return ['deleted' => $slug];
			},
		],
		[
			'pattern' => 'formwizard/pw/forms/(:any)/status',
			'method'  => 'POST',
			'action'  => function (string $slug) {
				fwEditor::guard();
				return fwEditor::setStatus($slug, (string) kirby()->request()->get('status'));
			},
		],
		[
			'pattern' => 'formwizard/pw/settings',
			'method'  => 'GET',
			'action'  => function () {
				fwEditor::guard();
				return fwEditor::settings();
			},
		],
		[
			'pattern' => 'formwizard/pw/settings',
			'method'  => 'POST',
			'action'  => function () {
				fwEditor::guard();
				return fwEditor::saveSettings(kirby()->request()->body()->toArray());
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
					$fields[] = [
						'type'     => $field['type'],
						'label'    => $field['label'],
						'required' => $field['required'],
						'width'    => $field['width'],
						'options'  => array_values($field['options'] ?? []),
						'when'     => $field['when'] !== null,
					];
				}
				return ['form' => [
					'title'  => $form->title()->value(),
					'status' => $form->status(),
					'closed' => $form->content()->get('closed')->toBool(),
					'button' => $form->content()->get('buttonText')->or(t('formwizard.button.default'))->value(),
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
