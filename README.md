# TRS Leads Generator 🚀

Plugin nativo de WordPress para la captación, gestión y distribución inteligente de leads. Diseñado para ser operado tanto por humanos a través del panel de administración como por Agentes de Inteligencia Artificial mediante WP REST API.

---

## 🛠️ Arquitectura y Decisiones Técnicas

- **Entorno:** 100% nativo de WordPress (PHP 8.0+, Gutenberg nativo con bloques/React).
- **Tipos de Contenido (CPT):** Los formularios se registran bajo el Custom Post Type `trs_form` (no público, accesible únicamente mediante shortcode o bloques).
- **Almacenamiento Dedicado:** Tabla SQL personalizada relacional (`{$wpdb->prefix}trs_leads`) optimizada para alto volumen y rendimiento, prescindiendo del post meta para almacenamiento masivo de leads.
- **Seguridad Zero Trust:**
  - Nonces obligatorios (`wp_nonce_field` / `check_admin_referer`) para acciones humanas.
  - Autenticación por Application Passwords / JWT para llamadas de agentes de IA.
  - Sanitización estricta (`sanitize_text_field`, `sanitize_email`, `wp_kses_post`, tipado estricto).
- **Tracking First-Party:** Captura automática de parámetros UTM (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`) persistidos en cookies de primera parte para garantizar la atribución cross-page.
- **Integración Externa:** Conexión nativa con Google Sheets API mediante Service Account (sin dependencias bloqueantes).

---

## 📦 Estructura del Proyecto

```
trs-leads-generator/
├── INSTRUCCIONES_IA.md             # Guía de contexto y prompts para Agentes de IA
├── README.md                       # Documentación del proyecto
├── .gitignore                      # Reglas de exclusión de git
├── trs-leads-generator.php         # Archivo maestro del plugin y hooks de ciclo de vida
├── includes/
│   ├── class-trs-activator.php       # Creación de tablas SQL ({prefix}trs_leads) y CPT
│   ├── class-trs-deactivator.php     # Gestión segura de desactivación
│   ├── class-trs-post-types.php      # Definición y registro del CPT trs_form
│   └── class-trs-leads-generator.php # Orquestador principal de dependencias y hooks
└── admin/
    ├── class-trs-admin.php           # Menús, Settings API y validación de Service Account
    ├── css/
    │   └── trs-admin.css             # Estilos del panel de control
    └── partials/
        ├── trs-admin-dashboard-display.php # Vista del Dashboard
        └── trs-admin-settings-display.php  # Vista del formulario de Ajustes
```

---

## 🚦 Fases de Desarrollo

- [x] **FASE 1: Core, Base de Datos y Panel**
  - Archivo principal y estructura modular OOP.
  - Clase de activación con creación de tabla SQL relacional `trs_leads`.
  - CPT `trs_form` (no público, con soporte de editor y custom fields).
  - Menú de administración "Lead Generation" con Dashicon de funnel.
  - Página de Ajustes con el Settings API para credenciales JSON de Google Service Account.
- [ ] **FASE 2: Constructor, Campos Personalizados y Endpoint IA**
  - Metaboxes para `trs_form` (tipo, captcha, términos, página asociada, portada, PDF, URL confirmación).
  - Endpoint REST `/trs/v1/generate-form` para creación de formularios por IA.
  - Script frontend para captura y persistencia de UTMs en cookies first-party.
- [ ] **FASE 3: Frontend, Procesamiento y Google Sheets**
  - Shortcode `[trs_form id="X"]` con validación CSRF (nonce) y captcha.
  - Endpoint de procesamiento AJAX / REST e inserción en base de datos.
  - Clase de integración y sincronización en tiempo real con Google Sheets.
- [ ] **FASE 4: Módulos de Generación Avanzada (Landing, PDF, Imágenes)**
  - Endpoint REST `/trs/v1/generate-landing` para creación automatizada de páginas de aterrizaje.
  - Integración de Dompdf para generación dinámica de PDFs a partir de plantillas HTML.
  - Selector de plantillas de imagen y documento.
- [ ] **FASE 5: Automatización de Pautas Ads (Meta & Google)**
  - Automatización de campañas publicitarias en Meta Graph API y Google Ads API en estado Borrador/DRAFT.

---

## ⚙️ Instalación y Activación

1. Clona o copia el plugin en la carpeta `/wp-content/plugins/trs-leads-generator/`.
2. Ve al panel de administración de WordPress > **Plugins**.
3. Activa **TRS Leads Generator**.
4. Al activarse, se creará automáticamente la tabla en la base de datos `{$wpdb->prefix}trs_leads` y se registrará el CPT `trs_form`.
5. Accede al menú **Lead Generation > Ajustes** para configurar las credenciales de tu Service Account de Google.
