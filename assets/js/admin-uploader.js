(function () {
	var root = document.getElementById('gr-upload');
	if (!root) { return; }
	var btn = document.getElementById('gr-pdf-upload-btn');
	var input = document.getElementById('gr-pdf-file');
	var status = document.getElementById('gr-upload-status');

	btn.addEventListener('click', function () {
		if (!input.files.length) { status.textContent = GR_Admin.i18n.choose; return; }
		var fd = new FormData();
		fd.append('action', 'gr_upload_pdf');
		fd.append('nonce', root.getAttribute('data-nonce'));
		fd.append('post_id', root.getAttribute('data-post'));
		fd.append('file', input.files[0]);

		status.textContent = GR_Admin.i18n.uploading;
		btn.disabled = true;

		fetch(GR_Admin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				btn.disabled = false;
				if (res && res.success) {
					status.textContent = GR_Admin.i18n.done + ' ' + res.data.name;
				} else {
					status.textContent = (res && res.data && res.data.message) ? res.data.message : GR_Admin.i18n.error;
				}
			})
			.catch(function () { btn.disabled = false; status.textContent = GR_Admin.i18n.error; });
	});
})();
