/* kirby-formwizard: conditions and sending once */
(function () {
	function setup(form) {
		if (form.dataset.formwizardReady) return;
		form.dataset.formwizardReady = '1';
		var conditional = form.querySelectorAll('[data-formwizard-field][data-when]');

		// the values of a field: the checked options, else the text
		function values(key) {
			var out = [];
			form.querySelectorAll('[name="fw[' + key + ']"], [name="fw[' + key + '][]"]').forEach(function (el) {
				if (el.disabled) return;
				if (el.type === 'radio' || el.type === 'checkbox') {
					if (el.checked) out.push(el.value);
				} else if (el.value !== '') {
					out.push(el.value);
				}
			});
			return out;
		}

		// in their order: a condition on a hidden field hides this one too
		function update() {
			conditional.forEach(function (field) {
				var show = values(field.dataset.when).indexOf(field.dataset.whenValue) > -1;
				field.hidden = !show;
				field.querySelectorAll('input, select, textarea').forEach(function (el) {
					el.disabled = !show;
				});
			});
		}

		form.addEventListener('change', update);
		form.addEventListener('input', update);
		update();

		// sending once: the button waits for the answer
		form.addEventListener('submit', function () {
			var button = form.querySelector('[type="submit"]');
			if (button) setTimeout(function () { button.disabled = true; }, 0);
		});
		window.addEventListener('pageshow', function () {
			var button = form.querySelector('[type="submit"]');
			if (button) button.disabled = false;
		});
	}

	function init() {
		document.querySelectorAll('form[data-formwizard]').forEach(setup);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
