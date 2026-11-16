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
				<span v-if="condition" class="fw-field-condition"> · {{ $t('formwizard.preview.condition') }} {{ condition }}</span>
			</div>
		</div>
		<span v-if="fwType !== 'fwhidden'" class="fw-field-width" :title="$t('formwizard.field.width')">{{ width === 'full' ? '1/1' : '1/2' }}</span>
	</div>
</template>

<script>
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
		// "<block id>:<value>" – the value is enough to recognise it
		condition() {
			const when = this.content.when || '';
			return when.includes(':') ? '= ' + when.slice(when.indexOf(':') + 1) : '';
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
