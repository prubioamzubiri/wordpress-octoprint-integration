# WordPress OctoPrint Integration

Plugin de WordPress para integrar OctoPrint con tu sitio, permitiendo monitorear y controlar tu impresora 3D directamente desde WordPress.

**Versión actual:** 0.3.3

## Instalación

1. **Método 1: Instalación directa**
   - Descarga el archivo ZIP del plugin desde la página del proyecto
   - En el panel de administración de WordPress, ve a Plugins > Añadir nuevo > Subir plugin
   - Selecciona el archivo ZIP descargado y haz clic en "Instalar ahora"
   - Activa el plugin después de la instalación

2. **Método 2: Instalación manual**
   - Extrae el archivo ZIP del plugin
   - Sube la carpeta `wordpress-octoprint-integration` a tu directorio `/wp-content/plugins/`
   - En el panel de administración de WordPress, ve a Plugins y activa "WordPress OctoPrint Integration"

3. **Configuración:**
   - Después de activar el plugin, ve a Ajustes > OctoPrint
   - Configura:
     - URL de OctoPrint (por ejemplo, http://localhost:5000 o la IP de tu Raspberry)
     - API Key de OctoPrint (puedes obtenerla en la interfaz de OctoPrint > Ajustes > API)
     - Opciones adicionales de visualización y seguridad

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

### Webcam dedicada

Si solo deseas mostrar la webcam de tu impresora sin los otros controles, puedes usar el shortcode `[octoprint_webcam]` en cualquier página o entrada.

Puedes personalizar la visualización con atributos:

```
[octoprint_webcam width="100%" height="480px" refresh_rate="5" controls="true"]
```

Donde:
- `width`: Define el ancho de la visualización de la webcam (por defecto "100%")
- `height`: Define la altura de la visualización (por defecto "480px")
- `refresh_rate`: Define cada cuántos segundos se actualiza la imagen estática (por defecto "5")
- `controls`: Si se establece como "true", muestra controles de rotación y zoom (por defecto "false")
- `stream`: Si se establece como "true", intenta mostrar un stream MJPG en lugar de imágenes estáticas (por defecto "true")

Ejemplos:
```
[octoprint_webcam] 
[octoprint_webcam width="640px" height="480px" stream="false"] 
[octoprint_webcam controls="true" refresh_rate="2"]
```

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
- Servidor web con capacidad para manejar subidas de archivos grandes (ajustar `post_max_size` y `upload_max_filesize` en php.ini si es necesario)

## Resolución de problemas

- **No se puede conectar a OctoPrint**: 
  - Verifica que la URL y la API Key sean correctas
  - Comprueba que OctoPrint esté funcionando y accesible desde el servidor WordPress
  - Asegúrate de que no haya firewalls bloqueando la conexión

- **Error al obtener el estado**: 
  - Asegúrate de que la impresora esté conectada a OctoPrint
  - Verifica que la API esté habilitada en OctoPrint
  - Revisa los logs de OctoPrint para más detalles

- **No se pueden subir archivos**: 
  - Verifica que el usuario tenga permisos suficientes
  - Comprueba que la ruta de subida sea correcta en OctoPrint
  - Revisa los límites de tamaño de subida en PHP y WordPress

- **La webcam no muestra imagen**: 
  - Comprueba la configuración de la webcam en OctoPrint
  - Asegúrate de que la URL de la webcam sea accesible desde el navegador del cliente
  - Verifica que no haya restricciones CORS impidiendo la carga de la imagen

## Integración con otras funcionalidades

El plugin está diseñado para funcionar junto con:

- **WooCommerce**: Puedes integrarlo con una tienda de servicios de impresión 3D, permitiendo a los clientes enviar modelos STL directamente.
- **BuddyPress/bbPress**: Crea comunidades de impresión 3D donde los usuarios pueden compartir y enviar modelos.
- **Membership plugins**: Restringe el acceso a la impresión 3D solo a miembros pagados.

## Changelog

### 0.3.3 (Actual)
- Mejoras en la estabilidad y rendimiento
- Corrección de errores menores
- Shortcode de la webcam añadida

### 0.3.2
- Mejoras en la integración de la API
- Corrección de errores en la subida de archivos

### 0.3.1
- Añadido soporte para múltiples impresoras
- Mejoras en la interfaz de usuario

### 0.3.0
- Implementación inicial de subida de archivos
- Monitoreo básico de impresora

## Créditos

Desarrollado por Pablo Rubio y Miren Esnaola

---

¿Encontraste un error o tienes una sugerencia? Por favor, abre un issue en nuestro repositorio o envía un correo a [prubioam@zubirimanteo.com].
