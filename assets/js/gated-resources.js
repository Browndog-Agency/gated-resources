(function () {
	var form = document.getElementById('gr-form');
	if (!form) { return; }
	var msg = form.querySelector('.gr-form__msg');
	var btn = form.querySelector('button[type="submit"]');

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		msg.textContent = GR_Front.i18n.submitting;
		btn.disabled = true;

		var fd = new FormData(form);
		fd.append('action', 'gr_submit');
		fd.append('nonce', form.getAttribute('data-nonce'));
		fd.append('page_uri', form.getAttribute('data-page'));
		fd.append('page_name', form.getAttribute('data-pagename'));

		fetch(GR_Front.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					// Reload so the server renders the unlocked viewer.
					window.location.reload();
				} else {
					btn.disabled = false;
					msg.textContent = (res && res.data && res.data.message) ? res.data.message : GR_Front.i18n.error;
					if (window.turnstile) { try { window.turnstile.reset(); } catch (e2) {} }
				}
			})
			.catch(function () {
				btn.disabled = false;
				msg.textContent = GR_Front.i18n.error;
			});
	});
})();
