<?php

/**
 * The container of the forms, a form, and the field types (one block each)
 */
$groups = [];
foreach (fwForms::GROUPS as $group => $types) {
	$groups[$group] = [
		'label'     => 'formwizard.group.' . $group,
		'type'      => 'group',
		'fieldsets' => $types,
	];
}

$noOptions = [
	'changeSlug'     => false,
	'changeStatus'   => false,
	'changeTemplate' => false,
	'changeTitle'    => false,
	'delete'         => false,
	'duplicate'      => false,
	'move'           => false,
	'preview'        => false,
	'sort'           => false,
];

return array_merge([

	/* -------------- The block: a form on a page --------------*/
	// (lazy: kirby-pagewizard loads after this plugin)
	'blocks/pwform' => fn () => pwBlueprint::main('pwform', fn ($cfg) => [
		'name'          => 'formwizard.block.name',
		'icon'          => 'formwizard',
		'contentFields' => array_merge(
			pwBlueprint::stdContent($cfg, ['tagline', 'heading', 'editor']),
			[
				'form' => [
					'label'    => 'formwizard.block.form',
					'type'     => 'pages',
					'multiple' => false,
					'required' => true,
					'query'    => 'kirby.page("' . fwForms::slug() . '").children.listed',
					'empty'    => 'formwizard.block.form.empty',
					'help'     => 'formwizard.block.form.help',
					'image'    => ['icon' => 'formwizard', 'back' => 'transparent'],
					'info'     => '{{ page.fwInfo }}',
				],
				'successText' => [
					'label'       => 'formwizard.block.success',
					'type'        => 'textarea',
					'buttons'     => ['bold', 'italic', 'link'],
					'size'        => 'small',
					'placeholder' => 'formwizard.block.success.placeholder',
					'help'        => 'formwizard.block.success.help',
				],
			]
		),
	])(),

	/* -------------- Form Wizard › Forms --------------*/
	'pages/formwizard-forms' => fn () => [
		'title'    => 'formwizard.forms',
		'icon'     => 'formwizard',
		'options'  => $noOptions,
		'sections' => [
			'forms' => [
				'type'      => 'pages',
				'label'     => 'formwizard.forms',
				'templates' => ['formwizard-form'],
				'status'    => 'all',
				'layout'    => 'list',
				'info'      => '{{ page.fwInfo }}',
				'empty'     => 'formwizard.forms.empty',
				'image'     => ['icon' => 'formwizard', 'back' => 'transparent'],
				'sortBy'    => 'title asc',
			],
		],
	],

	/* -------------- A form --------------*/
	'pages/formwizard-form' => fn () => [
		'title'   => 'formwizard.form',
		'icon'    => 'formwizard',
		'num'     => 'zero',
		'options' => [
			'changeTemplate' => false,
			'move'           => false,
			'preview'        => false,
		],
		// (no page of the website: no "open"; its submissions instead)
		'buttons' => ['formwizard-submissions', 'settings', 'languages', 'status'],
		'create'  => [
			'title'  => ['label' => 'formwizard.form.name'],
			'status' => 'draft',
		],
		// draft: not offered in the blocks yet; active: offered and sends
		'status'  => [
			'draft'    => ['label' => 'formwizard.status.draft', 'text' => 'formwizard.status.draft.text'],
			'unlisted' => false,
			'listed'   => ['label' => 'formwizard.status.listed', 'text' => 'formwizard.status.listed.text'],
		],
		'tabs' => [
			'fields' => [
				'label'  => 'formwizard.tab.fields',
				'icon'   => 'formwizard',
				'fields' => [
					'fields' => [
						'label'     => 'formwizard.fields',
						'type'      => 'blocks',
						'fieldsets' => $groups,
						'empty'     => 'formwizard.fields.empty',
						'help'      => 'formwizard.fields.help',
					],
				],
			],
			'after' => [
				'label'  => 'formwizard.tab.after',
				'icon'   => 'check',
				'fields' => [
					'success' => [
						'label'     => 'formwizard.success',
						'type'      => 'toggles',
						'default'   => 'text',
						'translate' => false,
						'options'   => [
							['value' => 'text', 'text' => ['*' => 'formwizard.success.text']],
							['value' => 'page', 'text' => ['*' => 'formwizard.success.page']],
						],
					],
					'successText' => [
						'label'       => 'formwizard.success.text.label',
						'type'        => 'textarea',
						'buttons'     => ['bold', 'italic', 'link'],
						'size'        => 'small',
						'placeholder' => 'formwizard.success.text.default',
						'help'        => 'formwizard.success.text.help',
						'when'        => ['success' => 'text'],
					],
					'successPage' => [
						'label'    => 'formwizard.success.page.label',
						'type'     => 'pages',
						'multiple' => false,
						'required' => true,
						'when'     => ['success' => 'page'],
					],
				],
			],
			'settings' => [
				'label'  => 'formwizard.tab.settings',
				'icon'   => 'settings',
				'fields' => [
					'buttonText' => [
						'label'       => 'formwizard.button',
						'type'        => 'text',
						'placeholder' => 'formwizard.button.default',
						'width'       => '1/2',
					],
					'privacyPage' => [
						'label'     => 'formwizard.privacy',
						'type'      => 'pages',
						'multiple'  => false,
						'translate' => false,
						'help'      => 'formwizard.privacy.help',
						'width'     => '1/2',
					],
					'store' => [
						'label'     => 'formwizard.store',
						'type'      => 'toggle',
						'default'   => true,
						'translate' => false,
						'text'      => ['formwizard.store.off', 'formwizard.store.on'],
						'help'      => 'formwizard.store.help',
						'width'     => '1/2',
					],
					'closed' => [
						'label'     => 'formwizard.closed',
						'type'      => 'toggle',
						'translate' => false,
						'text'      => ['formwizard.closed.off', 'formwizard.closed.on'],
						'help'      => 'formwizard.closed.help',
						'width'     => '1/2',
					],
					'closedText' => [
						'label'       => 'formwizard.closed.text',
						'type'        => 'textarea',
						'buttons'     => ['bold', 'italic', 'link'],
						'size'        => 'small',
						'placeholder' => 'formwizard.closed.text.default',
						'when'        => ['closed' => true],
					],
				],
			],
		],
	],

], fwFields::blueprints());
