<?php
/**
 * Plugin Name: WordPress OctoPrint Integration
 * Plugin URI: https://example.com/wordpress-octoprint
 * Description: Integra OctoPrint con WordPress para monitorear y controlar tu impresora 3D
 * Version: 0.3
 * Author: Pablo Rubio, Miren Esnaola
 * License: GPL-2.0+
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_OctoPrint_Integration {
    
    // URL base de OctoPrint
    private $octoprint_url;
    
    // API Key de OctoPrint
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Inicializar opciones
        $this->octoprint_url = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        $this->api_key = get_option('wpoi_api_key', '');
        
        // Registrar hooks para admin y front-end
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Registrar shortcodes
        add_shortcode('octoprint_status', array($this, 'octoprint_status_shortcode'));
        add_shortcode('octoprint_upload', array($this, 'octoprint_upload_shortcode'));
        
        // Registrar API endpoints de WordPress para comunicarse con OctoPrint
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Manejar la subida de archivos STL y GCODE
        add_action('wp_ajax_wpoi_upload_stl', array($this, 'handle_stl_upload'));
        add_action('wp_ajax_nopriv_wpoi_upload_stl', array($this, 'handle_stl_upload_nopriv'));
        
        // Añadir soporte para subir archivos STL y GCODE
        add_filter('upload_mimes', array($this, 'add_stl_mime_type'));
        add_filter('wp_check_filetype_and_ext', array($this, 'check_filetype_and_ext'), 10, 5);
    }
    
    /**
     * Añadir tipo MIME STL y GCODE
     */
    public function add_stl_mime_type($mimes) {
        $mimes['stl'] = 'application/sla';
        $mimes['gcode'] = 'text/x.gcode';
        return $mimes;
    }
    
    /**
     * Verificar tipo de archivo STL y GCODE
     */
    public function check_filetype_and_ext($data, $file, $filename, $mimes, $real_mime = '') {
        if (preg_match('/\.stl$/i', $filename)) {
            $data['ext'] = 'stl';
            $data['type'] = 'application/sla';
        } else if (preg_match('/\.gcode$/i', $filename)) {
            $data['ext'] = 'gcode';
            $data['type'] = 'text/x.gcode';
        }
        return $data;
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            'OctoPrint Integration',
            'OctoPrint',
            'manage_options',
            'wordpress-octoprint',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Registrar ajustes
     */
    public function register_settings() {
        register_setting('wpoi_settings', 'wpoi_octoprint_url');
        register_setting('wpoi_settings', 'wpoi_api_key');
    }
    
    /**
     * Renderizar página de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>WordPress OctoPrint Integration</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wpoi_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">URL de OctoPrint</th>
                        <td>
                            <input type="text" name="wpoi_octoprint_url" value="<?php echo esc_attr($this->octoprint_url); ?>" class="regular-text" />
                            <p class="description">Ejemplo: http://localhost:5000 o http://dirección-ip:5000</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="wpoi_api_key" value="<?php echo esc_attr($this->api_key); ?>" class="regular-text" />
                            <p class="description">Obtenido desde la interfaz de OctoPrint en Ajustes > API</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Prueba de conexión</h2>
            <button id="wpoi-test-connection" class="button button-secondary">Probar conexión</button>
            <div id="wpoi-connection-result"></div>
            
            <h2>Uso</h2>
            <p>Utiliza el shortcode <code>[octoprint_status]</code> para mostrar el estado de tu impresora en cualquier página o entrada.</p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wpoi-test-connection').on('click', function(e) {
                e.preventDefault();
                $('#wpoi-connection-result').html('Probando conexión...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpoi_test_connection'
                    },
                    success: function(response) {
                        $('#wpoi-connection-result').html(response);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts() {
        wp_enqueue_style('wpoi-styles', plugin_dir_url(__FILE__) . 'css/wpoi-styles.css');
        wp_enqueue_script('wpoi-scripts', plugin_dir_url(__FILE__) . 'js/wpoi-scripts.js', array('jquery'), '1.0.0', true);
        
        // Añadir script específico para subida de STL
        wp_enqueue_script('wpoi-upload', plugin_dir_url(__FILE__) . 'js/wpoi-upload.js', array('jquery'), '1.0', true);
        
        // Pasar variables a JavaScript
        wp_localize_script('wpoi-scripts', 'wpoi', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wpoi/v1/'),
            'octoprint_url' => $this->octoprint_url,
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        register_rest_route('wpoi/v1', '/printer', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_printer_status'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('wpoi/v1', '/job', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_job_status'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('wpoi/v1', '/command', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_command'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Añadir nueva ruta para subir archivos
        register_rest_route('wpoi/v1', '/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_file_to_octoprint'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Añadir ruta para imprimir un archivo existente
        register_rest_route('wpoi/v1', '/print', array(
            'methods' => 'POST',
            'callback' => array($this, 'print_file'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Añadir ruta para listar archivos
        register_rest_route('wpoi/v1', '/files', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_files_list'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Añadir endpoint POST para controlar trabajos (pause, resume, cancel)
        register_rest_route('wpoi/v1', '/job', array(
            'methods' => 'POST',
            'callback' => array($this, 'control_job'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * Obtener estado de la impresora desde OctoPrint
     */
    public function get_printer_status() {
        $response = $this->request_octoprint('api/printer');
        return rest_ensure_response($response);
    }
    
    /**
     * Obtener estado del trabajo actual
     */
    public function get_job_status() {
        $response = $this->request_octoprint('api/job');
        return rest_ensure_response($response);
    }
    
    /**
     * Enviar comando a OctoPrint
     */
    public function send_command($request) {
        $command = $request->get_param('command');
        $params = $request->get_param('params');
        
        $data = array(
            'command' => $command
        );
        
        if (!empty($params)) {
            $data['parameters'] = $params;
        }
        
        $response = $this->request_octoprint('api/printer/command', 'POST', $data);
        return rest_ensure_response($response);
    }
    
    /**
     * Listar archivos disponibles en OctoPrint
     */
    public function get_files_list($request) {
        $response = $this->request_octoprint('api/files');
        return rest_ensure_response($response);
    }
    
    /**
     * Subir archivo a OctoPrint
     */
    public function upload_file_to_octoprint($request) {
        $file_data = $request->get_file_params();
        
        if (empty($file_data['file']) || !is_uploaded_file($file_data['file']['tmp_name'])) {
            return new WP_Error('no_file', 'No se proporcionó un archivo válido', array('status' => 400));
        }
        
        $tmp_file = $file_data['file']['tmp_name'];
        $file_name = sanitize_file_name($file_data['file']['name']);
        
        // Enviar archivo a OctoPrint
        $url = trailingslashit($this->octoprint_url) . 'api/files/local';
        
        $boundary = wp_generate_password(24);
        $headers = array(
            'X-Api-Key' => $this->api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        
        $payload = '';
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
        $payload .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
        $payload .= file_get_contents($tmp_file) . "\r\n";
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="select"' . "\r\n\r\n";
        $payload .= 'true' . "\r\n";
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="print"' . "\r\n\r\n";
        
        // Si se solicita impresión inmediata
        $print_immediately = $request->get_param('print_immediately');
        $payload .= ($print_immediately ? 'true' : 'false') . "\r\n";
        $payload .= '--' . $boundary . '--' . "\r\n";
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 60,
            'method' => 'POST',
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status >= 200 && $status < 300) {
            return array(
                'success' => true,
                'message' => 'Archivo subido correctamente' . ($print_immediately ? ' y enviado a imprimir' : ''),
                'data' => json_decode($body, true)
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $status,
                'data' => json_decode($body, true)
            );
        }
    }
    
    /**
     * Enviar un archivo existente a imprimir
     */
    public function print_file($request) {
        $file_path = $request->get_param('file_path');
        
        if (!$file_path) {
            return new WP_Error('no_file', 'No se especificó un archivo para imprimir', array('status' => 400));
        }
        
        // Comando para enviar a imprimir un archivo seleccionado
        $url = trailingslashit($this->octoprint_url) . 'api/files/' . $file_path;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'X-Api-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('command' => 'select', 'print' => true)),
            'method' => 'POST',
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status >= 200 && $status < 300) {
            return array(
                'success' => true,
                'message' => 'Archivo enviado a imprimir'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Error al enviar el archivo a imprimir: ' . $status
            );
        }
    }
    
    /**
     * Manejar la subida de archivos STL y GCODE (para usuarios logueados)
     */
    public function handle_stl_upload() {
        // Verificar nonce
        if (!check_ajax_referer('wpoi-stl-upload', 'nonce', false)) {
            wp_send_json_error('Error de seguridad. Por favor, recarga la página.');
        }
        
        // Verificar si se ha subido un archivo
        if (empty($_FILES['stl_file'])) {
            wp_send_json_error('No se ha subido ningún archivo.');
        }
        
        $file = $_FILES['stl_file'];
        
        // Verificar tipo de archivo
        $filetype = wp_check_filetype(basename($file['name']));
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (($filetype['ext'] != 'stl' && $file_ext != 'stl') && 
            ($filetype['ext'] != 'gcode' && $file_ext != 'gcode')) {
            wp_send_json_error('Solo se permiten archivos STL o GCODE.');
        }
        
        // Enviar a OctoPrint
        $url = trailingslashit($this->octoprint_url) . 'api/files/local';
        
        $boundary = wp_generate_password(24);
        $headers = array(
            'X-Api-Key' => $this->api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        
        $payload = '';
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file['name']) . '"' . "\r\n";
        $payload .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
        $payload .= file_get_contents($file['tmp_name']) . "\r\n";
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="select"' . "\r\n\r\n";
        $payload .= 'true' . "\r\n";
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="print"' . "\r\n\r\n";
        $payload .= (isset($_POST['print_immediately']) && $_POST['print_immediately'] == 'true' ? 'true' : 'false') . "\r\n";
        $payload .= '--' . $boundary . '--' . "\r\n";
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 60,
            'method' => 'POST',
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Error al conectar con OctoPrint: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status >= 200 && $status < 300) {
            wp_send_json_success(array(
                'message' => 'Archivo subido correctamente' . 
                    (isset($_POST['print_immediately']) && $_POST['print_immediately'] == 'true' ? ' y enviado a imprimir' : ''),
                'data' => json_decode($body, true)
            ));
        } else {
            wp_send_json_error('Error al subir el archivo: ' . $status);
        }
    }
    
    /**
     * Manejar la subida para usuarios no logueados
     */
    public function handle_stl_upload_nopriv() {
        wp_send_json_error('Debe iniciar sesión para subir archivos.');
    }
    
    /**
     * Realizar solicitud a la API de OctoPrint
     */
    private function request_octoprint($endpoint, $method = 'GET', $data = null) {
        $url = trailingslashit($this->octoprint_url) . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'X-Api-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && ($method == 'POST' || $method == 'PUT')) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status >= 200 && $status < 300) {
            return array(
                'success' => true,
                'data' => json_decode($body, true)
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Error ' . $status,
                'data' => json_decode($body, true)
            );
        }
    }
    
    /**
     * Shortcode para mostrar el estado de la impresora
     */
    public function octoprint_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_temp' => 'true',
            'show_progress' => 'true',
            'show_webcam' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="wpoi-container">
            <div class="wpoi-status-box">
                <h3>Estado de la impresora</h3>
                <div id="wpoi-printer-status">Cargando...</div>
                
                <?php if ($atts['show_temp'] === 'true'): ?>
                <div class="wpoi-temperatures">
                    <h4>Temperaturas</h4>
                    <div id="wpoi-temperature-data">Cargando...</div>
                </div>
                <?php endif; ?>
                
                <?php if ($atts['show_progress'] === 'true'): ?>
                <div class="wpoi-job-progress">
                    <h4>Progreso del trabajo</h4>
                    <div id="wpoi-progress-bar">
                        <div id="wpoi-progress-inner" style="width: 0%;">0%</div>
                    </div>
                    <div id="wpoi-job-info">Sin trabajo activo</div>
                </div>
                <?php endif; ?>
                
                <?php if ($atts['show_webcam'] === 'true'): ?>
                <div class="wpoi-webcam">
                    <h4>Webcam</h4>
                    <img id="wpoi-webcam-image" src="" alt="Webcam no disponible" />
                </div>
                <?php endif; ?>
                
                <div class="wpoi-controls">
                    <button id="wpoi-btn-home" class="wpoi-button">Home</button>
                    <button id="wpoi-btn-pause" class="wpoi-button">Pausar</button>
                    <button id="wpoi-btn-resume" class="wpoi-button">Reanudar</button>
                    <button id="wpoi-btn-cancel" class="wpoi-button">Cancelar</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para subir archivos STL o GCODE e imprimirlos
     */
    public function octoprint_upload_shortcode($atts) {
        $atts = shortcode_atts(array(
            'allow_guest' => 'false',
            'show_files' => 'true',
        ), $atts);
        
        // Si no se permiten invitados y el usuario no está logueado, mostrar mensaje
        if ($atts['allow_guest'] === 'false' && !is_user_logged_in()) {
            return '<div class="wpoi-container"><div class="wpoi-status-box">
                <p>Debe iniciar sesión para subir archivos STL o GCODE.</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button">Iniciar sesión</a>
            </div></div>';
        }
        
        ob_start();
        ?>
        <div class="wpoi-container">
            <div class="wpoi-status-box wpoi-upload-box">
                <h3>Subir modelo para imprimir</h3>
                
                <form id="wpoi-stl-upload-form" method="post" enctype="multipart/form-data">
                    <div class="wpoi-form-row">
                        <label for="wpoi-stl-file">Seleccionar archivo (STL o GCODE):</label>
                        <input type="file" id="wpoi-stl-file" name="stl_file" accept=".stl,.gcode" required>
                    </div>
                    
                    <div class="wpoi-form-row">
                        <label for="wpoi-print-immediately">
                            <input type="checkbox" id="wpoi-print-immediately" name="print_immediately" value="true">
                            Imprimir inmediatamente
                        </label>
                    </div>
                    
                    <div class="wpoi-form-row">
                        <?php wp_nonce_field('wpoi-stl-upload', 'wpoi-stl-nonce'); ?>
                        <button type="submit" id="wpoi-upload-button" class="wpoi-button">Subir e imprimir</button>
                    </div>
                </form>
                
                <div id="wpoi-upload-status" class="wpoi-status-message" style="display:none;"></div>
                <div id="wpoi-upload-progress" class="wpoi-progress-container" style="display:none;">
                    <div class="wpoi-progress-bar">
                        <div id="wpoi-upload-progress-inner" class="wpoi-progress-inner" style="width: 0%;">0%</div>
                    </div>
                </div>
                
                <?php if ($atts['show_files'] === 'true'): ?>
                <div id="wpoi-files-container">
                    <h4>Archivos disponibles</h4>
                    <div id="wpoi-files-list">Cargando archivos...</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Inicializar el formulario de subida
            $('#wpoi-stl-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData();
                var fileInput = $('#wpoi-stl-file')[0];
                
                if (fileInput.files.length === 0) {
                    $('#wpoi-upload-status').html('Por favor, seleccione un archivo STL o GCODE').show().removeClass('success').addClass('error');
                    return false;
                }
                
                var file = fileInput.files[0];
                var fileName = file.name.toLowerCase();
                
                if (!fileName.endsWith('.stl') && !fileName.endsWith('.gcode')) {
                    $('#wpoi-upload-status').html('Solo se permiten archivos STL o GCODE').show().removeClass('success').addClass('error');
                    return false;
                }
                
                // Añadir el archivo al FormData
                formData.append('stl_file', file);
                formData.append('action', 'wpoi_upload_stl');
                formData.append('nonce', '<?php echo wp_create_nonce('wpoi-stl-upload'); ?>');
                formData.append('print_immediately', $('#wpoi-print-immediately').is(':checked') ? 'true' : 'false');
                
                // Mostrar el progreso
                $('#wpoi-upload-status').hide();
                $('#wpoi-upload-progress').show();
                $('#wpoi-upload-button').prop('disabled', true).text('Subiendo...');
                
                $.ajax({
                    url: wpoi.ajax_url,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                $('#wpoi-upload-progress-inner').css('width', percentComplete + '%').text(percentComplete + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wpoi-upload-status').html(response.data.message).show().removeClass('error').addClass('success');
                            $('#wpoi-stl-upload-form')[0].reset();
                            
                            // Actualizar la lista de archivos
                            if ($('#wpoi-files-list').length > 0) {
                                loadFilesList();
                            }
                        } else {
                            $('#wpoi-upload-status').html('Error: ' + response.data).show().removeClass('success').addClass('error');
                        }
                    },
                    error: function() {
                        $('#wpoi-upload-status').html('Error de conexión').show().removeClass('success').addClass('error');
                    },
                    complete: function() {
                        $('#wpoi-upload-button').prop('disabled', false).text('Subir e imprimir');
                        setTimeout(function() {
                            $('#wpoi-upload-progress').hide();
                            $('#wpoi-upload-progress-inner').css('width', '0%').text('0%');
                        }, 1000);
                    }
                });
            });
            
            <?php if ($atts['show_files'] === 'true'): ?>
            // Cargar la lista de archivos
            function loadFilesList() {
                $.ajax({
                    url: wpoi.rest_url + 'files',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
                    },
                    success: function(response) {
                        if (response.success && response.data.files) {
                            displayFilesList(response.data.files);
                        } else {
                            $('#wpoi-files-list').html('<p>Error al cargar la lista de archivos</p>');
                        }
                    },
                    error: function() {
                        $('#wpoi-files-list').html('<p>Error de conexión</p>');
                    }
                });
            }
            
            // Mostrar la lista de archivos
            function displayFilesList(files) {
                if (!files.length) {
                    $('#wpoi-files-list').html('<p>No hay archivos disponibles</p>');
                    return;
                }
                
                var html = '<table class="wpoi-files-table">';
                html += '<thead><tr><th>Nombre</th><th>Tamaño</th><th>Acción</th></tr></thead>';
                html += '<tbody>';
                
                files.forEach(function(file) {
                    if (file.type === 'folder') {
                        processFolder(file, html);
                    } else {
                        html += '<tr>';
                        html += '<td>' + file.name + '</td>';
                        html += '<td>' + formatFileSize(file.size) + '</td>';
                        html += '<td><button class="wpoi-button print-file" data-path="local/' + file.path + '">Imprimir</button></td>';
                        html += '</tr>';
                    }
                });
                
                html += '</tbody></table>';
                $('#wpoi-files-list').html(html);
                
                // Añadir listener para los botones de imprimir
                $('.print-file').on('click', function() {
                    var filePath = $(this).data('path');
                    printFile(filePath);
                });
            }
            
            // Procesar carpetas de archivos
            function processFolder(folder, html) {
                if (folder.children && folder.children.length) {
                    folder.children.forEach(function(file) {
                        if (file.type !== 'folder') {
                            html += '<tr>';
                            html += '<td>' + folder.name + '/' + file.name + '</td>';
                            html += '<td>' + formatFileSize(file.size) + '</td>';
                            html += '<td><button class="wpoi-button print-file" data-path="local/' + file.path + '">Imprimir</button></td>';
                            html += '</tr>';
                        }
                    });
                }
            }
            
            // Formatear tamaño de archivo
            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                else return (bytes / 1048576).toFixed(1) + ' MB';
            }
            
            // Imprimir un archivo
            function printFile(filePath) {
                $.ajax({
                    url: wpoi.rest_url + 'print',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
                    },
                    data: {
                        file_path: filePath
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Archivo enviado a imprimir: ' + response.message);
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    }
                });
            }
            
            // Cargar la lista inicial de archivos
            loadFilesList();
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Control de trabajo de impresión (pausar, reanudar, cancelar)
     */
    public function control_job($request) {
        $command = $request->get_param('command');
        $action = $request->get_param('action'); // 'pause' o 'resume'
        
        // Log for debugging
        error_log('OctoPrint job command: ' . $command . ', action: ' . $action);
        
        $data = array(
            'command' => $command
        );
        
        if ($command === 'pause' && !empty($action)) {
            $data['action'] = $action;
        }
        
        // Send to OctoPrint job API endpoint
        $response = $this->request_octoprint('api/job', 'POST', $data);
        
        // Log response for debugging
        error_log('OctoPrint response: ' . json_encode($response));
        
        return rest_ensure_response($response);
    }
    
    /**
     * Iniciar plugin
     */
    public static function init() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
}

// Inicializar el plugin
function wpoi_load() {
    WordPress_OctoPrint_Integration::init();
}
add_action('plugins_loaded', 'wpoi_load');
