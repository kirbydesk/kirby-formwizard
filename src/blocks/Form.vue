<template>
	<!-- the block in the page: its info bar, the shared preview of the
	     Project Wizard (tagline, heading, text), then the form's fields;
	     a double click opens the block -->
	<div class="pwPreview" data-kirbyblock="form" @dblclick="open">
		<pwBlockinfo :value="$t('formwizard.block.name')" :design="'pwform'" icon="formwizard" />

		<pw-block-panel-preview type="pwform" :content="content" />

		<div class="fw-block">
			<p v-if="!formRef" class="fw-block-note">{{ $t('formwizard.block.noForm') }}</p>
			<p v-else-if="form === null" class="fw-block-note">{{ loading ? '…' : $t('formwizard.block.noForm') }}</p>
			<template v-else>
				<div class="fw-block-head">
					<k-icon type="formwizard" />
					<strong>{{ form.title }}</strong>
					<span v-if="form.status === 'draft'" class="fw-block-pill">{{ $t('formwizard.status.draft') }}</span>
					<span v-if="form.closed" class="fw-block-pill">{{ $t('formwizard.closed.on') }}</span>
					<k-link :to="form.link" class="fw-block-edit" @click.native.stop>{{ $t('formwizard.block.edit') }} →</k-link>
				</div>
				<div class="fw-block-fields">
					<div v-for="(field, i) in form.fields" :key="i" class="fw-block-field" :class="{ 'is-full': field.width === 'full', 'is-info': field.type === 'fwinfo', 'is-when': field.when }">
						<template v-if="field.type === 'fwinfo'">
							<strong>{{ field.label }}</strong>
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
							<span v-else class="fw-block-input" :class="{ 'is-text': field.type === 'fwtextarea', 'is-file': field.type === 'fwfile' }"></span>
						</template>
					</div>
				</div>
				<div class="fw-block-button">{{ form.button }}</div>
			</template>
		</div>
	</div>
</template>

<script>
import pwBlockinfo from '@/../../kirby-pagewizard/src/components/blockinfo.vue';

export default {
	components: {
		pwBlockinfo
	},
	data() {
		return {
			form: null,
			loading: false
		};
	},
	computed: {
		// the chosen form: its uuid (or id)
		formRef() {
			const value = Array.isArray(this.content.form) ? this.content.form[0] : this.content.form;
			if (!value) return null;
			return typeof value === 'string' ? value : (value.uuid || value.id || null);
		}
	},
	watch: {
		formRef: {
			handler: 'load',
			immediate: true
		}
	},
	methods: {
		async load() {
			if (!this.formRef) {
				this.form = null;
				return;
			}
			this.loading = true;
			try {
				this.form = (await this.$api.get('formwizard/form', { form: this.formRef })).form;
			} catch (e) {
				this.form = null;
			}
			this.loading = false;
		}
	}
};
</script>

<style>
.fw-block {
	padding: var(--spacing-3) var(--spacing-4) var(--spacing-4);
	border-top: 1px dashed var(--color-gray-300);
	font-size: var(--text-sm);
}
.fw-block-note {
	color: var(--color-gray-600);
}
.fw-block-head {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	margin-bottom: var(--spacing-3);
}
.fw-block-pill {
	font-size: var(--text-xs);
	background: var(--color-gray-200);
	border-radius: var(--rounded-lg);
	padding: 0 var(--spacing-2);
}
.fw-block-edit {
	margin-inline-start: auto;
	color: var(--color-blue-700);
	font-size: var(--text-xs);
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
