<template>
	<!-- the form as the website shows it, from the editor's values: its
	     fields in their order and widths (conditional ones faded) -->
	<div class="fw-preview">
		<p class="fw-preview-head">{{ $t('formwizard.preview.website') }}</p>
		<!-- in the project's look (the block's preview of the Project Wizard) -->
		<pw-block-panel-preview v-if="designed" type="pwform" :content="{ _fwfields: fields, _fwbutton: button }" />
		<div v-else class="fw-preview-card">
			<p v-if="!fields.length" class="fw-block-note">{{ $t('formwizard.fields.empty') }}</p>
			<div class="fw-block-fields">
				<div v-for="field in fields" :key="field.id" class="fw-block-field" :class="{ 'is-full': field.width === 'full', 'is-info': field.type === 'fwinfo', 'is-when': field.when }">
					<template v-if="field.type === 'fwinfo'">
						<strong>{{ field.label }}</strong>
						<span v-if="field.text" class="fw-preview-text">{{ field.text }}</span>
					</template>
					<template v-else-if="field.type === 'fwconsent'">
						<span class="fw-block-check"></span>
						<span>{{ field.label }}<span v-if="field.required" class="fw-block-required"> *</span></span>
					</template>
					<template v-else>
						<span class="fw-block-label">{{ field.label }}<span v-if="field.required" class="fw-block-required"> *</span></span>
						<span v-if="['fwradio', 'fwcheckboxes'].includes(field.type)" class="fw-block-options">
							<span v-for="(option, j) in field.options" :key="j"><span class="fw-block-check" :class="{ 'is-round': field.type === 'fwradio' }"></span>{{ option }}</span>
						</span>
						<span v-else class="fw-block-input" :class="{ 'is-text': field.type === 'fwtextarea', 'is-file': field.type === 'fwfile' }">{{ field.placeholder }}</span>
						<span v-if="field.help" class="fw-preview-help">{{ field.help }}</span>
					</template>
				</div>
			</div>
			<div class="fw-block-button">{{ button || $t('formwizard.button.default') }}</div>
		</div>
	</div>
</template>

<script>
const WIDE = ['fwtextarea', 'fwradio', 'fwcheckboxes', 'fwfile', 'fwinfo', 'fwconsent'];

export default {
	props: {
		blocks: { type: Array, default: () => [] },
		button: { type: String, default: '' },
		title: { type: String, default: '' }
	},
	computed: {
		designed() {
			return this.$helper.isComponent('pw-block-panel-preview');
		},
		fields() {
			return this.blocks
				.filter((b) => b && b.type && b.type !== 'fwhidden' && !b.isHidden)
				.map((b) => {
					const c = b.content || {};
					return {
						id: b.id,
						type: b.type,
						label: c.label || this.$t('formwizard.type.' + b.type),
						// (the text without its formatting marks)
						text: (c.text || '').replace(/[*_#]+/g, '').replace(/\(link:\s*\S+\s+text:\s*([^)]+)\)/g, '$1'),
						help: c.help || '',
						placeholder: c.placeholder || '',
						required: c.required === true || c.required === 'true',
						width: c.width || (WIDE.includes(b.type) ? 'full' : 'half'),
						options: ['fwradio', 'fwcheckboxes'].includes(b.type) ? (Array.isArray(c.options) ? c.options : []).map((o) => o.text).filter(Boolean) : [],
						when: !!c.when
					};
				});
		}
	}
};
</script>

<style>
.fw-preview {
	padding: var(--spacing-3);
}
.fw-preview-head {
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	color: var(--color-gray-600);
	margin-bottom: var(--spacing-2);
}
.fw-preview-card {
	background: var(--color-white);
	border-radius: var(--rounded);
	box-shadow: var(--shadow);
	padding: var(--spacing-4);
	font-size: var(--text-sm);
}
.fw-preview .fw-block-input {
	font-size: var(--text-xs);
	color: var(--color-gray-500);
	padding: 4px 6px;
	overflow: hidden;
	white-space: nowrap;
}
.fw-preview-help,
.fw-preview-text {
	font-size: var(--text-xs);
	color: var(--color-gray-600);
}
.fw-block-fields {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: var(--spacing-2) var(--spacing-3);
}
.fw-block-field {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}
.fw-block-field.is-full {
	grid-column: 1 / -1;
}
.fw-block-field.is-when {
	opacity: .6;
}
.fw-block-field.is-info {
	padding-top: var(--spacing-1);
}
.fw-block-field:has(> .fw-block-check) {
	flex-direction: row;
	align-items: flex-start;
	gap: var(--spacing-2);
}
.fw-block-label {
	font-size: var(--text-xs);
	font-weight: var(--font-semi, 500);
}
.fw-block-required {
	color: var(--color-red-600);
}
.fw-block-input {
	display: block;
	height: 1.75rem;
	border: 1px solid var(--color-gray-300);
	border-radius: var(--rounded-sm);
	background: var(--color-white);
}
.fw-block-input.is-text {
	height: 3.5rem;
}
.fw-block-input.is-file {
	border-style: dashed;
}
.fw-block-options {
	display: flex;
	flex-wrap: wrap;
	gap: var(--spacing-1) var(--spacing-3);
	font-size: var(--text-xs);
}
.fw-block-options > span {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.fw-block-check {
	display: inline-block;
	width: .75rem;
	height: .75rem;
	flex-shrink: 0;
	border: 1px solid var(--color-gray-400);
	border-radius: 2px;
	margin-top: 2px;
}
.fw-block-check.is-round {
	border-radius: 50%;
}
.fw-block-button {
	display: inline-block;
	margin-top: var(--spacing-3);
	padding: var(--spacing-1) var(--spacing-4);
	border-radius: var(--rounded-lg);
	background: var(--color-gray-800);
	color: var(--color-white);
	font-size: var(--text-xs);
}
</style>
