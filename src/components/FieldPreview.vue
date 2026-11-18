<template>
	<!-- a field of the form, one row: type, label, required, width,
	     condition; a double click opens it -->
	<div class="fw-field" :data-type="fwType" :data-width="width" @dblclick="open">
		<k-icon :type="fieldset.icon" class="fw-field-icon" />
		<div class="fw-field-text">
			<div class="fw-field-label">
				{{ label }}<span v-if="required" class="fw-field-required" :title="$t('formwizard.preview.required')"> *</span>
			</div>
			<div class="fw-field-meta">
				<span>{{ fieldset.name }}</span>
				<span v-if="optionsCount !== null"> · {{ $t('formwizard.preview.options', { count: optionsCount }) }}</span>
				<span v-if="fwType === 'fwhidden'"> · {{ $t('formwizard.preview.hidden') }}</span>
				<span v-if="condition && condition.invalid" class="fw-field-condition is-invalid"> · {{ $t('formwizard.preview.condition.invalid') }}</span>
				<span v-else-if="condition" class="fw-field-condition"> · {{ $t('formwizard.preview.condition') }} {{ condition.field }} = {{ condition.text }}</span>
			</div>
		</div>
		<!-- (a condition: at a glance, beside the width) -->
		<k-icon v-if="condition" :type="condition.invalid ? 'alert' : 'git-branch'" class="fw-field-when" :class="{ 'is-invalid': condition.invalid }" />
		<span v-if="fwType !== 'fwhidden'" class="fw-field-width" :title="$t('formwizard.field.width')">{{ { half: '1/2', full: '1/1' }[width] || width }}</span>
	</div>
</template>

<script>
import { fwEditor, readCondition, slugger } from '../editor.js';

export default {
	computed: {
		// (the block's type: "type" of the block, else its fieldset's)
		fwType() {
			return this.type || this.fieldset?.type;
		},
		label() {
			return this.content.label || this.fieldset.name;
		},
		required() {
			return this.content.required === true || this.content.required === 'true';
		},
		width() {
			return this.content.width || (['fwtextarea', 'fwradio', 'fwcheckboxes', 'fwfile', 'fwinfo', 'fwconsent'].includes(this.fwType) ? 'full' : 'half');
		},
		optionsCount() {
			return Array.isArray(this.content.options) ? this.content.options.length : null;
		},
		// "<block id>:<value>": the choice field above and its option (the
		// fields of the form being edited), else only the value
		condition() {
			const when = this.content.when || '';
			if (!when.includes(':')) return null;
			if (!fwEditor.blocks.length) return { field: '', text: when.slice(when.indexOf(':') + 1) };
			return readCondition(when, this.id, fwEditor.blocks, slugger(this));
		}
	}
};
</script>

<style>
.fw-field {
	display: flex;
	align-items: center;
	gap: var(--spacing-3);
	padding: var(--spacing-2) var(--spacing-3);
	cursor: pointer;
}
.fw-field[data-type="fwinfo"] {
	background: var(--color-gray-100);
}
.fw-field-icon {
	color: var(--color-gray-600);
	flex-shrink: 0;
}
.fw-field-text {
	flex-grow: 1;
	min-width: 0;
	line-height: 1.3;
}
.fw-field-label {
	font-weight: var(--font-semi, 500);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.fw-field-required {
	color: var(--color-red-600);
}
.fw-field-meta {
	font-size: var(--text-xs);
	color: var(--color-gray-600);
}
.fw-field-condition {
	color: var(--color-blue-700);
}
.fw-field-condition.is-invalid,
.fw-field-when.is-invalid {
	color: var(--color-red-700);
}
.fw-field-when {
	color: var(--color-blue-700);
	flex-shrink: 0;
}
.fw-field-width {
	font-size: var(--text-xs);
	font-family: var(--font-mono);
	color: var(--color-gray-600);
	border: 1px solid var(--color-gray-300);
	border-radius: var(--rounded-sm);
	padding: 0 var(--spacing-1);
	flex-shrink: 0;
}
</style>
