# TRS Leads Generator 🚀

Plugin nativo de WordPress para la captación, gestión y distribución inteligente de leads. Diseñado para ser operado tanto por humanos a través del panel de administración como por Agentes de Inteligencia Artificial mediante WP REST API.

---

## 🛠️ Arquitectura y Decisiones Técnicas

- **Entorno:** 100% nativo de WordPress (PHP 8.0+, Gutenberg nativo con bloques/React).
- **Tipos de Contenido (CPT):** Los formularios se registran bajo el Custom Post Type `trs_form` (no público, accesible únicamente mediante shortcode o bloques).
- **Almacenamiento Dedicado:** Tabla SQL personalizada relacional (`{$wpdb->prefix}trs_leads`) optimizada para alto volumen y rendimiento, prescindiendo del post meta para almacenamiento masivo de leads.
- **Seguridad Zero Trust:**
  - Nonces obligatorios (`wp_nonce_field` / `check_admin_referer` / `check_ajax_referer`) para acciones humanas.
  - Autenticación por Application Passwords / JWT para llamadas de agentes de IA.
  - Sanitización estricta (`sanitize_text_field`, `sanitize_email`, `wp_kses_post`, tipado estricto).
  - Filtro activo anti-inyección de código ejecutable (`<script>`, `<iframe>`, `eval`, `<?php`, `system()`).
- **Tracking First-Party:** Captura automática de parámetros UTM (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`) persistidos en cookies de primera parte (30 días) para garantizar la atribución cross-page.
- **Integración con Google Sheets:** Conexión nativa con Google Sheets API v4 mediante Service Account (autenticación JWT nativa en PHP, sin dependencias conflictivas).
- **Generación Dinámica de PDFs:** Integración con la librería `Dompdf` mediante Composer para compilar PDFs personalizados en tiempo real con datos de cada lead.
- **Automatización de Pautas Ads:** Generación en borrador (`PAUSED`/`DRAFT`) en Meta Graph API y Google Ads API con revisión humana obligatoria.

---

## 📦 Estructura del Proyecto

```
trs-leads-generator/
├── INSTRUCCIONES_IA.md             # Directrices de arquitectura y contexto para IAs
├── README.md                       # Documentación técnica completa
├── .gitignore                      # Reglas de exclusión de git
├── composer.json                   # Dependencias PHP (Dompdf)
├── trs-leads-generator.php         # Archivo maestro del plugin y hooks de ciclo de vida
├── includes/
│   ├── class-trs-activator.php       # Activación: creación de {$wpdb->prefix}trs_leads y CPT
│   ├── class-trs-deactivator.php     # Gestión segura de desactivación
│   ├── class-trs-post-types.php      # Definición y registro del CPT trs_form
│   ├── class-trs-meta-boxes.php      # Metaboxes seguros (tipo, captcha, portada, PDF, etc.)
│   ├── class-trs-frontend.php        # Shortcode [trs_form], antispam y procesamiento AJAX
│   ├── class-trs-google-sheets.php   # Sincronización con Google Sheets API v4 (Service Account)
│   ├── class-trs-pdf-generator.php   # Compilación HTML a PDF con Dompdf
│   ├── class-trs-ads-automation.php  # Creación de campañas borradores en Meta y Google Ads
│   ├── class-trs-rest-controller.php # Endpoints REST (/generate-form, /generate-landing)
│   └── class-trs-leads-generator.php # Orquestador central del plugin
├── admin/
│   ├── class-trs-admin.php           # Menú, Settings API (Sheets, Meta, Google)
│   ├── css/
│   │   └── trs-admin.css             # Estilos del panel de control y dashboard
│   ├── js/
│   │   └── trs-metaboxes.js          # Visibilidad condicional y selector wp.media
│   └── partials/
│       ├── trs-admin-dashboard-display.php # Vista del panel métricas
│       └── trs-admin-settings-display.php  # Vista del panel de ajustes
└── public/
    ├── css/
    │   └── trs-frontend.css          # Estilos modernos y responsivos del formulario
    └── js/
        ├── trs-tracking.js           # Captura first-party de UTMs en cookies (30 días)
        └── trs-frontend.js           # Envío AJAX, loaders, descargas y redirecciones
```

---

## 🚦 Fases de Desarrollo Completadas (100%)

- [x] **FASE 1: Core, Base de Datos y Panel**
  - Archivo principal y estructura modular OOP.
  - Tabla relacional en la base de datos `{$wpdb->prefix}trs_leads`.
  - CPT `trs_form` (no público, editor y custom fields).
  - Menú de administración "Lead Generation" con Dashicon de funnel.
  - Página de Ajustes con el Settings API para credenciales JSON de Google Service Account.

- [x] **FASE 2: Constructor, Campos Personalizados y Endpoint IA**
  - Metaboxes para `trs_form` (Tipo, Captcha, Términos, Página asociada, Portada, Recurso PDF, URL de confirmación).
  - Lógica JS/PHP para visibilidad condicional de "URL de confirmación" en tipos Performance y Evento.
  - Endpoint REST `/trs/v1/generate-form` exclusivo para Agentes de IA con validación Zero Trust.
  - Script nativo `trs-tracking.js` para persistencia de UTMs en cookies por 30 días y autorelleno de formularios.

- [x] **FASE 3: Frontend, Procesamiento y Google Sheets**
  - Shortcode `[trs_form id="X"]` responsivo con honeypot antispam, captcha y términos.
  - Procesamiento seguro vía AJAX con nonces y sanitización estricta.
  - Inserción en la tabla SQL relacional `trs_leads`.
  - Clase `TRS_Google_Sheets` para sincronización automática con Google Sheets API v4 vía JWT firmado con RS256.

- [x] **FASE 4: Módulos de Generación Avanzada (Landing, PDF, Imágenes)**
  - Endpoint REST `/trs/v1/generate-landing` para crear páginas completas de aterrizaje en WordPress con bloques Gutenberg nativos e incrustación de shortcode.
  - Integración de `Dompdf` vía Composer en `TRS_PDF_Generator`.
  - Plantillas HTML dinámicas (Guía Ejecutiva, Checklist Estratégico, Entrada a Evento) con sustitución de variables `{{first_name}}`, `{{email}}`, `{{date}}`.
  - Descarga instantánea o entrega de recurso PDF al registrar un lead.

- [x] **FASE 5: Automatización de Pautas Ads (Meta & Google)**
  - Clase `TRS_Ads_Automation` invocable desde el metabox del formulario con botón "🚀 Generar Pauta en Borrador".
  - Creación de Campañas, AdSets y Ads en Meta Graph API en estado `PAUSED`.
  - Estructuración de campañas en Google Ads API en estado `DRAFT`.
  - Registro de auditoría en post meta y reporte detallado con requerimiento de aprobación humana previa a la publicación.

---

## 🔌 Uso de Endpoints REST (Para Agentes de IA)

### 1. Generar Formulario: `POST /wp-json/trs/v1/generate-form`
**Headers:**
```http
Authorization: Basic [Application Password Base64]
Content-Type: application/json
```
**Payload Ejemplo:**
```json
{
  "title": "Masterclass de Crecimiento B2B",
  "type": "event",
  "description": "Aprende las estrategias que usan las empresas líderes.",
  "captcha": true,
  "terms": true,
  "confirmation_url": "https://misitio.com/gracias-evento"
}
```

### 2. Generar Landing Page: `POST /wp-json/trs/v1/generate-landing`
**Payload Ejemplo:**
```json
{
  "form_id": 12,
  "page_title": "Webinar Exclusivo B2B",
  "headline": "Acelera tus Ventas con Inteligencia Artificial",
  "subheadline": "Descubre el método paso a paso para duplicar tus conversiones.",
  "bullet_points": [
    "Casos de éxito reales en el sector",
    "Plantillas descargables en PDF",
    "Sesión de preguntas en vivo"
  ],
  "status": "publish"
}
```

---

## 💻 Uso de Shortcodes Modulares en Frontend

El plugin permite desacoplar los componentes para que puedas insertarlos libremente en columnas o áreas distintas de tu diseño:

### 1. Solo Formulario (Inputs, Captcha, Términos y Botón):
```text
[trs_leads_generator_form id="12"]
```

### 2. Solo Imagen de Portada (Componente Visual Responsivo):
```text
[trs_leads_generator_image id="12"]
```

### 3. Componente Combinado (Imagen Superior + Formulario):
```text
[trs_form id="12"]
```

### 🎛️ Campos Seleccionables en el Formulario:
- **Base Obligatoria Fija:** Nombre, Apellido y Correo Electrónico (siempre preseleccionados y obligatorios).
- **Campos Opcionales a Activar en el Panel:**
  - ☑️ Teléfono / WhatsApp (`phone`)
  - ☑️ Institución / Empresa (`company`)
  - ☑️ Cargo / Puesto (`job_title`)
  - ☑️ Mensaje / Comentarios (`message`)
- *Regla de negocio:* Si un campo opcional se activa en el metabox, se vuelve **obligatorio por defecto** para el visitante al enviar el formulario.
- **Atribución Automática:** Todos los formularios detectan parámetros UTM por URL o cookie (`utm_source`, `utm_medium`, etc.) y los adjuntan al lead de forma 100% transparente.
