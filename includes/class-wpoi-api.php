<?php
/**
 * API functionality
 */
class WPOI_API {
    
    private $main;
    
    /**
     * Constructor
     */
    public function __construct($main) {
        $this->main = $main;
        
        // Registrar API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
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
        $response = $this->main->request_octoprint('api/printer');
        return rest_ensure_response($response);
    }
    
    /**
     * Obtener estado del trabajo actual
     */
    public function get_job_status() {
        $response = $this->main->request_octoprint('api/job');
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
        
        $response = $this->main->request_octoprint('api/printer/command', 'POST', $data);
        return rest_ensure_response($response);
    }
    
    /**
     * Listar archivos disponibles en OctoPrint
     */
    public function get_files_list($request) {
        $response = $this->main->request_octoprint('api/files');
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
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/local';
        
        $boundary = wp_generate_password(24);
        $headers = array(
            'X-Api-Key' => $this->main->get_api_key(),
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
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/' . $file_path;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'X-Api-Key' => $this->main->get_api_key(),
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
        $response = $this->main->request_octoprint('api/job', 'POST', $data);
        
        // Log response for debugging
        error_log('OctoPrint response: ' . json_encode($response));
        
        return rest_ensure_response($response);
    }
}
