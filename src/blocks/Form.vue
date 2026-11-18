<template>
	<!-- the block in the page: its info bar, the shared preview of the
	     Project Wizard (tagline, heading, text), then the form's fields;
	     a double click opens the block -->
	<div class="pwPreview" data-kirbyblock="form" @dblclick="open">
		<pwBlockinfo :value="$t('formwizard.block.name')" :design="'pwform'" icon="formwizard" />

		<!-- the form's head: which form, its state, the way to edit it -->
		<div class="fw-block">
			<p v-if="!formRef || (form === null && !loading)" class="fw-block-note">{{ $t('formwizard.block.noForm') }}</p>
			<div v-else-if="form" class="fw-block-head">
				<k-icon type="formwizard" />
				<strong>{{ form.title }}</strong>
				<k-link :to="form.link" class="fw-block-edit" @click.native.stop>{{ $t('formwizard.block.edit') }} →</k-link>
			</div>
		</div>

		<!-- the block as on the website: the Project Wizard's preview, the
		     form's own fields handed in (_fwfields, _fwbutton) -->
		<pw-block-panel-preview type="pwform" :content="previewContent" />
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
		previewContent() {
			if (!this.form) return { ...this.content, _fwfields: [] };
			return { ...this.content, _fwfields: this.form.fields, _fwbutton: this.form.button, _fwbuttonlayout: this.form.buttonLayout };
		},
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
</style>
