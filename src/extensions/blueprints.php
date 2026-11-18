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
					// (only the forms' names: no count, no way into subpages)
					'subpages' => false,
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
			'status' => 'listed',
		],
		// (no drafts any more: a form is offered in the blocks right away)
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
					],
					// below the fields: the captcha, the send button (in the
					// Project Wizard each a fixed row of the list, see Forms.vue)
					'captcha' => [
						'label'     => 'formwizard.captcha.form',
						'type'      => 'toggle',
						'default'   => true,
						'translate' => false,
						'text'      => ['formwizard.off', 'formwizard.on'],
						'width'     => '1/2',
					],
					'buttonText' => [
						'label'       => 'formwizard.button',
						'type'        => 'text',
						'placeholder' => 'formwizard.button.default',
						'width'       => '1/2',
					],
					// (on phones always the whole width)
					'buttonWidth' => [
						'label'     => 'formwizard.field.width',
						'type'      => 'toggles',
						'default'   => 'auto',
						'translate' => false,
						'options'   => [
							['value' => 'auto', 'text' => ['*' => 'formwizard.button.width.auto']],
							['value' => 'full', 'text' => ['*' => 'formwizard.button.width.full']],
						],
					],
					'buttonAlign' => [
						'label'     => 'formwizard.button.align',
						'type'      => 'toggles',
						'default'   => 'left',
						'translate' => false,
						'options'   => [
							['value' => 'left', 'text' => ['*' => 'formwizard.button.align.left']],
							['value' => 'center', 'text' => ['*' => 'formwizard.button.align.center']],
							['value' => 'right', 'text' => ['*' => 'formwizard.button.align.right']],
						],
						'when'      => ['buttonWidth' => 'auto'],
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

					// the confirmation to the sender: its name as the toggle's text
					'confirm' => [
						'label'     => '',
						'type'      => 'toggle',
						'translate' => false,
						'text'      => 'formwizard.confirm',
						'width'     => '1/2',
					],
					'confirmCopy' => [
						'label'     => '',
						'type'      => 'toggle',
						'default'   => true,
						'translate' => false,
						'text'      => 'formwizard.confirm.copy',
						'width'     => '1/2',
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
				],
			],
			// what happens with a submission: stored, mailed or both; the webhook
			'actions' => [
				'label'  => 'formwizard.tab.actions',
				'icon'   => 'bolt',
				'fields' => [
					'action' => [
						'label'     => 'formwizard.action',
						'type'      => 'checkboxes',
						'columns'   => 3,
						'required'  => true,
						'min'       => 1,
						'default'   => ['store', 'mail'],
						'translate' => false,
						'options'   => [
							['value' => 'store', 'text' => ['*' => 'formwizard.action.store']],
							['value' => 'mail', 'text' => ['*' => 'formwizard.action.mail']],
							['value' => 'webhook', 'text' => ['*' => 'formwizard.webhook']],
						],
					],

					// mailed to the team
					'headlineMail' => [
						'label' => 'formwizard.action.mail',
						'type'  => 'headline',
					],
					'recipients' => [
						'label'       => 'formwizard.team.recipients',
						'type'        => 'text',
						'translate'   => false,
						'placeholder' => 'formwizard.team.recipients.placeholder',
						'help'        => 'formwizard.team.recipients.help',
					],
					'mailSubject' => [
						'label'       => 'formwizard.team.subject',
						'type'        => 'text',
						'placeholder' => 'formwizard.team.subject.placeholder',
						'help'        => 'formwizard.placeholders.help',
					],

					// a webhook (CRM, Zapier, n8n …) – its address required when chosen (checked on saving)
					'headlineWebhook' => [
						'label' => 'formwizard.webhook',
						'type'  => 'headline',
					],
					'webhookUrl' => [
						'label'     => 'formwizard.webhook.url',
						'type'      => 'url',
						'translate' => false,
						'help'      => 'formwizard.webhook.help',
					],
				],
			],
		],
	],

], fwFields::blueprints());
