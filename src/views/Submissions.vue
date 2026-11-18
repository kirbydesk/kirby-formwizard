<template>
	<!-- Form Wizard › Submissions: all submissions of all forms, filtered
	     by form, status, a search; a click opens one -->
	<k-panel-inside class="k-formwizard-submissions-view">
		<k-header>
			{{ formName || $t('formwizard.submissions') }}
			<template #buttons>
				<k-button icon="download" variant="filled" size="sm" :link="csv" :text="$t('formwizard.submissions.csv')" :disabled="!pagination.total" />
			</template>
		</k-header>

		<div class="fw-subs-filter">
			<select v-if="!formName" :value="filter.form" @change="go({ form: $event.target.value })">
				<option value="">{{ $t('formwizard.submissions.allForms') }}</option>
				<option v-for="form in forms" :key="form.value" :value="form.value">{{ form.text }}</option>
			</select>
			<select :value="filter.status" @change="go({ status: $event.target.value })">
				<option value="">{{ $t('formwizard.submissions.allStatus') }}</option>
				<option value="new">{{ $t('formwizard.status.new') }}</option>
				<option value="done">{{ $t('formwizard.status.done') }}</option>
			</select>
			<input v-model="search" type="search" :placeholder="$t('search') + ' …'" @keydown.enter="go({ q: search })" @search="go({ q: search })" />
		</div>

		<k-empty v-if="!items.length" icon="email" layout="cards">
			{{ filtered ? $t('formwizard.submissions.none') : $t('formwizard.submissions.empty') }}
		</k-empty>

		<table v-else class="fw-subs-table">
			<thead>
				<tr>
					<th>{{ $t('formwizard.submission.date') }}</th>
					<th>{{ $t('formwizard.submission.from') }}</th>
					<th class="fw-subs-summary">{{ $t('formwizard.submission.about') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="item in items" :key="item.id" :class="{ 'is-new': item.status === 'new' }" @click="$go('formwizard/submissions/' + item.id)">
					<td class="fw-subs-date">{{ item.date }}</td>
					<td>
						<span class="fw-subs-title">{{ item.title }}</span>
						<small v-if="!filter.form">{{ item.form }}</small>
					</td>
					<td class="fw-subs-summary">{{ item.summary }}<k-icon v-if="item.files" type="attachment" class="fw-subs-file" /></td>
					<td class="fw-subs-status"><span class="fw-pill" :data-status="item.status">{{ $t('formwizard.status.' + item.status) }}</span></td>
				</tr>
			</tbody>
		</table>

		<footer class="fw-subs-footer">
			<k-text class="k-help">{{ retention ? $t('formwizard.submissions.retention', { days: retention }) : '' }}</k-text>
			<k-pagination v-if="pagination.total > pagination.limit" v-bind="pagination" :details="true" @paginate="go({ page: $event.page }, true)" />
		</footer>
	</k-panel-inside>
</template>

<script>
export default {
	props: {
		items: { type: Array, default: () => [] },
		forms: { type: Array, default: () => [] },
		filter: { type: Object, default: () => ({}) },
		formName: { type: String, default: null },
		pagination: { type: Object, default: () => ({ page: 1, limit: 30, total: 0 }) },
		retention: { type: Number, default: 0 },
		csv: { type: String, default: '' }
	},
	data() {
		return {
			search: this.filter.q || ''
		};
	},
	computed: {
		filtered() {
			return !!(this.filter.form || this.filter.status || this.filter.q);
		}
	},
	methods: {
		// the filters in the address: a new filter starts at page 1
		go(changes, keepPage = false) {
			const query = { ...this.filter, ...changes };
			if (!keepPage) delete query.page;
			Object.keys(query).forEach((key) => (query[key] === '' || query[key] === undefined) && delete query[key]);
			this.$go('formwizard/submissions', { query });
		}
	}
};
</script>

<style>
.fw-subs-filter {
	display: flex;
	flex-wrap: wrap;
	gap: var(--spacing-2);
	margin-bottom: var(--spacing-4);
}
.fw-subs-filter :is(select, input) {
	font: inherit;
	font-size: var(--text-sm);
	height: var(--input-height, 2.25rem);
	padding: 0 var(--spacing-3);
	border: 0;
	border-radius: var(--rounded);
	background: var(--input-color-back, var(--color-white));
	box-shadow: var(--shadow-sm, var(--shadow));
	color: var(--color-text);
}
.fw-subs-filter select {
	width: auto;
	flex: 0 0 auto;
	max-width: 16rem;
}
.fw-subs-filter input {
	width: auto;
	flex: 1 1 10rem;
	min-width: 10rem;
}
.fw-subs-table {
	width: 100%;
	border-collapse: collapse;
	background: var(--color-white);
	border-radius: var(--rounded);
	box-shadow: var(--shadow);
	font-size: var(--text-sm);
	overflow: hidden;
}
.fw-subs-table th {
	text-align: start;
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	font-weight: normal;
	color: var(--color-gray-600);
	padding: var(--spacing-2) var(--spacing-3);
}
.fw-subs-table td {
	padding: var(--spacing-2) var(--spacing-3);
	border-top: 1px solid var(--color-gray-200);
	vertical-align: middle;
}
.fw-subs-table tbody tr {
	cursor: pointer;
}
.fw-subs-table tbody tr:hover td {
	background: var(--color-gray-100);
}
.fw-subs-table tr.is-new .fw-subs-title {
	font-weight: var(--font-bold, 600);
}
.fw-subs-table small {
	display: block;
	font-size: var(--text-xs);
	color: var(--color-gray-600);
}
.fw-subs-date {
	white-space: nowrap;
	color: var(--color-gray-600);
}
.fw-subs-file {
	display: inline-flex;
	margin-inline-start: var(--spacing-1);
	color: var(--color-gray-500);
	vertical-align: middle;
}
.fw-subs-status {
	text-align: end;
}
.fw-pill {
	display: inline-block;
	font-size: var(--text-xs);
	padding: 0 var(--spacing-2);
	border-radius: var(--rounded-lg);
	background: var(--color-gray-200);
	color: var(--color-gray-700);
	white-space: nowrap;
}
.fw-pill[data-status="new"] {
	background: var(--color-orange-400, var(--color-notice));
	color: var(--color-black);
}
.fw-subs-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--spacing-3);
	margin-top: var(--spacing-3);
}
@media (max-width: 40rem) {
	.fw-subs-summary {
		display: none;
	}
}
</style>
