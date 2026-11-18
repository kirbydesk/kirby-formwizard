<template>
	<!-- a field's condition: the select with the choices of the choice
	     fields above this one – from the form being edited, so new options
	     are there at once (the one chosen stays, even when gone) -->
	<k-select-field v-bind="$attrs" :options="shown" v-on="$listeners" />
</template>

<script>
import { fwEditor, optionValue, slugger } from '../editor.js';

export default {
	inheritAttrs: false,
	props: {
		options: { type: [Array, Object], default: () => [] }
	},
	computed: {
		shown() {
			const id = this.$panel.drawer?.id;
			const blocks = fwEditor.blocks;
			const index = blocks.findIndex((b) => b && b.id === id);
			// (outside the form's editor: the server's choices)
			if (index < 0) return Array.isArray(this.options) ? this.options : [];
			const list = [];
			const slug = slugger(this);
			for (const block of blocks.slice(0, index)) {
				if (!block || !['fwselect', 'fwradio', 'fwcheckboxes'].includes(block.type)) continue;
				const label = block.content?.label || this.$t('formwizard.type.' + block.type);
				const seen = new Set();
				for (const option of Array.isArray(block.content?.options) ? block.content.options : []) {
					const value = optionValue(option, slug);
					if (!option.text || !value || seen.has(value)) continue;
					seen.add(value);
					list.push({ value: block.id + ':' + value, text: label + ' = ' + option.text });
				}
			}
			const current = this.$attrs.value;
			if (current && !list.some((o) => o.value === current)) list.push({ value: current, text: this.$t('formwizard.preview.condition.invalid') });
			return list;
		}
	}
};
</script>
