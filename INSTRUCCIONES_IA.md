# Contexto del Proyecto: TRS Leads Generator

## Descripción General
Plugin de WordPress nativo para la captación, gestión y distribución de leads. Diseñado para ser operado tanto por humanos (interfaz gráfica) como por Agentes de IA (vía API REST). El enfoque principal es la seguridad, la escalabilidad y la prevención estricta de ejecución de código arbitrario.

## Arquitectura y Decisiones Técnicas
1. **Entorno:** 100% Nativo de WordPress (PHP 8.0+, JS Vanilla/React para Gutenberg).
2. **Tipos de Datos (CPT):** Los formularios se guardan como un Custom Post Type (`trs_form`).
3. **Almacenamiento de Leads:** Se utiliza una tabla relacional personalizada en la base de datos (`{$wpdb->prefix}trs_leads`) para alto rendimiento, NO post meta.
4. **Seguridad (Zero Trust):** 
   - Nonces obligatorios para acciones humanas.
   - Autenticación mediante Application Passwords / JWT para el Agente de IA.
   - Sanitización estricta con `sanitize_text_field`, `wp_kses_post` y validaciones de tipos.
5. **Constructor de Formularios:** Basado en bloques nativos de Gutenberg o ingesta estructurada JSON vía API (para la IA).
6. **Tracking:** UTMs capturadas vía GET y persistidas en cookies (first-party) para evitar pérdida de atribución si el usuario navega antes de convertir.

## Estructura del Formulario (Metadatos del CPT `trs_form`)
- `trs_form_type`: Enum (performance, lead_magnet, event).
- `trs_form_captcha`: Boolean (Activo/Inactivo).
- `trs_form_terms`: Boolean (Requerido).
- `trs_form_associated_page`: URL externa o ID de página interna.
- `trs_form_cover_image`: ID del attachment (Media Library).
- `trs_form_pdf_resource`: ID del attachment (PDF).
- `trs_form_confirmation_url`: URL de redirección (Solo visible/activo si type es 'performance' o 'event').

## Fases del Proyecto
- **FASE 1:** Base, CPTs, Tabla SQL personalizada y Panel de Ajustes (Google Sheets).
- **FASE 2:** Generador de Formularios (Gutenberg + Endpoint IA) y Lógica Frontend (Tracking UTMs).
- **FASE 3:** Procesamiento de Leads (Guardado en BD + Push a Google Sheets) y Seguridad.
- **FASE 4:** Generación de Landing Pages, Imágenes de portada y PDFs.
- **FASE 5:** Integración de APIs publicitarias (Meta/Google Ads para pautas en Draft).
