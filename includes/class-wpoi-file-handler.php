<?php
/**
 * File uploads handling
 */
class WPOI_File_Handler {
    
    private $main;
    
    /**
     * Constructor
     */
    public function __construct($main) {
        $this->main = $main;
        
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
        $url = trailingslashit($this->main->get_octoprint_url()) . 'api/files/local';
        
        $boundary = wp_generate_password(24);
        $headers = array(
            'X-Api-Key' => $this->main->get_api_key(),
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
}
