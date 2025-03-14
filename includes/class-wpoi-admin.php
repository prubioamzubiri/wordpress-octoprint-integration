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
                            <input type="text" name="wpoi_octoprint_url" value="<?php echo esc_attr(get_option('wpoi_octoprint_url', 'http://localhost:5000')); ?>" class="regular-text" />
                            <p class="description">Ejemplo: http://localhost:5000 o http://dirección-ip:5000</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="wpoi_api_key" value="<?php echo esc_attr(get_option('wpoi_api_key', '')); ?>" class="regular-text" />
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
}
