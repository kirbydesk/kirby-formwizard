<template>
	<!-- Project Wizard › Forms: the overview (tabs: the forms with the
	     submissions' retention, the spam protection, the mail server), a
	     new form, a form (its fields, the response, the actions – with
	     the preview in the wizard's right column). Saved with the wizard's
	     save button above: it calls save() and discard(), asks askLeave()
	     before another page and shows the count sent with "pending". -->
	<div class="fw-pw">
		<!-- the overview: its tabs -->
		<template v-if="mode === 'settings'">
		<div class="fw-pw-head">
			<nav class="fw-pw-tabs">
				<button v-for="t in settingsTabs" :key="t.key" type="button" :aria-current="settingsTab === t.key ? 'true' : undefined" @click="openSettingsTab(t.key)">
					<k-icon :type="t.icon" />{{ t.label }}
				</button>
			</nav>
		</div>

		<!-- the forms, below them how long their submissions stay (with forms only) -->
		<div v-if="settingsTab === 'forms'" class="fw-pw-page">
			<div class="fw-pw-bar">
				<k-button icon="add" variant="filled" size="sm" :text="$t('formwizard.form.new')" @click="createDialog" />
			</div>
			<k-empty v-if="list && !list.length" icon="formwizard" layout="cards" @click="createDialog">{{ $t('formwizard.forms.empty') }}</k-empty>
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
						<!-- (deleting only here, not in the form's page) -->
						<td class="fw-pw-options" @click.stop>
							<k-button icon="dots" size="xs" :title="$t('options')" @click="$refs['options-' + form.slug][0].toggle()" />
							<k-dropdown-content :ref="'options-' + form.slug" :options="[{ icon: 'trash', text: $t('delete'), theme: 'negative', click: () => remove(form) }]" align-x="end" />
						</td>
					</tr>
				</tbody>
			</table>
			<section v-if="settings && list && list.length" class="fw-pw-settings">
				<k-form :fields="retentionFields" :value="settings" @input="settings = { ...settings, ...$event }" />
			</section>
		</div>

		<!-- the spam protection (of all forms) -->
		<div v-else-if="settingsTab === 'spam'" class="fw-pw-page">
			<k-form v-if="settings" :fields="spamFields" :value="settings" @input="settings = { ...settings, ...$event }" />
		</div>

		<!-- the mail server (the Project Wizard's, saved with its others) -->
		<div v-else-if="settingsTab === 'mail' && pwMail" class="fw-pw-page">
			<pw-mail-settings :mail="pwMail.state" />
		</div>
		</template>


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
				</div>

				<!-- the language edited (multilingual sites): Kirby's language
				     dropdown in the title row, as on pages – for the whole Panel -->
				<!-- (a place that stays: Vue keeps its nodes in order, the portal's content lives elsewhere) -->
				<div class="fw-pw-portal">
					<pw-portal v-if="languages.length > 1" to=".pw-wizard .pw-page-title-row" class="fw-pw-language">
						<div class="k-view-button k-languages-dropdown">
							<k-button icon="translate" variant="filled" size="sm" responsive="text" :dropdown="true" :text="language().toUpperCase()" @click="$refs.languages.toggle()" />
							<k-dropdown-content ref="languages" :options="languageOptions" align-x="end">
								<template #item="{ item, index }">
									<k-button :key="'item-' + index" v-bind="item" class="k-dropdown-item k-languages-dropdown-item" @click="switchLanguage(item.code)">
										{{ item.text }}
										<span class="k-languages-dropdown-item-info">
											<span class="k-languages-dropdown-item-code">{{ item.code.toUpperCase() }}</span>
										</span>
									</k-button>
								</template>
							</k-dropdown-content>
						</div>
					</pw-portal>
				</div>

				<k-form v-if="tab !== 'design'" :key="form.slug + '-' + tab + '-' + version" :fields="tabFields" :value="values" @input="onInput" />

				<!-- below the fields, as on the website: the captcha (with one
				     set up) and the send button – fixed rows of the list, their
				     settings in Kirby's drawer (a double click, as a field) -->
				<div v-if="tab === 'fields'" :key="'fixed-' + form.slug + '-' + version" class="fw-pw-portal">
					<pw-portal to=".fw-pw .k-field-name-fields .k-blocks" class="fw-pw-fixed">
						<div v-if="form.tabs.fields.captcha" class="fw-pw-fixed-row" @dblclick="openFixed(['captcha'], 'shield')">
							<div class="fw-field">
								<k-icon type="shield" class="fw-field-icon" />
								<div class="fw-field-text">
									<div class="fw-field-label">{{ $t('formwizard.captcha') }}</div>
									<div class="fw-field-meta">{{ form.tabs.fields.captcha.help }} · {{ $t(captchaOn ? 'formwizard.on' : 'formwizard.off') }}</div>
								</div>
							</div>
						</div>
						<div class="fw-pw-fixed-row" @dblclick="openFixed(['buttontext', 'buttonwidth', 'buttonalign'], 'plane')">
							<div class="fw-field">
								<k-icon type="plane" class="fw-field-icon" />
								<div class="fw-field-text">
									<div class="fw-field-label">{{ values.buttontext || $t('formwizard.button.default') }}</div>
									<div class="fw-field-meta">{{ $t('formwizard.button') }} · {{ $t('formwizard.button.meta.' + (buttonLayout[0] === 'full' ? 'full' : buttonLayout[1])) }}</div>
								</div>
								<span class="fw-field-width">{{ buttonLayout[0] === 'full' ? '1/1' : 'auto' }}</span>
							</div>
						</div>
					</pw-portal>
				</div>

				<!-- the design of all forms (the block's, from the Project Wizard):
				     kept while the form is open, saved along with it -->
				<pw-block-design v-if="designOpened" v-show="tab === 'design'" ref="design" type="pwform" @pending="designPending = $event" />

				<!-- the preview: in the wizard's right column -->
				<!-- (a place that stays: Vue keeps its nodes in order, the portal's content lives elsewhere) -->
				<div class="fw-pw-portal">
					<pw-portal to=".pw-wizard .pw-preview-column">
						<fw-form-preview :blocks="values.fields || []" :button="values.buttontext" :button-layout="buttonLayout" :uploads="form.uploads" :title="titleValue" />
					</pw-portal>
				</div>
			</template>
		</template>

	</div>
</template>

<script>
import { fwEditor } from '../editor.js';

export default {
	props: {
		// list, new, settings or "form:<slug>"
		view: { type: String, default: null }
	},
	// the Project Wizard's mail server: { state, load } (Overview.vue)
	inject: {
		pwMail: { default: null }
	},
	data() {
		return {
			list: null,
			form: null,
			values: {},
			saved: {},
			tab: 'fields',
			version: 0,
			designPending: 0,
			designOpened: false,
			busy: false,
			settings: null,
			// the overview's tab: forms, spam, mail
			settingsTab: 'forms',
			settingsSaved: 'null'
		};
	},
	computed: {
		languages() {
			return this.$panel.languages || [];
		},
		// the form's values changed (by field) – not by mode: a view changed
		// is asked about with the changes of the one before
		formPending() {
			if (!this.form) return 0;
			const saved = JSON.parse(this.saved || '{}');
			return [...new Set([...Object.keys(this.values), ...Object.keys(saved)])].filter((k) => JSON.stringify(this.values[k] ?? null) !== JSON.stringify(saved[k] ?? null)).length;
		},
		// the settings of all forms changed (by field)
		settingsPending() {
			if (!this.settings) return 0;
			const saved = JSON.parse(this.settingsSaved) || {};
			return Object.keys(this.settings).filter((k) => JSON.stringify(this.settings[k] ?? null) !== JSON.stringify(saved[k] ?? null)).length;
		},
		// all changes of this page (the count at the wizard's save button)
		pending() {
			return this.formPending + this.designPending + this.settingsPending;
		},
		// as Kirby's: the default language first, then a line, then the others
		languageOptions() {
			const current = this.language();
			const option = (l) => ({ text: l.name, code: l.code, current: l.code === current });
			const first = this.languages.find((l) => l.default) || this.languages[0];
			return [option(first), '-', ...this.languages.filter((l) => l !== first).map(option)];
		},
		mode() {
			// (the list of all: on the settings' page, at the top; a new form:
			// Kirby's dialog over it)
			if (!this.view || this.view === 'list' || this.view === 'new') return 'settings';
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
				{ key: 'actions', label: 'formwizard.tab.actions', icon: 'bolt' },
				// (with the Project Wizard's design of blocks)
				...(this.$helper.isComponent('pw-block-design') ? [{ key: 'design', label: 'formwizard.tab.design', icon: 'prw-design' }] : [])
			];
		},
		// the fields of the open tab (the settings start with the form's name)
		tabFields() {
			const fields = { ...(this.form.tabs[this.tab] || {}) };
			// the actions: the mail's fields only when mailed (its recipients
			// required), the files' choice only when stored and mailed, the
			// webhook's address only with it (then required)
			// the captcha and the send button: rows below the list (their drawer)
			if (this.tab === 'fields') {
				for (const name of ['captcha', 'buttontext', 'buttonwidth', 'buttonalign']) delete fields[name];
			}
			// one e-mail field per form: once there, not offered any more
			if (this.tab === 'fields' && fields.fields) {
				const hasEmail = (this.values.fields || []).some((b) => b && b.type === 'fwemail');
				if (hasEmail) {
					const groups = {};
					for (const [key, group] of Object.entries(fields.fields.fieldsetGroups || {})) {
						groups[key] = { ...group, sets: (group.sets || []).filter((t) => t !== 'fwemail') };
					}
					fields.fields = { ...fields.fields, fieldsetGroups: groups };
				}
			}
			// an upload field's "attach to the mail": only when the form mails
			if (this.tab === 'fields' && fields.fields && !this.actions.includes('mail')) {
				const fieldsets = { ...(fields.fields.fieldsets || {}) };
				if (fieldsets.fwfile) {
					const tabs = {};
					for (const [key, tab] of Object.entries(fieldsets.fwfile.tabs || {})) {
						const own = { ...(tab.fields || {}) };
						delete own.attach;
						tabs[key] = { ...tab, fields: own };
					}
					fieldsets.fwfile = { ...fieldsets.fwfile, tabs };
					fields.fields = { ...fields.fields, fieldsets };
				}
			}
			if (this.tab === 'actions') {
				const actions = this.actions;
				const drop = (...names) => names.forEach((n) => delete fields[n]);
				// (stored: where the submissions are and how long they stay)
				if (actions.includes('store') && fields.action) fields.action = { ...fields.action, help: this.form.storeInfo };
				if (!actions.includes('mail')) drop('headlinemail', 'recipients', 'mailsubject');
				else if (fields.recipients) fields.recipients = { ...fields.recipients, required: true };
				if (!actions.includes('webhook')) drop('headlinewebhook', 'webhookurl');
				else if (fields.webhookurl) fields.webhookurl = { ...fields.webhookurl, required: true };
			}
			// the confirmation: to the form's e-mail field – without one not shown
			if (this.tab === 'after' && !(this.values.fields || []).some((b) => b && b.type === 'fwemail')) {
				for (const name of ['confirm', 'confirmcopy', 'confirmsubject', 'confirmtext']) delete fields[name];
			}
			return fields;
		},
		// what happens with a submission (the actions' checkboxes)
		actions() {
			const raw = this.values.action;
			return Array.isArray(raw) ? raw : String(raw || '').split(',').map((a) => a.trim()).filter(Boolean);
		},
		// the send button: [auto|full, left|center|right]
		buttonLayout() {
			const align = this.values.buttonalign;
			return [this.values.buttonwidth === 'full' ? 'full' : 'auto', ['center', 'right'].includes(align) ? align : 'left'];
		},
		captchaOn() {
			return this.values.captcha === true || this.values.captcha === 'true';
		},
		titleValue() {
			return this.form ? this.form.title : '';
		},
		newFields() {
			return {
				title: { name: 'title', type: 'text', label: this.$t('formwizard.form.name'), required: true, autofocus: true },
				template: {
					name: 'template',
					type: 'radio',
					label: this.$t('formwizard.tpl'),
					options: ['contact', 'contactfull', 'callback', 'application', 'registration', 'project', ''].map((t) => ({ value: t, text: this.$t('formwizard.tpl.' + (t || 'empty')), info: t ? this.$t('formwizard.tpl.' + t + '.info') : null }))
				}
			};
		},
		settingsTabs() {
			return [
				{ key: 'forms', label: this.$t('formwizard.forms'), icon: 'formwizard' },
				{ key: 'spam', label: this.$t('formwizard.spam'), icon: 'shield' },
				...(this.pwMail ? [{ key: 'mail', label: this.$t('prw.tab.mail'), icon: 'email' }] : [])
			];
		},
		// the overview's settings, by tab
		retentionFields() {
			const { retention } = this.settingsFields;
			return { retention: { ...retention, width: '1/3' } };
		},
		spamFields() {
			const fields = { ...this.settingsFields };
			delete fields.retention;
			delete fields.headlineSpam;
			return fields;
		},
		settingsFields() {
			const secretHelp = {
				stored: this.$t('formwizard.captcha.secret.stored'),
				unreadable: this.$t('formwizard.captcha.secret.unreadable'),
				none: this.$t('formwizard.captcha.secret.none')
			}[this.settings?.captchaSecretState || 'none'];
			return {
				headlineSpam: { name: 'headlineSpam', type: 'headline', label: this.$t('formwizard.spam') },
				captcha: {
					name: 'captcha',
					type: 'select',
					label: this.$t('formwizard.captcha'),
					// (always one: the honeypot without a provider)
					required: true,
					empty: false,
					help: this.$t(this.settings && this.settings.captcha && this.settings.captcha !== 'none' ? 'formwizard.captcha.help' : 'formwizard.spam.always'),
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
							captchaSiteKey: { name: 'captchaSiteKey', type: 'text', label: this.$t('formwizard.captcha.sitekey') },
							captchaSecret: { name: 'captchaSecret', type: 'password', label: this.$t('formwizard.captcha.secret'), help: secretHelp, autocomplete: 'new-password' }
						}
					: {}),
				rate: { name: 'rate', type: 'toggle', label: this.$t('formwizard.rate'), text: [this.$t('formwizard.off'), this.$t('formwizard.on')], help: this.$t('formwizard.rate.help') },
				rateLimit: { name: 'rateLimit', type: 'number', label: this.$t('formwizard.rate.limit'), min: 1, max: 100, width: '1/2', when: { rate: true } },
				rateMinutes: { name: 'rateMinutes', type: 'number', label: this.$t('formwizard.rate.minutes'), min: 1, max: 1440, width: '1/2', after: this.$t('formwizard.rate.minutes.unit'), when: { rate: true } },
				retention: {
					name: 'retention',
					type: 'select',
					label: this.$t('formwizard.settings.retention'),
					help: this.$t('formwizard.settings.retention.help'),
					required: true,
					empty: false,
					options: [30, 90, 180, 365, 0].map((d) => ({ value: d, text: d ? this.$t('formwizard.settings.retention.days', { days: d }) : this.$t('formwizard.settings.retention.never') }))
				}
			};
		}
	},
	watch: {
		// (the fields for the field list and the conditions' choices)
		'values.fields': {
			immediate: true,
			handler(fields) {
				fwEditor.blocks = Array.isArray(fields) ? fields : [];
			}
		},
		pending: {
			immediate: true,
			handler(count) {
				this.$emit('pending', count);
			}
		},
		// the design: opened once, then kept (its changes stay while switching tabs)
		tab(tab) {
			if (tab === 'design') this.designOpened = true;
		},
		// the Panel in another language (the dropdown in the title row): the
		// form loaded again in it – else its fields still show the old one's
		'$panel.language.code'(code, old) {
			if (old && code !== old && this.mode === 'form') this.load();
		},
		view: {
			immediate: true,
			handler(view, old) {
				// (back to the form after "keep editing": nothing to load)
				if (this._restoring) {
					this._restoring = false;
					return;
				}
				// unsaved changes: asked first – keep editing goes back
				if (this.pending && old !== undefined) {
					this.askLeave().then((leave) => {
						if (leave) return this.load();
						this._restoring = true;
						this.$emit('navigate', old);
					});
					return;
				}
				this.load();
			}
		}
	},
	mounted() {
		this._unload = (e) => {
			if (this.pending) e.preventDefault();
		};
		window.addEventListener('beforeunload', this._unload);
	},
	beforeDestroy() {
		window.removeEventListener('beforeunload', this._unload);
		this.$emit('pending', 0);
		fwEditor.blocks = [];
	},
	methods: {
		// a tab of the overview (the mail server's values loaded when first shown)
		openSettingsTab(key) {
			this.settingsTab = key;
			if (key === 'mail' && this.pwMail) this.pwMail.load();
		},
		// unsaved changes and another page: asked first – true when they are
		// discarded (then gone), false for keep editing
		askLeave() {
			if (!this.pending) return Promise.resolve(true);
			return new Promise((resolve) => {
				let decided = false;
				const decide = (leave) => {
					if (decided) return;
					decided = true;
					if (leave) {
						this.$panel.dialog.close();
						this.discard();
					}
					resolve(leave);
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
							submit: () => decide(true),
							cancel: () => decide(false),
							close: () => decide(false)
						}
					}
				);
			});
		},
		language() {
			return this.$panel.language?.code || this.languages[0]?.code || '';
		},
		// another language: for the whole Panel, as Kirby's dropdown on pages
		// (the form opened again in it) – asked first with unsaved changes
		switchLanguage(code) {
			this.$refs.languages?.close();
			if (code === this.language()) return;
			this.askLeave().then((leave) => {
				if (leave) this.$go('projectwizard', { query: { tab: 'forms', view: 'form:' + this.slug, language: code } });
			});
		},
		async load() {
			this.$emit('preview', this.mode === 'form');
			if (this.mode === 'settings') {
				this.$emit('title', { text: this.$t('formwizard.forms'), icon: 'formwizard' });
				[this.list, this.settings] = await Promise.all([this.$api.get('formwizard/pw/forms'), this.$api.get('formwizard/pw/settings')]);
				this.settingsSaved = JSON.stringify(this.settings);
				if (this.view === 'new') this.createDialog();
			} else if (this.mode === 'form') {
				this.form = null;
				this.tab = 'fields';
				try {
					this.take(await this.$api.get('formwizard/pw/forms/' + this.slug, { language: this.language() }));
				} catch (e) {
					this.$emit('navigate', 'settings');
				}
			}
		},
		take(form) {
			this.form = form;
			this.values = { ...form.values };
			this.saved = JSON.stringify(this.values);
			this.version++;
			// (its name renamed by a click on it, as on Kirby's pages)
			this.$emit('title', { text: form.title, icon: 'formwizard', rename: true });
		},
		onInput(values) {
			this.values = { ...this.values, ...values };
		},
		// every change of the page back (the wizard's discard button, leaving)
		discard() {
			if (this.formPending) {
				this.values = JSON.parse(this.saved);
				this.version++;
			}
			// (the design: the preview back to what is saved)
			if (this.$refs.design) this.$refs.design.discard();
			if (this.settingsPending) this.settings = JSON.parse(this.settingsSaved);
		},
		// every change of the page saved (the wizard's save button, which
		// reports success): false when something failed (its message shown)
		async save() {
			if (this.busy) return false;
			this.busy = true;
			let ok = true;
			try {
				if (this.formPending) {
					try {
						this.take(await this.$api.post('formwizard/pw/forms/' + this.slug, { values: this.values, language: this.language() }));
						this.$emit('changed');
					} catch (e) {
						// (e.g. mailed without a recipient: the server's message)
						this.$panel.notification.error(e);
						ok = false;
					}
				}
				if (this.designPending && this.$refs.design) {
					ok = (await this.$refs.design.save()) && ok;
				}
				if (this.settingsPending) {
					try {
						this.settings = await this.$api.post('formwizard/pw/settings', this.settings);
						this.settingsSaved = JSON.stringify(this.settings);
					} catch (e) {
						this.$panel.notification.error(e);
						ok = false;
					}
				}
			} finally {
				this.busy = false;
			}
			return ok;
		},
		// a fixed row's settings (the captcha, the send button): Kirby's drawer
		// with the form's own fields, changed as the others
		openFixed(names, icon) {
			const defs = this.form.tabs.fields || {};
			const fields = {};
			for (const name of names) {
				if (defs[name]) fields[name] = { ...defs[name], width: '1/1' };
			}
			if (fields.buttonalign) fields.buttonalign.when = { buttonwidth: 'auto' };
			this.$panel.drawer.open({
				component: 'k-form-drawer',
				props: {
					icon,
					title: names[0] === 'captcha' ? this.$t('formwizard.captcha') : this.$t('formwizard.button'),
					fields,
					// (older forms without the button's width: its start values)
					value: Object.fromEntries(names.map((n) => {
						const start = { buttonwidth: this.buttonLayout[0], buttonalign: this.buttonLayout[1] }[n];
						return [n, (this.values[n] === '' || this.values[n] == null) && start ? start : this.values[n]];
					}))
				},
				on: {
					input: (value) => this.onInput(value)
				}
			});
		},
		// a new form: Kirby's dialog (name, start fields) – then its editor
		createDialog() {
			this.$panel.dialog.open(
				{
					component: 'k-form-dialog',
					props: {
						fields: this.newFields,
						value: { title: '', template: 'contact' },
						submitButton: { text: this.$t('formwizard.form.create'), icon: 'check', theme: 'positive' }
					}
				},
				async (value) => {
					const title = String((value && value.title) || '').trim();
					if (!title) return;
					try {
						const { slug } = await this.$api.post('formwizard/pw/forms', { title, template: (value && value.template) || '' });
						this.$panel.dialog.close();
						this.list = await this.$api.get('formwizard/pw/forms');
						this.syncMenu();
						this.$emit('changed');
						this.$emit('navigate', 'form:' + slug);
					} catch (e) {
						this.$panel.notification.error(e);
					}
				}
			);
		},
		// the form renamed (a click on its name above): Kirby's dialog, the
		// name in the language shown – unsaved changes stay
		rename() {
			if (!this.form) return;
			this.$panel.dialog.open(
				{
					component: 'k-form-dialog',
					props: {
						fields: { title: { label: this.$t('formwizard.form.name'), type: 'text', required: true, autofocus: true } },
						value: { title: this.form.title },
						submitButton: { text: this.$t('rename'), icon: 'check', theme: 'positive' }
					}
				},
				async (value) => {
					const title = String((value && value.title) || '').trim();
					if (!title) return;
					const { title: saved } = await this.$api.post('formwizard/pw/forms/' + this.slug + '/title', { title, language: this.language() });
					this.$panel.dialog.close();
					this.form = { ...this.form, title: saved };
					// (its entry in Kirby's menu with the new name, without reloading)
					const menu = this.$panel.menu;
					if (menu && Array.isArray(menu.entries) && this.form.folder) {
						menu.entries = menu.entries.map((e) => (e && typeof e.link === 'string' && e.link.includes('form=' + encodeURIComponent(this.form.folder)) ? { ...e, text: saved } : e));
					}
					this.$emit('title', { text: saved, icon: 'formwizard', rename: true });
					this.$emit('changed');
				}
			);
		},
		// a form deleted (from the list): asked first
		remove(form) {
			this.$panel.dialog.open(
				{
					component: 'k-remove-dialog',
					props: { text: this.$t('formwizard.form.delete.confirm', { title: form.title }) }
				},
				async () => {
					await this.$api.delete('formwizard/pw/forms/' + form.slug);
					this.$panel.dialog.close();
					this.$panel.notification.success();
					this.$emit('changed');
					this.list = await this.$api.get('formwizard/pw/forms');
					this.syncMenu();
				}
			);
		},
		// Kirby's menu: an entry per form that stores (as the server makes
		// them) – new and deleted ones at once, without reloading
		syncMenu() {
			const menu = this.$panel.menu;
			if (!menu || !Array.isArray(menu.entries) || !Array.isArray(this.list)) return;
			const isForm = (e) => e && typeof e.link === 'string' && e.link.includes('formwizard/submissions?form=');
			const template = menu.entries.find(isForm) || { icon: 'email' };
			let at = menu.entries.findIndex(isForm);
			const rest = menu.entries.filter((e) => !isForm(e));
			// (none there yet: before the Project Wizard's entry, else at the end)
			if (at < 0) at = rest.findIndex((e) => e && typeof e.link === 'string' && e.link.endsWith('projectwizard'));
			if (at < 0) at = rest.length;
			const forms = this.list.filter((f) => f.stores && f.folder).map((f) => ({
				...template,
				text: f.title,
				link: 'formwizard/submissions?form=' + encodeURIComponent(f.folder),
				current: false,
				badge: f.new ? { text: String(f.new), theme: 'notice' } : undefined
			}));
			menu.entries = [...rest.slice(0, at), ...forms, ...rest.slice(at)];
		}
	}
};
</script>

<style>
/* the overview's tabs: the whole width; the retention below the forms */
.fw-pw {
	container-type: inline-size;
}
.fw-pw-settings {
	margin-top: var(--spacing-8);
}
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
.fw-pw-portal {
	display: contents;
}
/* the fields: added with the button above only (the captcha and the send
   button close the list) */
.fw-pw .k-field-name-fields > footer > .k-button {
	display: none;
}
/* the fixed rows below the fields: as a field's row, a line above them */
.fw-pw-fixed {
	display: flex;
	flex-direction: column;
	gap: var(--spacing-1, 0.25rem);
	margin-top: var(--spacing-3);
	padding-top: var(--spacing-3);
	border-top: 1px dashed var(--color-border, var(--color-gray-300));
}
.fw-pw-fixed-row {
	background: var(--color-white, #fff);
	border-radius: var(--rounded);
	box-shadow: var(--shadow);
}
.fw-pw-fixed-row .fw-field {
	min-height: 3.25rem;
}
.fw-pw-language {
	margin-left: auto;
}
.fw-pw-options {
	width: 1%;
	text-align: end;
}
.fw-pw-note {
	margin-top: var(--spacing-6);
}
.fw-pill[data-status="active"] {
	background: color-mix(in srgb, var(--color-positive, #1a7f37) 18%, transparent);
	color: var(--color-positive, #1a7f37);
}
</style>
