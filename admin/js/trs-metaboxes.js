/**
 * Admin Metaboxes JavaScript for TRS Leads Generator
 * Handles conditional field visibility and WP Media Uploader.
 */
(function ($) {
	'use strict';

	$(function () {
		var $typeSelect = $('#trs_form_type');
		var $confirmWrap = $('#trs-confirmation-url-wrap');

		// Toggle Confirmation URL field visibility based on form type.
		function toggleConfirmationUrl() {
			var selectedType = $typeSelect.val();
			if (selectedType === 'performance' || selectedType === 'event') {
				$confirmWrap.slideDown(200);
			} else {
				$confirmWrap.slideUp(200);
			}
		}

		if ($typeSelect.length) {
			$typeSelect.on('change', toggleConfirmationUrl);
			// Initial check on page load.
			toggleConfirmationUrl();
		}

		// Media Uploader for Cover Image.
		var coverFrame;
		$('#trs-select-cover-btn').on('click', function (e) {
			e.preventDefault();

			if (coverFrame) {
				coverFrame.open();
				return;
			}

			coverFrame = wp.media({
				title: 'Seleccionar Imagen de Portada',
				button: {
					text: 'Usar esta imagen'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			coverFrame.on('select', function () {
				var attachment = coverFrame.state().get('selection').first().toJSON();
				$('#trs_form_cover_image').val(attachment.id);
				$('#trs-cover-preview').attr('src', attachment.url).show();
				$('#trs-remove-cover-btn').show();
			});

			coverFrame.open();
		});

		$('#trs-remove-cover-btn').on('click', function (e) {
			e.preventDefault();
			$('#trs_form_cover_image').val('');
			$('#trs-cover-preview').attr('src', '').hide();
			$(this).hide();
		});

		// Media Uploader for PDF Resource.
		var pdfFrame;
		$('#trs-select-pdf-btn').on('click', function (e) {
			e.preventDefault();

			if (pdfFrame) {
				pdfFrame.open();
				return;
			}

			pdfFrame = wp.media({
				title: 'Seleccionar Recurso PDF',
				button: {
					text: 'Usar este PDF'
				},
				multiple: false,
				library: {
					type: 'application/pdf'
				}
			});

			pdfFrame.on('select', function () {
				var attachment = pdfFrame.state().get('selection').first().toJSON();
				$('#trs_form_pdf_resource').val(attachment.id);
				$('#trs-pdf-filename').text(attachment.filename || attachment.title).show();
				$('#trs-remove-pdf-btn').show();
			});

			pdfFrame.open();
		});

		$('#trs-remove-pdf-btn').on('click', function (e) {
			e.preventDefault();
			$('#trs_form_pdf_resource').val('');
			$('#trs-pdf-filename').text('').hide();
			$(this).hide();
		});
	});
})(jQuery);
