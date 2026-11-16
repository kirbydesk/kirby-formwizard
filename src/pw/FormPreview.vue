<template>
	<!-- the form as the website shows it, from the editor's values: its
	     fields in their order and widths (conditional ones faded) -->
	<div class="fw-preview">
		<p class="fw-preview-head">{{ $t('formwizard.preview.website') }}</p>
		<div class="fw-preview-card">
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
						options: (Array.isArray(c.options) ? c.options : []).map((o) => o.text).filter(Boolean),
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
</style>
