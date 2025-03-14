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
        
        // Añadir ruta para eliminar archivos
        register_rest_route('wpoi/v1', '/delete-file', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_file'),
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

        // Add route for creating folders
        register_rest_route('wpoi/v1', '/create-folder', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_folder'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Add route for deleting folders
        register_rest_route('wpoi/v1', '/delete-folder', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_folder'),
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
        $path = $request->get_param('path');
        
        // Add debugging
        error_log('WPOI get_files_list request path: ' . (string)$path);
        
        if (!empty($path)) {
            // Get specific folder contents
            $endpoint = 'api/files/local/' . ltrim($path, '/');
            
            // Debug endpoint
            error_log('Requesting folder contents from: ' . $endpoint);
            
            // Make the API request to OctoPrint
            $response = $this->main->request_octoprint($endpoint);
            
            // If successful, ensure we're returning the folder contents properly
            if (isset($response['success']) && $response['success'] && isset($response['data'])) {
                error_log('Successfully fetched folder contents');
                return rest_ensure_response($response);
            } else {
                error_log('Error fetching folder contents: ' . json_encode($response));
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => 'Error al obtener contenido de la carpeta',
                    'response' => $response
                ));
            }
        } else {
            // Get root files and folders
            $endpoint = 'api/files';
            error_log('Requesting root files from: ' . $endpoint);
            
            $response = $this->main->request_octoprint($endpoint);
            return rest_ensure_response($response);
        }
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

    /**
     * Eliminar un archivo de OctoPrint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function delete_file($request) {
        $file_path = $request->get_param('file_path');
        
        if (!$file_path) {
            return new WP_Error('no_file', 'No se especificó un archivo para eliminar', array('status' => 400));
        }
        
        // Enviar solicitud DELETE a OctoPrint para eliminar el archivo
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/' . $file_path;
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'X-Api-Key' => $this->main->get_api_key(),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        // OctoPrint returns 204 (No Content) on successful deletion
        if ($status === 204) {
            return array(
                'success' => true,
                'message' => 'Archivo eliminado correctamente'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Error desconocido';
            
            return array(
                'success' => false,
                'message' => 'Error al eliminar el archivo: ' . $error_message,
                'status' => $status
            );
        }
    }

    /**
     * Create a new folder in OctoPrint
     */
    public function create_folder($request) {
        $folder_name = $request->get_param('folder_name');
        $path = $request->get_param('path');
        
        if (empty($folder_name)) {
            return new WP_Error('missing_param', 'Nombre de carpeta requerido', array('status' => 400));
        }
        
        // Debug log
        error_log('Creating folder: ' . $folder_name . ' in path: ' . $path);
        
        // Create folder via OctoPrint API using multipart/form-data format
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/local';
        
        // Generate a boundary for multipart form
        $boundary = wp_generate_password(24, false);
        
        $body = '';
        // Add foldername part
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="foldername"' . "\r\n\r\n";
        $body .= $folder_name . "\r\n";
        
        // Add path part if specified
        if (!empty($path)) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="path"' . "\r\n\r\n";
            $body .= $path . '/' . "\r\n"; // Path must end with '/'
        }
        
        // Close the body
        $body .= '--' . $boundary . '--' . "\r\n";
        
        // Log the request body for debugging
        error_log('Folder creation request body: ' . $body);
        
        $headers = array(
            'X-Api-Key' => $this->main->get_api_key(),
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Folder creation error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log full response for debugging
        error_log('Folder creation response status: ' . $status);
        error_log('Folder creation response body: ' . $body);
        
        if ($status >= 200 && $status < 300) {
            return array(
                'success' => true,
                'message' => 'Carpeta creada correctamente'
            );
        } else {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Error desconocido';
            
            return array(
                'success' => false,
                'message' => 'Error al crear la carpeta: ' . $error_message,
                'status' => $status
            );
        }
    }
    
    /**
     * Delete a folder from OctoPrint
     */
    public function delete_folder($request) {
        $folder_path = $request->get_param('folder_path');
        
        if (!$folder_path) {
            return new WP_Error('no_folder', 'No se especificó una carpeta para eliminar', array('status' => 400));
        }
        
        // Send DELETE request to OctoPrint
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/' . $folder_path;
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'X-Api-Key' => $this->main->get_api_key(),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        // OctoPrint returns 204 (No Content) on successful deletion
        if ($status === 204) {
            return array(
                'success' => true,
                'message' => 'Carpeta eliminada correctamente'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Error desconocido';
            
            return array(
                'success' => false,
                'message' => 'Error al eliminar la carpeta: ' . $error_message,
                'status' => $status
            );
        }
    }
}
