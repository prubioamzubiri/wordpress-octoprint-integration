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
        register_setting('wpoi_settings', 'wpoi_settings');
        
        add_settings_section(
            'wpoi_settings_section',
            __('Configuración de OctoPrint', 'wordpress-octoprint-integration'),
            array($this, 'settings_section_callback'),
            'wpoi_settings'
        );
        
        add_settings_field(
            'octoprint_url',
            __('URL de OctoPrint', 'wordpress-octoprint-integration'),
            array($this, 'octoprint_url_callback'),
            'wpoi_settings',
            'wpoi_settings_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'wordpress-octoprint-integration'),
            array($this, 'api_key_callback'),
            'wpoi_settings',
            'wpoi_settings_section'
        );
        
        // Add webcam settings fields
        add_settings_field(
            'webcam_stream_url',
            __('URL de Stream Webcam', 'wordpress-octoprint-integration'),
            array($this, 'webcam_stream_url_callback'),
            'wpoi_settings',
            'wpoi_settings_section'
        );
        
        add_settings_field(
            'webcam_snapshot_url',
            __('URL de Snapshot Webcam', 'wordpress-octoprint-integration'),
            array($this, 'webcam_snapshot_url_callback'),
            'wpoi_settings',
            'wpoi_settings_section'
        );
    }
    
    /**
     * Renderizar página de administración
     */
    public function admin_page() {
        // Get settings
        $settings = get_option('wpoi_settings', array());
        $octoprint_url = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        $api_key = get_option('wpoi_api_key', '');
        ?>
        <div class="wrap">
            <h1>WordPress OctoPrint Integration</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wpoi_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">URL de OctoPrint</th>
                        <td>
                            <input type="text" name="wpoi_octoprint_url" value="<?php echo esc_attr($octoprint_url); ?>" class="regular-text" />
                            <p class="description">Ejemplo: http://localhost:5000 o http://dirección-ip:5000</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="wpoi_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">Obtenido desde la interfaz de OctoPrint en Ajustes > API</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL de Stream Webcam</th>
                        <td>
                            <input type="text" name="wpoi_settings[webcam_stream_url]" value="<?php echo esc_attr($settings['webcam_stream_url'] ?? ''); ?>" class="regular-text" />
                            <p class="description">URL del stream de la webcam (ej. http://octopi.local/webcam/?action=stream). Déjelo en blanco para usar la URL por defecto.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL de Snapshot Webcam</th>
                        <td>
                            <input type="text" name="wpoi_settings[webcam_snapshot_url]" value="<?php echo esc_attr($settings['webcam_snapshot_url'] ?? ''); ?>" class="regular-text" />
                            <p class="description">URL de snapshot de la webcam (ej. http://octopi.local/webcam/?action=snapshot). Déjelo en blanco para usar la URL por defecto.</p>
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
     * Webcam stream URL field callback
     */
    public function webcam_stream_url_callback() {
        $settings = $this->main->get_settings();
        ?>
        <input type="text" name="wpoi_settings[webcam_stream_url]" value="<?php echo esc_attr($settings['webcam_stream_url'] ?? ''); ?>" class="regular-text" />
        <p class="description">
            <?php _e('URL del stream de la webcam (ej. http://octopi.local/webcam/?action=stream). Déjelo en blanco para usar la URL por defecto.', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Webcam snapshot URL field callback
     */
    public function webcam_snapshot_url_callback() {
        $settings = $this->main->get_settings();
        ?>
        <input type="text" name="wpoi_settings[webcam_snapshot_url]" value="<?php echo esc_attr($settings['webcam_snapshot_url'] ?? ''); ?>" class="regular-text" />
        <p class="description">
            <?php _e('URL de snapshot de la webcam (ej. http://octopi.local/webcam/?action=snapshot). Déjelo en blanco para usar la URL por defecto.', 'wordpress-octoprint-integration'); ?>
        </p>
        <?php
    }
}
