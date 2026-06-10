(function () {
	var modal = document.getElementById('gr-modal');
	var pendingUrl = '';

	function openModal(url) {
		pendingUrl = url || '';
		modal.hidden = false;
		document.body.classList.add('gr-modal-open');
		var first = modal.querySelector('input[type="text"], input[type="email"]');
		if (first) { first.focus(); }
	}

	function closeModal() {
		modal.hidden = true;
		document.body.classList.remove('gr-modal-open');
	}

	if (modal) {
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('.gr-gate-trigger');
			if (trigger) {
				e.preventDefault();
				openModal(trigger.getAttribute('href'));
				return;
			}
			if (!modal.hidden && e.target.closest('[data-gr-close]')) {
				closeModal();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
		});
	}

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
					if (pendingUrl) {
						// Everything is unlocked now: flip the cards to direct
						// links (covers back/bfcache restores), then open the
						// resource the visitor originally clicked.
						document.querySelectorAll('.gr-gate-trigger').forEach(function (el) {
							el.classList.remove('gr-gate-trigger');
							el.setAttribute('target', '_blank');
							el.setAttribute('rel', 'noopener');
						});
						closeModal();
						window.location.assign(pendingUrl);
					} else {
						window.location.reload();
					}
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
