/**
 * Frontend Form Handling for TRS Leads Generator.
 * Handles AJAX submission, loading state, redirection, and PDF resource downloads.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form.trs-lead-form');

		forms.forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();

				var submitBtn = form.querySelector('.trs-form-submit-btn');
				var messageBox = form.querySelector('.trs-form-message');
				var origBtnText = submitBtn ? submitBtn.innerHTML : '';

				// Reset message state.
				if (messageBox) {
					messageBox.className = 'trs-form-message';
					messageBox.style.display = 'none';
					messageBox.innerHTML = '';
				}

				// Loading state.
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.innerHTML = '<span class="trs-spinner"></span> ' + (submitBtn.getAttribute('data-loading-text') || 'Procesando...');
				}

				var formData = new FormData(form);
				formData.append('action', 'trs_submit_lead');

				var endpointUrl = (window.trs_frontend_obj && window.trs_frontend_obj.ajax_url)
					? window.trs_frontend_obj.ajax_url
					: '/wp-admin/admin-ajax.php';

				fetch(endpointUrl, {
					method: 'POST',
					body: formData,
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (res) {
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.innerHTML = origBtnText;
						}

						if (res && res.success) {
							var data = res.data || {};
							if (messageBox) {
								messageBox.className = 'trs-form-message success';
								messageBox.style.display = 'block';
								messageBox.innerHTML = data.message || '¡Gracias! Tu información ha sido enviada exitosamente.';

								// If Lead Magnet has download URL, append direct link.
								if (data.download_url) {
									var downloadLink = '<p style="margin-top: 10px;"><a href="' + encodeURI(data.download_url) + '" class="button button-primary" target="_blank" rel="noopener noreferrer" style="font-weight: 700;">📥 Descargar Recurso PDF Ahora</a></p>';
									messageBox.innerHTML += downloadLink;

									// Optionally auto-open download in new tab
									window.open(data.download_url, '_blank');
								}
							}

							// Reset input fields except hidden ones.
							form.querySelectorAll('input:not([type="hidden"]):not([type="submit"])').forEach(function (input) {
								if (input.type === 'checkbox') {
									input.checked = false;
								} else {
									input.value = '';
								}
							});

							// Redirect if confirmation URL is provided.
							if (data.redirect_url) {
								setTimeout(function () {
									window.location.href = data.redirect_url;
								}, 1200);
							}
						} else {
							var errorMsg = (res && res.data && res.data.message)
								? res.data.message
								: 'Ocurrió un error al enviar el formulario. Por favor revisa los datos e inténtalo nuevamente.';
							if (messageBox) {
								messageBox.className = 'trs-form-message error';
								messageBox.style.display = 'block';
								messageBox.innerHTML = errorMsg;
							}
						}
					})
					.catch(function (err) {
						console.error('[TRS Leads Generator] Submission error:', err);
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.innerHTML = origBtnText;
						}
						if (messageBox) {
							messageBox.className = 'trs-form-message error';
							messageBox.style.display = 'block';
							messageBox.innerHTML = 'Error de conexión con el servidor. Inténtalo más tarde.';
						}
					});
			});
		});
	});
})();

