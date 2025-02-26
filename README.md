# wordpress-octoprint-integration

Cómo implementar y usar el plugin

1. **Crear la estructura de carpetas:**

Crea una carpeta llamada wordpress-octoprint-integration en tu directorio /wp-content/plugins/
Dentro de esta carpeta, crea:

Un archivo principal wordpress-octoprint-integration.php con el código PHP
Una carpeta css con un archivo wpoi-styles.css
Una carpeta js con un archivo wpoi-scripts.js

2. **Configuración:**

Después de activar el plugin en WordPress, ve a Ajustes > OctoPrint
Configura la URL de OctoPrint (por ejemplo, http://localhost:5000 o la IP de tu Raspberry)
Añade la API Key de OctoPrint (puedes obtenerla en la interfaz de OctoPrint > Ajustes > API)

3. **Uso:**

Usa el shortcode `[octoprint_status]` en cualquier página o entrada
Puedes personalizar la visualización con atributos:

[octoprint_status show_temp="true" show_progress="true" show_webcam="true"]
**Características del plugin**

Visualización del estado actual de la impresora
Monitoreo de temperaturas (extrusor y cama)
Progreso de impresión con tiempo estimado
Visualización de la webcam
Controles básicos (Home, Pausar, Reanudar, Cancelar)
Panel de administración para configuración

**Posibles mejoras futuras**

Añadir soporte para subir archivos STL directamente desde WordPress
Implementar un sistema de colas de impresión
Añadir gráficos de temperatura en tiempo real
Soporte para múltiples impresoras
Integración con WooCommerce para servicios de impresión 3D
