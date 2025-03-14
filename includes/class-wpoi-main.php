<?php
/**
 * Core plugin class
 */
class WPOI_Main {
    
    // Singleton instance
    private static $instance = null;
    
    // URL base de OctoPrint
    private $octoprint_url;
    
    // API Key de OctoPrint
    private $api_key;
    
    // Class instances
    private $admin;
    private $api;
    private $shortcodes;
    private $file_handler;
    
    /**
     * Constructor - make it private for singleton
     */
    private function __construct() {
        // Inicializar opciones
        $this->octoprint_url = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        $this->api_key = get_option('wpoi_api_key', '');
        
        // Initialize component classes
        $this->admin = new WPOI_Admin($this);
        $this->api = new WPOI_API($this);
        $this->shortcodes = new WPOI_Shortcodes($this);
        $this->file_handler = new WPOI_File_Handler($this);
        
        // Register scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts() {
        wp_enqueue_style('wpoi-styles', WPOI_PLUGIN_URL . 'css/wpoi-styles.css');
        wp_enqueue_script('wpoi-scripts', WPOI_PLUGIN_URL . 'js/wpoi-scripts.js', array('jquery'), WPOI_VERSION, true);
        
        // Añadir script específico para subida de STL
        wp_enqueue_script('wpoi-upload', WPOI_PLUGIN_URL . 'js/wpoi-upload.js', array('jquery'), WPOI_VERSION, true);
        
        // Pasar variables a JavaScript
        wp_localize_script('wpoi-scripts', 'wpoi', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wpoi/v1/'),
            'octoprint_url' => $this->octoprint_url,
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Get plugin settings
     * 
     * @return array Settings array
     */
    public function get_settings() {
        // Get all relevant settings
        $settings = get_option('wpoi_settings', array());
        
        // Add individual settings that are stored separately
        $settings['octoprint_url'] = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        $settings['api_key'] = get_option('wpoi_api_key', '');
        
        return $settings;
    }
    
    /**
     * Get OctoPrint URL
     *
     * @return string OctoPrint URL
     */
    public function get_octoprint_url() {
        return get_option('wpoi_octoprint_url', 'http://localhost:5000');
    }
    
    /**
     * Get API key
     *
     * @return string API key
     */
    public function get_api_key() {
        return get_option('wpoi_api_key', '');
    }
    
    /**
     * Request to OctoPrint API
     */
    public function request_octoprint($endpoint, $method = 'GET', $data = null) {
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
}
