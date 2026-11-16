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
					'link'   => $form->panel()->url(true),
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
