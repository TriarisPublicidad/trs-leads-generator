<?php
/**
 * Dynamic HTML to PDF Generator using Dompdf.
 *
 * @package    TRS_Leads_Generator
 * @subpackage TRS_Leads_Generator/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Handles PDF rendering from HTML templates.
 */
class TRS_PDF_Generator {

	/**
	 * Ensure vendor autoloader is loaded.
	 */
	public static function init_autoloader() {
		$autoload = TRS_PLUGIN_DIR . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
	}

	/**
	 * Get predefined HTML templates for Lead Magnets.
	 *
	 * @return array
	 */
	public static function get_templates() {
		return array(
			'executive_whitepaper' => array(
				'title' => __( 'Plantilla: Guía Ejecutiva / Whitepaper', 'trs-leads-generator' ),
				'html'  => self::get_executive_template_html(),
			),
			'strategy_checklist'   => array(
				'title' => __( 'Plantilla: Checklist Estratégico', 'trs-leads-generator' ),
				'html'  => self::get_checklist_template_html(),
			),
			'event_ticket'         => array(
				'title' => __( 'Plantilla: Pase / Entrada a Evento', 'trs-leads-generator' ),
				'html'  => self::get_ticket_template_html(),
			),
		);
	}

	/**
	 * Render and save a PDF file from an HTML string with dynamic lead tags.
	 *
	 * @param string $html_content Raw HTML template.
	 * @param array  $lead_data    Lead details for variable replacement.
	 * @param string $output_name  Desired file name prefix.
	 * @return array|WP_Error Array with 'file_path' and 'file_url' or WP_Error.
	 */
	public static function generate_pdf( $html_content, array $lead_data = array(), $output_name = 'recurso-lead' ) {
		self::init_autoloader();

		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			return new WP_Error( 'dompdf_missing', __( 'La librería Dompdf no está disponible.', 'trs-leads-generator' ) );
		}

		// Replace merge tags (both {{tag}} and {tag} styles).
		$first_name = esc_html( $lead_data['first_name'] ?? 'Cliente' );
		$last_name  = esc_html( $lead_data['last_name'] ?? '' );
		$email      = esc_html( $lead_data['email'] ?? '' );
		$phone      = esc_html( $lead_data['phone'] ?? '' );
		$company    = esc_html( $lead_data['company'] ?? '' );
		$job_title  = esc_html( $lead_data['job_title'] ?? '' );
		$message    = esc_html( $lead_data['message'] ?? '' );
		$date       = esc_html( current_time( 'd/m/Y' ) );
		$form_title = esc_html( $lead_data['form_title'] ?? 'TRS Leads Generator' );

		$tags = array(
			'{{first_name}}' => $first_name,
			'{first_name}'   => $first_name,
			'{{last_name}}'  => $last_name,
			'{last_name}'    => $last_name,
			'{{email}}'      => $email,
			'{email}'        => $email,
			'{{phone}}'      => $phone,
			'{phone}'        => $phone,
			'{{company}}'    => $company,
			'{company}'      => $company,
			'{{job_title}}'  => $job_title,
			'{job_title}'    => $job_title,
			'{{message}}'    => $message,
			'{message}'      => $message,
			'{{date}}'       => $date,
			'{date}'         => $date,
			'{{form_title}}' => $form_title,
			'{form_title}'   => $form_title,
		);
		$parsed_html = str_replace( array_keys( $tags ), array_values( $tags ), $html_content );

		try {
			$options = new Options();
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'defaultFont', 'Helvetica' );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $parsed_html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			$output = $dompdf->output();

			// Prepare storage directory.
			$upload_dir = wp_upload_dir();
			$trs_dir    = $upload_dir['basedir'] . '/trs-leads/pdfs';
			$trs_url    = $upload_dir['baseurl'] . '/trs-leads/pdfs';

			if ( ! wp_mkdir_p( $trs_dir ) ) {
				return new WP_Error( 'dir_creation_failed', __( 'No se pudo crear el directorio de subida para PDFs.', 'trs-leads-generator' ) );
			}

			// Add index.php / .htaccess for directory security.
			if ( ! file_exists( $trs_dir . '/index.php' ) ) {
				file_put_contents( $trs_dir . '/index.php', '<?php // Silence is golden.' );
			}

			$safe_name  = sanitize_file_name( $output_name . '-' . time() . '-' . wp_generate_password( 6, false ) . '.pdf' );
			$file_path  = $trs_dir . '/' . $safe_name;
			$file_url   = $trs_url . '/' . $safe_name;

			file_put_contents( $file_path, $output );

			return array(
				'file_path' => $file_path,
				'file_url'  => $file_url,
				'filename'  => $safe_name,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_generation_exception', $e->getMessage() );
		}
	}

	/**
	 * HTML for executive template.
	 */
	private static function get_executive_template_html() {
		return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; margin: 40px; color: #1a202c; line-height: 1.6; }
  .header { border-bottom: 2px solid #2b6cb0; padding-bottom: 20px; margin-bottom: 30px; }
  h1 { color: #2b6cb0; margin: 0 0 10px 0; font-size: 26px; }
  .meta { color: #718096; font-size: 13px; }
  .box { background: #edf2f7; border-left: 4px solid #2b6cb0; padding: 15px; margin: 25px 0; border-radius: 4px; }
  .footer { margin-top: 50px; border-top: 1px solid #e2e8f0; padding-top: 15px; font-size: 11px; color: #a0aec0; text-align: center; }
</style>
</head>
<body>
  <div class="header">
    <h1>{{form_title}}</h1>
    <div class="meta">Preparado exclusivamente para: <strong>{{first_name}} {{last_name}}</strong> ({{email}}) | Fecha: {{date}}</div>
  </div>
  <div class="box">
    <strong>Resumen Ejecutivo:</strong> Gracias por solicitar este recurso especializado. Este documento contiene las claves y mejores prácticas recopiladas por nuestro equipo de expertos.
  </div>
  <h2>1. Fundamentos y Diagnóstico</h2>
  <p>La optimización de procesos y la adquisición estructurada de clientes potenciales requieren una alineación precisa entre la propuesta de valor y el canal de distribución.</p>
  <h2>2. Pasos de Implementación Inmediata</h2>
  <ul>
    <li>Auditoría de activos digitales y puntos de contacto.</li>
    <li>Estandarización de parámetros UTM para máxima trazabilidad.</li>
    <li>Sincronización automatizada con plataformas CRM y Google Sheets.</li>
  </ul>
  <div class="footer">
    Documento generado automáticamente por TRS Leads Generator. Triaris Publicidad &copy; {{date}}
  </div>
</body>
</html>';
	}

	/**
	 * HTML for checklist template.
	 */
	private static function get_checklist_template_html() {
		return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; margin: 40px; color: #2d3748; line-height: 1.6; }
  h1 { color: #234e52; border-bottom: 2px solid #319795; padding-bottom: 10px; }
  .item { margin: 12px 0; font-size: 14px; }
  .box { background: #e6fffa; border: 1px solid #b2f5ea; padding: 12px; border-radius: 6px; margin: 20px 0; }
  .footer { margin-top: 40px; font-size: 11px; color: #718096; text-align: center; }
</style>
</head>
<body>
  <h1>Checklist de Ejecución Rápida</h1>
  <p>Hola <strong>{{first_name}}</strong>, utiliza esta lista de verificación para auditar tu embudo de ventas:</p>
  <div class="box">
    <strong>Meta del Proyecto:</strong> Máxima conversión y cero pérdida de atribución.
  </div>
  <div class="item">&#9744; 1. Pixel de Meta y Google Tag activos en la Landing Page.</div>
  <div class="item">&#9744; 2. Formulario de captura con validación en tiempo real y captcha activo.</div>
  <div class="item">&#9744; 3. Cookies de primera parte capturando utm_source, utm_medium y utm_campaign.</div>
  <div class="item">&#9744; 4. Sincronización instantánea con Google Sheets vía Service Account.</div>
  <div class="item">&#9744; 5. Mensaje de confirmación o redirección a página de gracias con oferta complementaria.</div>
  <div class="footer">TRS Leads Generator &bull; Checklist de Alto Rendimiento</div>
</body>
</html>';
	}

	/**
	 * HTML for ticket template.
	 */
	private static function get_ticket_template_html() {
		return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; margin: 40px; color: #1a202c; text-align: center; }
  .ticket { border: 2px dashed #4a5568; border-radius: 12px; padding: 30px; background: #f7fafc; margin: 0 auto; max-width: 500px; }
  h1 { color: #805ad5; margin: 0 0 10px 0; }
  .attendee { font-size: 18px; margin: 15px 0; color: #2d3748; }
  .badge { background: #6b46c1; color: white; padding: 6px 14px; border-radius: 20px; font-size: 12px; text-transform: uppercase; font-weight: bold; }
  .footer { margin-top: 25px; font-size: 12px; color: #a0aec0; }
</style>
</head>
<body>
  <div class="ticket">
    <span class="badge">Registro Confirmado</span>
    <h1>{{form_title}}</h1>
    <div class="attendee">
      Asistente: <strong>{{first_name}} {{last_name}}</strong><br>
      Correo: {{email}}
    </div>
    <p>Presenta este comprobante digital o impreso al momento de ingresar a la sesión.</p>
    <div class="footer">Emitido el {{date}} vía TRS Leads Generator</div>
  </div>
</body>
</html>';
	}

	/**
	 * Default base starter HTML for custom PDF text editor.
	 *
	 * @return string
	 */
	public static function get_default_html() {
		return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; margin: 40px; color: #1e293b; line-height: 1.6; }
  .header { border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 25px; }
  h1 { color: #1e3a8a; font-size: 24px; margin: 0 0 10px 0; }
  .meta { color: #64748b; font-size: 13px; }
  .callout { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px; }
  .content { font-size: 14px; margin: 20px 0; }
  .footer { margin-top: 45px; border-top: 1px solid #e2e8f0; padding-top: 12px; font-size: 11px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
  <div class="header">
    <h1>{{form_title}}</h1>
    <div class="meta">Documento preparado para: <strong>{{first_name}} {{last_name}}</strong> ({{email}}) | Fecha: {{date}}</div>
  </div>

  <div class="callout">
    <strong>¡Bienvenido(a) {{first_name}}!</strong><br />
    Gracias por registrarte. Este es tu recurso personalizado listo para su aplicación inmediata.
  </div>

  <div class="content">
    <h2>Información y Contenido Exclusivo</h2>
    <p>Puedes editar este contenido directamente en el panel de administración de tu formulario en WordPress. Utiliza etiquetas dinámicas para personalizar cada PDF que reciban tus contactos.</p>
    <ul>
      <li><strong>Contacto:</strong> {{first_name}} {{last_name}}</li>
      <li><strong>Correo:</strong> {{email}}</li>
      <li><strong>Teléfono:</strong> {{phone}}</li>
      <li><strong>Institución / Empresa:</strong> {{company}}</li>
      <li><strong>Cargo:</strong> {{job_title}}</li>
    </ul>
  </div>

  <div class="footer">
    Documento generado de forma automatizada por TRS Leads Generator &bull; {{date}}
  </div>
</body>
</html>';
	}
}


