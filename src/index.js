import './index.css';
import FieldPreview from './components/FieldPreview.vue';
import pwform from './blocks/Form.vue';
import Submissions from './views/Submissions.vue';
import Submission from './views/Submission.vue';
import Forms from './pw/Forms.vue';
import FormPreview from './pw/FormPreview.vue';

const types = ['fwtext', 'fwemail', 'fwtel', 'fwnumber', 'fwdate', 'fwtextarea', 'fwselect', 'fwradio', 'fwcheckboxes', 'fwfile', 'fwhidden', 'fwinfo', 'fwconsent'];

panel.plugin('kirbydesk/kirby-formwizard', {
	// the fields of a form: one row each
	blocks: {
		// the block: a form on a page
		pwform,
		...Object.fromEntries(types.map((type) => [type, { ...FieldPreview }])),
	},
	components: {
		'k-formwizard-submissions-view': Submissions,
		'k-formwizard-submission-view': Submission,
		// the tab "Forms" in the Project Wizard
		'k-formwizard-pw': Forms,
		'fw-form-preview': FormPreview,
	},
	icons: {
		formwizard: '<path d="M17 2V4H20.0066C20.5552 4 21 4.44495 21 4.9934V21.0066C21 21.5552 20.5551 22 20.0066 22H3.9934C3.44476 22 3 21.5551 3 21.0066V4.9934C3 4.44476 3.44495 4 3.9934 4H7V2H17ZM7 6H5V20H19V6H17V8H7V6ZM9 16V18H7V16H9ZM9 13V15H7V13H9ZM9 10V12H7V10H9ZM17 16V18H11V16H17ZM17 13V15H11V13H17ZM17 10V12H11V10H17ZM15 4H9V6H15V4Z"/>',
	},
});
