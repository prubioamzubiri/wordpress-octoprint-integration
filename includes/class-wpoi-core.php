<?php
/**
 * Core functionality class
 */
class WPOI_Core {
    
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('wpoi_settings', array());
    }
    
    /**
     * Get the OctoPrint URL
     * 
     * @return string OctoPrint URL
     */
    public function get_octoprint_url() {
        return !empty($this->settings['octoprint_url']) ? $this->settings['octoprint_url'] : '';
    }
    
    /**
     * Get the OctoPrint API key
     * 
     * @return string OctoPrint API key
     */
    public function get_api_key() {
        return !empty($this->settings['api_key']) ? $this->settings['api_key'] : '';
    }
    
    /**
     * Make a request to the OctoPrint API
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc)
     * @param array $data Optional data to send with the request
     * @return array Response data
     */
    public function request_octoprint($endpoint, $method = 'GET', $data = array()) {
        // Get API settings
        $octoprint_url = $this->get_octoprint_url();
        $api_key = $this->get_api_key();
        
        if (empty($octoprint_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'Missing OctoPrint configuration'
            );
        }
        
        // Build request URL
        $url = trailingslashit($octoprint_url) . $endpoint;
        
        // Debug log
        error_log('OctoPrint API Request: ' . $method . ' ' . $url);
        
        // Set up request args
        $args = array(
            'headers' => array(
                'X-Api-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
            'method' => $method
        );
        
        // Add body data if needed
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        // Handle potential errors
        if (is_wp_error($response)) {
            error_log('OctoPrint API Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        // Get response data
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Log detailed response for debugging
        error_log('OctoPrint API Response Code: ' . $status);
        error_log('OctoPrint API Response Body: ' . substr($body, 0, 1000) . (strlen($body) > 1000 ? '...' : ''));
        
        // Return parsed response
        return array(
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $data,
        );
    }
}