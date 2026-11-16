<template>
	<!-- one submission: what arrived, marked new or done, answered,
	     deleted -->
	<k-panel-inside class="k-formwizard-submission-view">
		<k-header>
			{{ submission.title }}
			<template #buttons>
				<k-button icon="angle-left" variant="filled" size="sm" link="formwizard/submissions" :text="$t('formwizard.submissions')" />
				<k-button
					:icon="submission.status === 'new' ? 'check' : 'refresh'"
					variant="filled"
					size="sm"
					:theme="submission.status === 'new' ? 'positive' : null"
					:text="submission.status === 'new' ? $t('formwizard.submission.markDone') : $t('formwizard.submission.markNew')"
					@click="toggle"
				/>
				<k-button v-if="submission.reply" icon="email" variant="filled" size="sm" :link="'mailto:' + submission.reply" :text="$t('formwizard.submission.reply')" />
				<k-button icon="trash" variant="filled" size="sm" theme="negative" :title="$t('delete')" @click="remove" />
			</template>
		</k-header>

		<div class="fw-sub">
			<div class="fw-sub-meta">
				<span class="fw-pill" :data-status="submission.status">{{ $t('formwizard.status.' + submission.status) }}</span>
				<span>{{ submission.date }}</span>
				<span>·</span>
				<k-link v-if="formLink" :to="formLink">{{ submission.form }}</k-link>
				<span v-else>{{ submission.form }}</span>
				<template v-if="submission.page">
					<span>·</span>
					<a :href="submission.page.url" target="_blank">{{ submission.page.title }}</a>
				</template>
				<span v-if="submission.language">· {{ submission.language.toUpperCase() }}</span>
			</div>

			<dl class="fw-sub-fields">
				<template v-for="(field, i) in submission.fields">
					<dt :key="'l' + i">{{ field.label }}</dt>
					<dd :key="'v' + i" :class="{ 'is-long': field.type === 'fwtextarea' }">
						<a v-if="field.type === 'fwemail'" :href="'mailto:' + field.value">{{ field.value }}</a>
						<a v-else-if="field.type === 'fwtel'" :href="'tel:' + field.value.replace(/[^0-9+]/g, '')">{{ field.value }}</a>
						<template v-else>{{ field.value }}</template>
					</dd>
				</template>
			</dl>

			<template v-if="submission.files.length">
				<h3 class="fw-sub-heading">{{ $t('formwizard.submission.files') }}</h3>
				<ul class="fw-sub-files">
					<li v-for="file in submission.files" :key="file.name">
						<k-icon type="attachment" />
						<a v-if="file.url" :href="file.url">{{ file.name }}</a>
						<span v-else>{{ file.name }}</span>
						<small v-if="file.size">{{ file.size }}</small>
					</li>
				</ul>
			</template>

			<template v-if="submission.log.length">
				<h3 class="fw-sub-heading">{{ $t('formwizard.submission.log') }}</h3>
				<ul class="fw-sub-log">
					<li v-for="(entry, i) in submission.log" :key="i" :data-ok="entry.ok !== false">{{ entry.text }}</li>
				</ul>
			</template>
		</div>
	</k-panel-inside>
</template>

<script>
export default {
	props: {
		submission: { type: Object, required: true },
		formLink: { type: String, default: null }
	},
	methods: {
		async toggle() {
			const status = this.submission.status === 'new' ? 'done' : 'new';
			await this.$api.post('formwizard/submissions/' + this.submission.id + '/status', { status });
			// done: back to the list (the next one waits there)
			if (status === 'done') {
				this.$go('formwizard/submissions');
			} else {
				this.$panel.view.reload();
			}
		},
		remove() {
			this.$panel.dialog.open(
				{
					component: 'k-remove-dialog',
					props: { text: this.$t('formwizard.submission.delete.confirm') }
				},
				async () => {
					await this.$api.delete('formwizard/submissions/' + this.submission.id);
					this.$panel.dialog.close();
					this.$panel.notification.success();
					this.$go('formwizard/submissions');
				}
			);
		}
	}
};
</script>

<style>
.fw-sub {
	background: var(--color-white);
	border-radius: var(--rounded);
	box-shadow: var(--shadow);
	padding: var(--spacing-6);
}
.fw-sub-meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--spacing-2);
	font-size: var(--text-sm);
	color: var(--color-gray-600);
	margin-bottom: var(--spacing-6);
}
.fw-sub-meta a {
	color: var(--color-blue-700);
}
.fw-sub-fields {
	display: grid;
	grid-template-columns: minmax(8rem, 1fr) 3fr;
	gap: var(--spacing-3) var(--spacing-6);
}
.fw-sub-fields dt {
	font-size: var(--text-sm);
	color: var(--color-gray-600);
}
.fw-sub-fields dd {
	margin: 0;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}
.fw-sub-fields dd a {
	color: var(--color-blue-700);
}
@media (max-width: 40rem) {
	.fw-sub-fields {
		grid-template-columns: 1fr;
		gap: var(--spacing-1);
	}
	.fw-sub-fields dd {
		margin-bottom: var(--spacing-3);
	}
}
.fw-sub-heading {
	font-size: var(--text-sm);
	font-weight: var(--font-bold, 600);
	margin: var(--spacing-8) 0 var(--spacing-3);
}
.fw-sub-files li,
.fw-sub-log li {
	display: flex;
	align-items: center;
	gap: var(--spacing-2);
	font-size: var(--text-sm);
	padding: var(--spacing-1) 0;
}
.fw-sub-files small {
	color: var(--color-gray-600);
}
.fw-sub-log li::before {
	content: "●";
	color: var(--color-positive);
}
.fw-sub-log li[data-ok="false"]::before {
	color: var(--color-negative);
}
</style>
