<template>
	<!-- Form Wizard › Submissions: all submissions of all forms -->
	<k-panel-inside class="k-formwizard-submissions-view">
		<k-header>{{ $t('formwizard.submissions') }}</k-header>
		<k-empty icon="email" layout="cards">{{ $t('formwizard.submissions.empty') }}</k-empty>
	</k-panel-inside>
</template>
