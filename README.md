# WordPress OctoPrint Integration

Plugin de WordPress para integrar OctoPrint con tu sitio, permitiendo monitorear y controlar tu impresora 3D directamente desde WordPress.

## Instalación

1. **Crear la estructura de carpetas:**

   Crea una carpeta llamada `wordpress-octoprint-integration` en tu directorio `/wp-content/plugins/`
   Dentro de esta carpeta, crea:
   - Un archivo principal `wordpress-octoprint-integration.php` con el código PHP
   - Una carpeta `css` con un archivo `wpoi-styles.css`
   - Una carpeta `js` con los archivos `wpoi-scripts.js` y `wpoi-upload.js`

2. **Activar el plugin:**

   En el panel de administración de WordPress, ve a Plugins y activa "WordPress OctoPrint Integration".

3. **Configuración:**

   Después de activar el plugin, ve a Ajustes > OctoPrint
   Configura:
   - URL de OctoPrint (por ejemplo, http://localhost:5000 o la IP de tu Raspberry)
   - API Key de OctoPrint (puedes obtenerla en la interfaz de OctoPrint > Ajustes > API)

## Uso

### Monitoreo de impresora

Para mostrar el estado de tu impresora, usa el shortcode `[octoprint_status]` en cualquier página o entrada.

Puedes personalizar la visualización con atributos:

```
[octoprint_status show_temp="true" show_progress="true" show_webcam="true"]
```

### Subida e impresión de archivos STL y GCODE

Para permitir a los usuarios subir archivos STL o GCODE directamente a OctoPrint e imprimirlos, usa el shortcode `[octoprint_upload]` en cualquier página o entrada.

Puedes personalizar este shortcode con atributos:

```
[octoprint_upload allow_guest="false" show_files="true"]
```

Donde:
- `allow_guest`: Si se establece como "true", permitirá a usuarios no logueados subir archivos (por defecto es "false")
- `show_files`: Si se establece como "true", mostrará una lista de archivos disponibles en OctoPrint para imprimir (por defecto es "true")

## Funcionalidades

### Monitoreo de impresora (`[octoprint_status]`)
- Visualización del estado actual de la impresora (Operativa, Imprimiendo, Pausada, Desconectada)
- Monitoreo de temperaturas (extrusor y cama)
- Progreso de impresión con tiempo estimado y tiempo transcurrido
- Visualización de la webcam en tiempo real
- Controles básicos:
  - Home (reinicio de ejes)
  - Pausar impresión
  - Reanudar impresión
  - Cancelar impresión

### Subida e impresión de archivos (`[octoprint_upload]`)
- Subida directa de archivos STL y GCODE a OctoPrint
- Opción para imprimir inmediatamente el archivo subido
- Listado de archivos disponibles en OctoPrint
- Funcionalidad para imprimir archivos existentes con un solo clic
- Barra de progreso durante la subida
- Validación de archivos (solo STL y GCODE)
- Mensajes de estado informativos

## Requisitos técnicos

- WordPress 5.0 o superior
- PHP 7.0 o superior
- OctoPrint con API habilitada y configurada
- La impresora 3D debe estar conectada a OctoPrint

## Resolución de problemas

- **No se puede conectar a OctoPrint**: Verifica que la URL y la API Key sean correctas y que OctoPrint esté funcionando.
- **Error al obtener el estado**: Asegúrate de que la impresora esté conectada a OctoPrint y que la API esté habilitada.
- **No se pueden subir archivos**: Verifica que el usuario tenga permisos suficientes y que la ruta de subida sea correcta en OctoPrint.
- **La webcam no muestra imagen**: Comprueba la configuración de la webcam en OctoPrint y asegúrate de que esté accesible desde la URL configurada.

## Integración con otras funcionalidades

El plugin está diseñado para funcionar junto con:

- **WooCommerce**: Puedes integrarlo con una tienda de servicios de impresión 3D, permitiendo a los clientes enviar modelos STL directamente.
- **BuddyPress/bbPress**: Crea comunidades de impresión 3D donde los usuarios pueden compartir y enviar modelos.
- **Membership plugins**: Restringe el acceso a la impresión 3D solo a miembros pagados.

## Próximas mejoras

- Implementar un sistema de colas de impresión
- Añadir gráficos de temperatura en tiempo real
- Soporte para múltiples impresoras
- Panel de administración avanzado con estadísticas
- Notificaciones por email o push cuando se complete una impresión
- Integración con servicios de diseño 3D online

## Créditos

Desarrollado por Pablo Rubio y Miren Esnaola
