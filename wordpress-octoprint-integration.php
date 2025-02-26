<?php
/**
 * Plugin Name: WordPress OctoPrint Integration
 * Plugin URI: https://example.com/wordpress-octoprint
 * Description: Integra OctoPrint con WordPress para monitorear y controlar tu impresora 3D
 * Version: 0.2
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
        
        // Registrar shortcode para mostrar estado de impresora
        add_shortcode('octoprint_status', array($this, 'octoprint_status_shortcode'));
        
        // Registrar API endpoints de WordPress para comunicarse con OctoPrint
        add_action('rest_api_init', array($this, 'register_rest_routes'));
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
        
        // Pasar variables a JavaScript
        wp_localize_script('wpoi-scripts', 'wpoi', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wpoi/v1/')
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
