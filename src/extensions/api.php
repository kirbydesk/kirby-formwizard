<?php

/**
 * For the Panel: the fields of a form (the preview of the block)
 */
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
	],
];
