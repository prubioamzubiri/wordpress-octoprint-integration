<?php
/**
 * Test script for OctoPrint API
 * 
 * This script helps debug API calls to OctoPrint.
 * Access this via: http://your-site.com/wp-content/plugins/wordpress-octoprint-integration/includes/test-octoprint-api.php
 * 
 * IMPORTANT: Delete this file after debugging is complete for security reasons.
 */

// Load WordPress core
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You do not have permission to access this page.');
}

// Get OctoPrint settings
$octoprint_url = get_option('wpoi_octoprint_url', '');
$api_key = get_option('wpoi_octoprint_api_key', '');

// If no settings, show error
if (empty($octoprint_url) || empty($api_key)) {
    echo '<h2>Error: Missing OctoPrint configuration</h2>';
    echo '<p>Please configure OctoPrint URL and API key in the plugin settings.</p>';
    exit;
}

// Function to make an API request
function test_octoprint_api($url, $endpoint, $api_key, $method = 'GET', $data = null) {
    $full_url = trailingslashit($url) . $endpoint;
    
    $args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30,
        'method' => $method
    );
    
    if ($method !== 'GET' && $data !== null) {
        $args['body'] = json_encode($data);
    }
    
    $response = wp_remote_request($full_url, $args);
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'error' => $response->get_error_message()
        );
    }
    
    return array(
        'success' => true,
        'status' => wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response),
        'headers' => wp_remote_retrieve_headers($response),
        'data' => json_decode(wp_remote_retrieve_body($response), true)
    );
}

// Handle form submission
$test_result = null;
$test_data = null;

if (isset($_POST['test_api'])) {
    $endpoint = sanitize_text_field($_POST['endpoint']);
    $method = sanitize_text_field($_POST['method']);
    $data = null;
    
    if (!empty($_POST['data'])) {
        $data = json_decode(stripslashes($_POST['data']), true);
    }
    
    $test_result = test_octoprint_api($octoprint_url, $endpoint, $api_key, $method, $data);
    $test_data = $data;
}

// Test creating a folder specifically
if (isset($_POST['test_folder'])) {
    $folder_name = sanitize_text_field($_POST['folder_name']);
    $folder_path = !empty($_POST['folder_path']) ? trim(sanitize_text_field($_POST['folder_path']), '/') : '';
    
    // Generate boundary for multipart form
    $boundary = wp_generate_password(24, false);
    
    $body = '';
    // Add foldername part
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="foldername"' . "\r\n\r\n";
    $body .= $folder_name . "\r\n";
    
    // Add path part if specified
    if (!empty($folder_path)) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="path"' . "\r\n\r\n";
        $body .= $folder_path . '/' . "\r\n"; // Path must end with '/'
    }
    
    // Close the body
    $body .= '--' . $boundary . '--' . "\r\n";
    
    $full_url = trailingslashit($octoprint_url) . 'api/files/local';
    
    $args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ),
        'body' => $body,
        'timeout' => 30,
        'method' => 'POST'
    );
    
    $response = wp_remote_request($full_url, $args);
    
    if (is_wp_error($response)) {
        $test_result = array(
            'success' => false,
            'error' => $response->get_error_message()
        );
    } else {
        $test_result = array(
            'success' => true,
            'status' => wp_remote_retrieve_response_code($response),
            'body' => wp_remote_retrieve_body($response),
            'headers' => wp_remote_retrieve_headers($response),
            'data' => json_decode(wp_remote_retrieve_body($response), true),
            'request_body' => $body
        );
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OctoPrint API Test</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .container { margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        textarea { min-height: 100px; font-family: monospace; }
        button { background: #0073aa; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005177; }
        .result { background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f1f1f1; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>OctoPrint API Test Tool</h1>
    
    <div class="container">
        <h2>General API Test</h2>
        <form method="post" action="">
            <div class="form-group">
                <label for="endpoint">API Endpoint:</label>
                <input type="text" id="endpoint" name="endpoint" value="api/files" required>
            </div>
            
            <div class="form-group">
                <label for="method">HTTP Method:</label>
                <select id="method" name="method">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="data">JSON Data (for POST/PUT):</label>
                <textarea id="data" name="data" placeholder='{"command": "mkdir", "foldername": "test"}'></textarea>
            </div>
            
            <button type="submit" name="test_api">Test API</button>
        </form>
    </div>
    
    <div class="container">
        <h2>Create Folder Test</h2>
        <form method="post" action="">
            <div class="form-group">
                <label for="folder_name">Folder Name:</label>
                <input type="text" id="folder_name" name="folder_name" value="test_folder" required>
            </div>
            
            <div class="form-group">
                <label for="folder_path">Parent Folder Path (optional):</label>
                <input type="text" id="folder_path" name="folder_path" placeholder="path/to/parent">
            </div>
            
            <button type="submit" name="test_folder">Create Folder</button>
        </form>
    </div>
    
    <?php if ($test_result): ?>
    <div class="result">
        <h3>Test Results</h3>
        
        <?php if ($test_data): ?>
        <h4>Request Data:</h4>
        <pre><?php echo json_encode($test_data, JSON_PRETTY_PRINT); ?></pre>
        <?php endif; ?>
        
        <h4>Response:</h4>
        <?php if ($test_result['success']): ?>
            <p>Status Code: <span class="<?php echo ($test_result['status'] >= 200 && $test_result['status'] < 300) ? 'success' : 'error'; ?>">
                <?php echo $test_result['status']; ?>
            </span></p>
            
            <h4>Response Headers:</h4>
            <pre><?php print_r($test_result['headers']); ?></pre>
            
            <h4>Response Body (Raw):</h4>
            <pre><?php echo htmlspecialchars($test_result['body']); ?></pre>
            
            <h4>Response Data (Parsed):</h4>
            <pre><?php print_r($test_result['data']); ?></pre>
        <?php else: ?>
            <p class="error">Error: <?php echo $test_result['error']; ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
