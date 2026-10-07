/**
 * Admin Metaboxes JavaScript for TRS Leads Generator
 * Handles conditional field visibility, WP Media Uploader, and PDF Live Preview.
 */
(function ($) {
	'use strict';

	$(function () {
		var $typeSelect = $('#trs_form_type');
		var $confirmWrap = $('#trs-confirmation-url-wrap');

		// 1. Toggle Confirmation URL field visibility based on form type.
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
			toggleConfirmationUrl();
		}

		// 2. Cover Image Source Switcher.
		var $coverSourceSelect = $('#trs_form_cover_source');
		function toggleCoverSource() {
			var val = $coverSourceSelect.val();
			$('.trs-cover-source-box').hide();
			$('#trs-cover-source-' + val).show();
		}

		if ($coverSourceSelect.length) {
			$coverSourceSelect.on('change', toggleCoverSource);
			toggleCoverSource();
		}

		// Live preview for external image URL input.
		$('#trs_form_cover_external_url').on('input change', function () {
			var url = $.trim($(this).val());
			if (url) {
				$('#trs-cover-ext-preview').attr('src', url).show();
			} else {
				$('#trs-cover-ext-preview').attr('src', '').hide();
			}
		});

		// 3. PDF Source Switcher.
		var $pdfSourceSelect = $('#trs_form_pdf_source');
		function togglePdfSource() {
			var val = $pdfSourceSelect.val();
			$('.trs-pdf-source-box').hide();
			$('#trs-pdf-source-' + val).show();
		}

		if ($pdfSourceSelect.length) {
			$pdfSourceSelect.on('change', togglePdfSource);
			togglePdfSource();
		}

		// 4. Load predefined HTML templates into PDF editor.
		$('.trs-load-tpl-btn').on('click', function (e) {
			e.preventDefault();
			var tplKey = $(this).data('tpl');
			if (typeof trsMetaboxData !== 'undefined' && trsMetaboxData.templates && trsMetaboxData.templates[tplKey]) {
				var currentVal = $.trim($('#trs_form_pdf_content').val());
				if (currentVal.length > 0 && !confirm('¿Deseas reemplazar el contenido actual del editor con esta plantilla?')) {
					return;
				}
				$('#trs_form_pdf_content').val(trsMetaboxData.templates[tplKey]);
			}
		});

		// 5. Live PDF Preview via AJAX.
		var $previewBtn = $('#trs-preview-live-pdf-btn');
		$previewBtn.on('click', function (e) {
			e.preventDefault();
			if (typeof trsMetaboxData === 'undefined') {
				return;
			}

			var content = $('#trs_form_pdf_content').val();
			var originalText = $previewBtn.text();

			$previewBtn.prop('disabled', true).text('⏳ Generando PDF...');

			$.ajax({
				url: trsMetaboxData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'trs_preview_draft_pdf',
					security: trsMetaboxData.nonce,
					post_id: trsMetaboxData.postId,
					content: content
				},
				success: function (res) {
					$previewBtn.prop('disabled', false).text(originalText);
					if (res.success && res.data && res.data.pdf_url) {
						window.open(res.data.pdf_url, '_blank');
					} else {
						alert('Error al generar la vista previa: ' + (res.data ? res.data.message : 'Error desconocido'));
					}
				},
				error: function (xhr, status, error) {
					$previewBtn.prop('disabled', false).text(originalText);
					alert('Error en la solicitud: ' + error);
				}
			});
		});

		// 6. Media Uploader for Cover Image.
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

		// 7. Media Uploader for PDF Resource.
		var pdfFrame;
		$('#trs-select-pdf-btn').on('click', function (e) {
			e.preventDefault();

			if (pdfFrame) {
				pdfFrame.open();
				return;
			}

			pdfFrame = wp.media({
				title: 'Seleccionar Archivo PDF',
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
				$('#trs-pdf-filename').html('📄 ' + (attachment.filename || attachment.title)).show();
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
