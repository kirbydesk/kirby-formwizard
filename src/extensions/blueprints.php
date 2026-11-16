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

					// the mail to the team
					'headlineTeam' => [
						'label' => 'formwizard.team',
						'type'  => 'headline',
					],
					'mailTeam' => [
						'label'     => 'formwizard.team.send',
						'type'      => 'toggle',
						'default'   => true,
						'translate' => false,
						'text'      => ['formwizard.off', 'formwizard.on'],
						'width'     => '1/3',
					],
					'recipients' => [
						'label'       => 'formwizard.team.recipients',
						'type'        => 'text',
						'translate'   => false,
						'placeholder' => 'formwizard.team.recipients.placeholder',
						'help'        => 'formwizard.team.recipients.help',
						'width'       => '2/3',
						'when'        => ['mailTeam' => true],
					],
					'routes' => [
						'label'     => 'formwizard.team.routes',
						'type'      => 'structure',
						'translate' => false,
						'help'      => 'formwizard.team.routes.help',
						'when'      => ['mailTeam' => true],
						'columns'   => ['when' => ['width' => '1/2'], 'to' => ['width' => '1/2']],
						'fields'    => [
							'when' => [
								'label'    => 'formwizard.team.routes.when',
								'type'     => 'select',
								'required' => true,
								'options'  => ['type' => 'query', 'query' => 'page.fwConditions'],
							],
							'to' => [
								'label'       => 'formwizard.team.routes.to',
								'type'        => 'text',
								'required'    => true,
								'placeholder' => 'vertrieb@…',
							],
						],
					],
					'mailSubject' => [
						'label'       => 'formwizard.team.subject',
						'type'        => 'text',
						'placeholder' => 'formwizard.team.subject.placeholder',
						'help'        => 'formwizard.placeholders.help',
						'when'        => ['mailTeam' => true],
					],

					// the confirmation to the sender
					'headlineConfirm' => [
						'label' => 'formwizard.confirm',
						'type'  => 'headline',
					],
					'confirm' => [
						'label'     => 'formwizard.confirm.send',
						'type'      => 'toggle',
						'translate' => false,
						'text'      => ['formwizard.off', 'formwizard.on'],
						'help'      => 'formwizard.confirm.help',
						'width'     => '1/3',
					],
					'confirmCopy' => [
						'label'     => 'formwizard.confirm.copy',
						'type'      => 'toggle',
						'default'   => true,
						'translate' => false,
						'text'      => ['formwizard.off', 'formwizard.on'],
						'width'     => '2/3',
						'when'      => ['confirm' => true],
					],
					'confirmSubject' => [
						'label'       => 'formwizard.confirm.subject',
						'type'        => 'text',
						'placeholder' => 'formwizard.confirm.subject.placeholder',
						'when'        => ['confirm' => true],
					],
					'confirmText' => [
						'label'       => 'formwizard.confirm.text',
						'type'        => 'textarea',
						'buttons'     => false,
						'size'        => 'small',
						'placeholder' => 'formwizard.confirm.text.default',
						'help'        => 'formwizard.placeholders.help',
						'when'        => ['confirm' => true],
					],

					// a webhook (CRM, Zapier, n8n …)
					'headlineWebhook' => [
						'label' => 'formwizard.webhook',
						'type'  => 'headline',
					],
					'webhook' => [
						'label'     => 'formwizard.webhook.send',
						'type'      => 'toggle',
						'translate' => false,
						'text'      => ['formwizard.off', 'formwizard.on'],
						'width'     => '1/3',
					],
					'webhookUrl' => [
						'label'     => 'formwizard.webhook.url',
						'type'      => 'url',
						'translate' => false,
						'required'  => true,
						'help'      => 'formwizard.webhook.help',
						'width'     => '2/3',
						'when'      => ['webhook' => true],
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
