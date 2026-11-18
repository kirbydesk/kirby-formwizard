/* The form being edited (Project Wizard › Forms › a form): its fields,
   for the field list and the conditions' choices – the condition's drawer
   is not inside the blocks field, so it reads them here */
export const fwEditor = window.Vue.observable({ blocks: [] });

// the slug of a text as the server keeps an option's value (the default
// language's rules, see Editor::adoptLegacy)
export function slugger(vm) {
	const panel = vm.$panel;
	const first = (panel.languages || []).find((l) => l.default);
	const rules = [first?.rules ?? panel.system?.slugs, panel.system?.ascii].filter(Boolean);
	return (text) => vm.$helper.slug(text, rules);
}

// a choice's value: its own, else its text as a slug (as on the server)
export function optionValue(option, slug) {
	return String((option && option.value) || slug(String((option && option.text) || '')));
}

/**
 * A field's condition read: { field, text } of the choice field above it
 * and the option, or { invalid: true } when either is gone – null without one
 */
export function readCondition(when, id, blocks, slug) {
	if (!when || !String(when).includes(':')) return null;
	const [target, value] = [String(when).slice(0, String(when).indexOf(':')), String(when).slice(String(when).indexOf(':') + 1)];
	const index = blocks.findIndex((b) => b && b.id === id);
	const above = index > -1 ? blocks.slice(0, index) : blocks;
	const field = above.find((b) => b && b.id === target);
	const option = field && (Array.isArray(field.content?.options) ? field.content.options : []).find((o) => optionValue(o, slug) === value);
	if (!option) return { invalid: true };
	return { field: field.content.label || field.type, text: option.text || value };
}
