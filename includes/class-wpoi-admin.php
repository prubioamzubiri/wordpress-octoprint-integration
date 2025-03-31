<?php
/**
 * Admin functionality
 */
class WPOI_Admin {
    
    private $main;
    
    /**
     * Constructor
     */
    public function __construct($main) {
        $this->main = $main;
        
        // Hooks for admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handler for connection test
        add_action('wp_ajax_wpoi_test_connection', array($this, 'test_connection_ajax'));
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
        // Register individual settings for direct save
        register_setting(
            'wpoi_settings_group', // Option group
            'wpoi_octoprint_url',  // Option name
            array(
                'sanitize_callback' => array($this, 'sanitize_url'),
                'default' => 'http://localhost:5000'
            )
        );
        
        register_setting(
            'wpoi_settings_group',
            'wpoi_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        // Also register the settings group
        register_setting(
            'wpoi_settings_group',
            'wpoi_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array()
            )
        );
        
        add_settings_section(
            'wpoi_settings_section',
            __('Configuración de OctoPrint', 'wordpress-octoprint-integration'),
            array($this, 'settings_section_callback'),
            'wordpress-octoprint'
        );
        
        add_settings_field(
            'octoprint_url',
            __('URL de OctoPrint', 'wordpress-octoprint-integration'),
            array($this, 'octoprint_url_callback'),
            'wordpress-octoprint',
            'wpoi_settings_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'wordpress-octoprint-integration'),
            array($this, 'api_key_callback'),
            'wordpress-octoprint',
            'wpoi_settings_section'
        );
        
        // Add webcam settings fields
        add_settings_field(
            'webcam_stream_url',
            __('URL de Stream Webcam', 'wordpress-octoprint-integration'),
            array($this, 'webcam_stream_url_callback'),
            'wordpress-octoprint',
            'wpoi_settings_section'
        );
        
        add_settings_field(
            'webcam_snapshot_url',
            __('URL de Snapshot Webcam', 'wordpress-octoprint-integration'),
            array($this, 'webcam_snapshot_url_callback'),
            'wordpress-octoprint',
            'wpoi_settings_section'
        );
    }
    
    /**
     * Sanitize URL
     * 
     * @param string $url
     * @return string
     */
    public function sanitize_url($url) {
        $url = esc_url_raw(trim($url));
        
        // Add http:// if no protocol specified
        if (!empty($url) && !preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'http://' . $url;
        }
        
        return $url;
    }
    
    /**
     * Sanitize settings array
     * 
     * @param array $settings
     * @return array
     */
    public function sanitize_settings($settings) {
        // Create a safe array with only expected values
        $safe_settings = array();
        
        // Webcam URLs
        $safe_settings['webcam_stream_url'] = isset($settings['webcam_stream_url']) ? 
            esc_url_raw(trim($settings['webcam_stream_url'])) : '';
            
        $safe_settings['webcam_snapshot_url'] = isset($settings['webcam_snapshot_url']) ? 
            esc_url_raw(trim($settings['webcam_snapshot_url'])) : '';
        
        return $safe_settings;
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure los ajustes de conexión a OctoPrint.', 'wordpress-octoprint-integration') . '</p>';
    }
    
    /**
     * OctoPrint URL field callback
     */
    public function octoprint_url_callback() {
        $octoprint_url = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        ?>
        <input type="url" name="wpoi_octoprint_url" value="<?php echo esc_attr($octoprint_url); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Ejemplo: http://localhost:5000 o http://dirección-ip:5000', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * API Key field callback
     */
    public function api_key_callback() {
        $api_key = get_option('wpoi_api_key', '');
        ?>
        <input type="text" name="wpoi_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Obtenido desde la interfaz de OctoPrint en Ajustes > API', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Webcam stream URL field callback
     */
    public function webcam_stream_url_callback() {
        $settings = get_option('wpoi_settings', array());
        $webcam_stream_url = isset($settings['webcam_stream_url']) ? $settings['webcam_stream_url'] : '';
        ?>
        <input type="url" name="wpoi_settings[webcam_stream_url]" value="<?php echo esc_attr($webcam_stream_url); ?>" class="regular-text" />
        <p class="description">
            <?php _e('URL del stream de la webcam (ej. http://octopi.local/webcam/?action=stream). Déjelo en blanco para usar la URL por defecto.', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Webcam snapshot URL field callback
     */
    public function webcam_snapshot_url_callback() {
        $settings = get_option('wpoi_settings', array());
        $webcam_snapshot_url = isset($settings['webcam_snapshot_url']) ? $settings['webcam_snapshot_url'] : '';
        ?>
        <input type="url" name="wpoi_settings[webcam_snapshot_url]" value="<?php echo esc_attr($webcam_snapshot_url); ?>" class="regular-text" />
        <p class="description">
            <?php _e('URL de snapshot de la webcam (ej. http://octopi.local/webcam/?action=snapshot). Déjelo en blanco para usar la URL por defecto.', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Renderizar página de administración
     */
    public function admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show success/error messages
        settings_errors('wpoi_settings_group');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                // Output security fields
                settings_fields('wpoi_settings_group');
                // Output setting sections and fields
                do_settings_sections('wordpress-octoprint');
                // Output save settings button
                submit_button(__('Guardar ajustes', 'wordpress-octoprint-integration'));
                ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Prueba de conexión', 'wordpress-octoprint-integration'); ?></h2>
            <button id="wpoi-test-connection" class="button button-secondary">
                <?php _e('Probar conexión', 'wordpress-octoprint-integration'); ?>
            </button>
            <div id="wpoi-connection-result" style="margin-top: 10px;"></div>
            
            <hr>
            
            <h2><?php _e('Uso', 'wordpress-octoprint-integration'); ?></h2>
            <p><?php _e('Utiliza el shortcode <code>[octoprint_status]</code> para mostrar el estado de tu impresora en cualquier página o entrada.', 'wordpress-octoprint-integration'); ?></p>
            <p><?php _e('Otros shortcodes disponibles:', 'wordpress-octoprint-integration'); ?></p>
            <ul style="list-style-type: disc; padding-left: 2em;">
                <li><code>[octoprint_webcam]</code> - <?php _e('Muestra la webcam de OctoPrint', 'wordpress-octoprint-integration'); ?></li>
                <li><code>[octoprint_temperature]</code> - <?php _e('Muestra información de temperatura', 'wordpress-octoprint-integration'); ?></li>
                <li><code>[octoprint_job]</code> - <?php _e('Muestra el estado del trabajo actual', 'wordpress-octoprint-integration'); ?></li>
                <li><code>[octoprint_files]</code> - <?php _e('Muestra los archivos disponibles', 'wordpress-octoprint-integration'); ?></li>
            </ul>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wpoi-test-connection').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $result = $('#wpoi-connection-result');
                
                $button.prop('disabled', true);
                $result.html('<span style="color: #666;"><em><?php _e('Probando conexión...', 'wordpress-octoprint-integration'); ?></em></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpoi_test_connection',
                        nonce: '<?php echo wp_create_nonce('wpoi_test_connection'); ?>'
                    },
                    success: function(response) {
                        if(response.success) {
                            $result.html('<span style="color: green;">' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: red;">' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;"><?php _e('Error al realizar la prueba de conexión.', 'wordpress-octoprint-integration'); ?></span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Test connection AJAX handler
     */
    public function test_connection_ajax() {
        // Verify nonce
        check_ajax_referer('wpoi_test_connection', 'nonce', true);
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'wordpress-octoprint-integration')));
            return;
        }
        
        // Get current settings
        $octoprint_url = get_option('wpoi_octoprint_url', '');
        $api_key = get_option('wpoi_api_key', '');
        
        if (empty($octoprint_url) || empty($api_key)) {
            wp_send_json_error(array('message' => __('URL de OctoPrint o API Key no configurados', 'wordpress-octoprint-integration')));
            return;
        }
        
        // Test connection to OctoPrint API
        $url = trailingslashit($octoprint_url) . 'api/version';
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'X-Api-Key' => $api_key
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error de conexión: %s', 'wordpress-octoprint-integration'), $status_code)
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data)) {
            wp_send_json_error(array(
                'message' => __('Respuesta inválida de OctoPrint', 'wordpress-octoprint-integration')
            ));
            return;
        }
        
        // Success!
        wp_send_json_success(array(
            'message' => sprintf(
                __('Conexión exitosa! OctoPrint v%s - API v%s', 'wordpress-octoprint-integration'),
                $data['server'], 
                $data['api']
            ),
            'data' => $data
        ));
    }
}
