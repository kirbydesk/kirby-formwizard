<template>
	<!-- Project Wizard › Forms: the list, a new form, a form (its fields,
	     what happens after sending, its settings – with the preview in the
	     wizard's right column), the settings of all forms -->
	<div class="fw-pw">
		<!-- the list -->
		<template v-if="mode === 'list'">
			<div class="fw-pw-bar">
				<k-button icon="add" variant="filled" size="sm" :text="$t('formwizard.form.new')" @click="$emit('navigate', 'new')" />
			</div>
			<k-empty v-if="list && !list.length" icon="formwizard" layout="cards" @click="$emit('navigate', 'new')">{{ $t('formwizard.forms.empty') }}</k-empty>
			<table v-else-if="list" class="fw-subs-table">
				<thead>
					<tr>
						<th>{{ $t('formwizard.form') }}</th>
						<th>{{ $t('formwizard.fields') }}</th>
						<th>{{ $t('formwizard.submissions') }}</th>
						<th class="fw-subs-summary">{{ $t('formwizard.form.usage') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="form in list" :key="form.slug" @click="$emit('navigate', 'form:' + form.slug)">
						<td><span class="fw-subs-title">{{ form.title }}</span></td>
						<td>{{ form.fields }}</td>
						<td>{{ form.submissions }} <span v-if="form.new" class="fw-pill" data-status="new">{{ form.new }} {{ $t('formwizard.status.new') }}</span></td>
						<td class="fw-subs-summary">{{ form.usage.join(', ') || '–' }}</td>
						<td class="fw-subs-status">
							<span v-if="form.closed" class="fw-pill">{{ $t('formwizard.closed.on') }}</span>
							<span class="fw-pill" :data-status="form.status === 'listed' ? 'active' : 'draft'">{{ $t('formwizard.status.' + form.status) }}</span>
						</td>
					</tr>
				</tbody>
			</table>
		</template>

		<!-- a new form: its name -->
		<section v-else-if="mode === 'new'" class="fw-pw-new">
			<k-form :fields="newFields" :value="{ title: newTitle, template: newTemplate }" @input="newTitle = $event.title; newTemplate = $event.template" @submit="create" />
			<div class="fw-pw-buttons">
				<k-button variant="filled" size="sm" :text="$t('cancel')" @click="$emit('navigate', 'list')" />
				<k-button variant="filled" size="sm" theme="positive" icon="check" :text="$t('formwizard.form.create')" :disabled="!newTitle.trim() || busy" @click="create" />
			</div>
		</section>

		<!-- a form -->
		<template v-else-if="mode === 'form'">
			<p v-if="!form" class="fw-block-note">…</p>
			<template v-else>
				<div class="fw-pw-head">
					<nav class="fw-pw-tabs">
						<button v-for="t in tabs" :key="t.key" type="button" :aria-current="tab === t.key ? 'true' : undefined" @click="tab = t.key">
							<k-icon :type="t.icon" />{{ $t(t.label) }}
						</button>
					</nav>
					<div class="fw-pw-actions">
						<!-- the language edited (multilingual sites) -->
						<span v-if="languages.length > 1" class="fw-pw-langs" role="group">
							<button v-for="l in languages" :key="l.code" type="button" :aria-pressed="lang === l.code ? 'true' : 'false'" :title="l.name" @click="switchLanguage(l.code)">{{ l.code.toUpperCase() }}</button>
						</span>
						<k-button
							:icon="form.status === 'listed' ? 'status-listed' : 'status-draft'"
							variant="filled"
							size="sm"
							:theme="form.status === 'listed' ? 'positive' : null"
							:text="$t('formwizard.status.' + form.status)"
							:title="$t(form.status === 'listed' ? 'formwizard.status.listed.text' : 'formwizard.status.draft.text')"
							@click="toggleStatus"
						/>
						<k-button icon="email" variant="filled" size="sm" :text="$t('formwizard.submissions') + ' (' + form.submissions + ')'" :link="'formwizard/submissions?form=' + form.folder" />
						<k-button icon="trash" variant="filled" size="sm" theme="negative" :title="$t('delete')" @click="remove" />
					</div>
				</div>

				<k-form :key="form.slug + '-' + tab + '-' + version" :fields="tabFields" :value="values" @input="onInput" @submit="save" />

				<p v-if="tab === 'settings'" class="k-help fw-pw-usage">
					{{ form.usage.length ? $t('formwizard.form.usedOn', { pages: form.usage.join(', ') }) : $t('formwizard.form.unused') }}
				</p>

				<div class="fw-pw-buttons" :class="{ 'is-dirty': dirty }">
					<k-button v-if="dirty" variant="filled" size="sm" theme="notice" icon="undo" :text="$t('discard')" @click="discard" />
					<k-button variant="filled" size="sm" theme="positive" icon="check" :text="$t('save')" :disabled="!dirty || busy" @click="save" />
				</div>

				<!-- the preview: in the wizard's right column -->
				<pw-portal to=".pw-wizard .pw-preview-column">
					<fw-form-preview :blocks="values.fields || []" :button="values.buttontext" :title="titleValue" />
				</pw-portal>
			</template>
		</template>

		<!-- the settings of all forms -->
		<section v-else-if="mode === 'settings'" class="fw-pw-settings">
			<k-form v-if="settings" :fields="settingsFields" :value="settings" @input="settings = $event; settingsDirty = true" @submit="saveSettings" />
			<div class="fw-pw-buttons">
				<k-button variant="filled" size="sm" theme="positive" icon="check" :text="$t('save')" :disabled="!settingsDirty || busy" @click="saveSettings" />
			</div>
		</section>
	</div>
</template>

<script>
export default {
	props: {
		// list, new, settings or "form:<slug>"
		view: { type: String, default: null }
	},
	data() {
		return {
			list: null,
			form: null,
			values: {},
			saved: {},
			tab: 'fields',
			version: 0,
			dirty: false,
			busy: false,
			newTitle: '',
			newTemplate: 'contact',
			lang: null,
			settings: null,
			settingsDirty: false
		};
	},
	computed: {
		languages() {
			return this.$panel.languages || [];
		},
		mode() {
			if (!this.view || this.view === 'list') return 'list';
			if (this.view.startsWith('form:')) return 'form';
			return this.view;
		},
		slug() {
			return this.mode === 'form' ? this.view.slice(5) : null;
		},
		tabs() {
			return [
				{ key: 'fields', label: 'formwizard.tab.fields', icon: 'formwizard' },
				{ key: 'after', label: 'formwizard.tab.after', icon: 'check' },
				{ key: 'settings', label: 'formwizard.tab.settings', icon: 'settings' }
			];
		},
		// the fields of the open tab (the settings start with the form's name)
		tabFields() {
			const fields = { ...(this.form.tabs[this.tab] || {}) };
			if (this.tab !== 'settings') return fields;
			return {
				title: { name: 'title', type: 'text', label: this.$t('formwizard.form.name'), required: true, width: '1/2' },
				...fields
			};
		},
		titleValue() {
			return this.values.title || (this.form ? this.form.title : '');
		},
		newFields() {
			return {
				title: { name: 'title', type: 'text', label: this.$t('formwizard.form.name'), required: true, autofocus: true },
				template: {
					name: 'template',
					type: 'radio',
					label: this.$t('formwizard.tpl'),
					help: this.$t('formwizard.tpl.help'),
					options: ['contact', 'callback', 'application', 'registration', ''].map((t) => ({ value: t, text: this.$t('formwizard.tpl.' + (t || 'empty')), info: t ? this.$t('formwizard.tpl.' + t + '.info') : null }))
				}
			};
		},
		settingsFields() {
			const secretHelp = {
				stored: this.$t('formwizard.captcha.secret.stored'),
				unreadable: this.$t('formwizard.captcha.secret.unreadable'),
				none: this.$t('formwizard.captcha.secret.none')
			}[this.settings?.captchaSecretState || 'none'];
			return {
				retention: {
					name: 'retention',
					type: 'select',
					label: this.$t('formwizard.settings.retention'),
					help: this.$t('formwizard.settings.retention.help'),
					empty: false,
					width: '1/2',
					options: [30, 90, 180, 365, 0].map((d) => ({ value: d, text: d ? this.$t('formwizard.settings.retention.days', { days: d }) : this.$t('formwizard.settings.retention.never') }))
				},
				headlineSpam: { name: 'headlineSpam', type: 'headline', label: this.$t('formwizard.spam') },
				spamInfo: { name: 'spamInfo', type: 'info', text: this.$t('formwizard.spam.always') },
				captcha: {
					name: 'captcha',
					type: 'select',
					label: this.$t('formwizard.captcha'),
					empty: false,
					width: '1/2',
					help: this.$t('formwizard.captcha.help'),
					options: [
						{ value: 'none', text: this.$t('formwizard.captcha.none') },
						{ value: 'friendly', text: 'Friendly Captcha' },
						{ value: 'turnstile', text: 'Cloudflare Turnstile' },
						{ value: 'hcaptcha', text: 'hCaptcha' }
					]
				},
				// (the keys only with a provider chosen)
				...(this.settings && this.settings.captcha && this.settings.captcha !== 'none'
					? {
							captchaSiteKey: { name: 'captchaSiteKey', type: 'text', label: this.$t('formwizard.captcha.sitekey'), width: '1/2' },
							captchaSecret: { name: 'captchaSecret', type: 'password', label: this.$t('formwizard.captcha.secret'), help: secretHelp, width: '1/2', autocomplete: 'new-password' }
						}
					: {}),
				rate: { name: 'rate', type: 'toggle', label: this.$t('formwizard.rate'), text: [this.$t('formwizard.off'), this.$t('formwizard.on')], width: '1/2' },
				rateLimit: { name: 'rateLimit', type: 'number', label: this.$t('formwizard.rate.limit'), min: 1, max: 100, width: '1/4', when: { rate: true } },
				rateMinutes: { name: 'rateMinutes', type: 'number', label: this.$t('formwizard.rate.minutes'), min: 1, max: 1440, width: '1/4', after: this.$t('formwizard.rate.minutes.unit'), when: { rate: true } },
				rateInfo: { name: 'rateInfo', type: 'info', text: this.$t('formwizard.rate.help'), when: { rate: true } }
			};
		}
	},
	watch: {
		view: {
			immediate: true,
			handler(view, old) {
				// (back to the form after "keep editing": nothing to load)
				if (this._restoring) {
					this._restoring = false;
					return;
				}
				// unsaved changes: asked first – keep editing goes back
				if (this.dirty && old !== undefined) return this.confirmLeave(old);
				this.load();
			}
		}
	},
	mounted() {
		this._unload = (e) => {
			if (this.dirty) e.preventDefault();
		};
		window.addEventListener('beforeunload', this._unload);
	},
	beforeDestroy() {
		window.removeEventListener('beforeunload', this._unload);
	},
	methods: {
		confirmLeave(old) {
			let decided = false;
			const stay = () => {
				if (decided) return;
				decided = true;
				this._restoring = true;
				this.$emit('navigate', old);
			};
			this.$panel.dialog.open(
				{
					component: 'k-text-dialog',
					props: {
						text: this.$t('formwizard.unsaved'),
						submitButton: { text: this.$t('discard'), icon: 'undo', theme: 'notice' },
						cancelButton: this.$t('formwizard.unsaved.stay')
					}
				},
				{
					on: {
						submit: () => {
							decided = true;
							this.$panel.dialog.close();
							this.dirty = false;
							this.load();
						},
						cancel: stay,
						close: stay
					}
				}
			);
		},
		language() {
			return this.lang || this.$panel.language?.code || null;
		},
		// another language of the form: asked first with unsaved changes
		async switchLanguage(code) {
			if (code === this.language()) return;
			if (this.dirty && !window.confirm(this.$t('formwizard.unsaved'))) return;
			this.lang = code;
			this.dirty = false;
			this.take(await this.$api.get('formwizard/pw/forms/' + this.slug, { language: code }));
		},
		async load() {
			this.$emit('preview', this.mode === 'form');
			this.dirty = false;
			if (this.mode === 'list') {
				this.$emit('title', { text: this.$t('formwizard.forms'), icon: 'formwizard' });
				this.list = await this.$api.get('formwizard/pw/forms');
			} else if (this.mode === 'new') {
				this.$emit('title', { text: this.$t('formwizard.form.new'), icon: 'add' });
				this.newTitle = '';
				this.newTemplate = 'contact';
			} else if (this.mode === 'settings') {
				this.$emit('title', { text: this.$t('formwizard.settings.title'), icon: 'settings' });
				this.settings = await this.$api.get('formwizard/pw/settings');
				this.settingsDirty = false;
			} else if (this.mode === 'form') {
				this.form = null;
				this.tab = 'fields';
				try {
					this.take(await this.$api.get('formwizard/pw/forms/' + this.slug, { language: this.language() }));
				} catch (e) {
					this.$emit('navigate', 'list');
				}
			}
		},
		take(form) {
			this.form = form;
			this.values = { ...form.values, title: form.title };
			this.saved = JSON.stringify(this.values);
			this.dirty = false;
			this.version++;
			this.$emit('title', { text: form.title, icon: 'formwizard' });
		},
		onInput(values) {
			this.values = { ...this.values, ...values };
			this.dirty = JSON.stringify(this.values) !== this.saved;
		},
		discard() {
			this.values = JSON.parse(this.saved);
			this.dirty = false;
			this.version++;
		},
		async save() {
			if (!this.dirty || this.busy) return;
			this.busy = true;
			try {
				const { title, ...values } = this.values;
				this.take(await this.$api.post('formwizard/pw/forms/' + this.slug, { values, title, language: this.language() }));
				this.$panel.notification.success();
				this.$emit('changed');
			} finally {
				this.busy = false;
			}
		},
		async create() {
			if (!this.newTitle.trim() || this.busy) return;
			this.busy = true;
			try {
				const { slug } = await this.$api.post('formwizard/pw/forms', { title: this.newTitle, template: this.newTemplate });
				this.$emit('changed');
				this.$emit('navigate', 'form:' + slug);
			} finally {
				this.busy = false;
			}
		},
		async toggleStatus() {
			const status = this.form.status === 'listed' ? 'draft' : 'listed';
			const form = await this.$api.post('formwizard/pw/forms/' + this.slug + '/status', { status });
			// (unsaved changes stay)
			this.form = { ...this.form, status: form.status };
			this.$emit('changed');
		},
		remove() {
			this.$panel.dialog.open(
				{
					component: 'k-remove-dialog',
					props: { text: this.$t('formwizard.form.delete.confirm', { title: this.form.title }) }
				},
				async () => {
					await this.$api.delete('formwizard/pw/forms/' + this.slug);
					this.$panel.dialog.close();
					this.$panel.notification.success();
					this.dirty = false;
					this.$emit('changed');
					this.$emit('navigate', 'list');
				}
			);
		},
		async saveSettings() {
			this.busy = true;
			try {
				this.settings = await this.$api.post('formwizard/pw/settings', this.settings);
				this.settingsDirty = false;
				this.$panel.notification.success();
			} finally {
				this.busy = false;
			}
		}
	}
};
</script>

<style>
.fw-pw-bar {
	display: flex;
	justify-content: flex-end;
	margin-bottom: var(--spacing-3);
}
.fw-pw-head {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: var(--spacing-3);
	margin-bottom: var(--spacing-6);
	border-bottom: 1px solid var(--color-border, var(--color-gray-300));
}
.fw-pw-tabs {
	display: flex;
	gap: var(--spacing-1);
}
.fw-pw-tabs button {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	padding: var(--spacing-2) var(--spacing-3);
	font-size: var(--text-sm);
	color: var(--color-gray-700);
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
}
.fw-pw-tabs button[aria-current="true"] {
	color: var(--color-text);
	border-bottom-color: currentColor;
	font-weight: var(--font-semi, 500);
}
.fw-pw-actions {
	display: flex;
	gap: var(--spacing-2);
	padding-bottom: var(--spacing-2);
}
.fw-pw-langs {
	display: inline-flex;
	border-radius: var(--rounded);
	overflow: hidden;
	box-shadow: var(--shadow);
}
.fw-pw-langs button {
	padding: 0 var(--spacing-2);
	height: var(--height-sm, 1.75rem);
	font-size: var(--text-xs);
	font-family: var(--font-mono);
	background: var(--color-white);
}
.fw-pw-langs button[aria-pressed="true"] {
	background: var(--color-black);
	color: var(--color-white);
}
.fw-pw-buttons {
	display: flex;
	justify-content: flex-end;
	gap: var(--spacing-2);
	margin-top: var(--spacing-6);
}
.fw-pw-buttons.is-dirty {
	position: sticky;
	bottom: var(--spacing-3);
}
.fw-pw-usage {
	margin-top: var(--spacing-6);
}
.fw-pw-note {
	margin-top: var(--spacing-6);
}
.fw-pill[data-status="active"] {
	background: color-mix(in srgb, var(--color-positive, #1a7f37) 18%, transparent);
	color: var(--color-positive, #1a7f37);
}
.fw-pw-new {
	max-width: 30rem;
}
</style>
