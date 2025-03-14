<?php
/**
 * Shortcodes functionality
 */
class WPOI_Shortcodes {
    
    private $main;
    
    /**
     * Constructor
     */
    public function __construct($main) {
        $this->main = $main;
        
        // Registrar shortcodes
        add_shortcode('octoprint_status', array($this, 'octoprint_status_shortcode'));
        add_shortcode('octoprint_upload', array($this, 'octoprint_upload_shortcode'));
        add_shortcode('octoprint_webcam', array($this, 'webcam_shortcode'));
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

                <div class="wpoi-controls">
                    <button id="wpoi-btn-home" class="wpoi-button">Home</button>
                    <button id="wpoi-btn-pause" class="wpoi-button">Pausar</button>
                    <button id="wpoi-btn-resume" class="wpoi-button">Reanudar</button>
                    <button id="wpoi-btn-cancel" class="wpoi-button">Cancelar</button>
                </div>
                
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
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para subir archivos STL o GCODE e imprimirlos
     */
    public function octoprint_upload_shortcode($atts) {
        $atts = shortcode_atts(array(
            'allow_guest' => 'false',
            'show_files' => 'true',
        ), $atts);
        
        // Si no se permiten invitados y el usuario no está logueado, mostrar mensaje
        if ($atts['allow_guest'] === 'false' && !is_user_logged_in()) {
            return '<div class="wpoi-container"><div class="wpoi-status-box">
                <p>Debe iniciar sesión para subir archivos STL o GCODE.</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button">Iniciar sesión</a>
            </div></div>';
        }
        
        ob_start();
        ?>
        <div class="wpoi-container">
            <div class="wpoi-status-box wpoi-upload-box">
                <h3>Subir modelo para imprimir</h3>
                
                <form id="wpoi-stl-upload-form" method="post" enctype="multipart/form-data">
                    <div class="wpoi-form-row">
                        <label for="wpoi-stl-file">Seleccionar archivo (STL o GCODE):</label>
                        <input type="file" id="wpoi-stl-file" name="stl_file" accept=".stl,.gcode" required>
                    </div>
                    
                    <div class="wpoi-form-row">
                        <label for="wpoi-print-immediately">
                            <input type="checkbox" id="wpoi-print-immediately" name="print_immediately" value="true">
                            Imprimir inmediatamente
                        </label>
                    </div>
                    
                    <div class="wpoi-form-row">
                        <?php wp_nonce_field('wpoi-stl-upload', 'wpoi-stl-nonce'); ?>
                        <button type="submit" id="wpoi-upload-button" class="wpoi-button">Subir e imprimir</button>
                    </div>
                </form>
                
                <div id="wpoi-upload-status" class="wpoi-status-message" style="display:none;"></div>
                <div id="wpoi-upload-progress" class="wpoi-progress-container" style="display:none;">
                    <div class="wpoi-progress-bar">
                        <div id="wpoi-upload-progress-inner" class="wpoi-progress-inner" style="width: 0%;">0%</div>
                    </div>
                </div>
                
                <?php if ($atts['show_files'] === 'true'): ?>
                <div id="wpoi-files-container">
                    <h4>Archivos disponibles</h4>
                    <div id="wpoi-files-list">Cargando archivos...</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Inicializar el formulario de subida
            $('#wpoi-stl-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData();
                var fileInput = $('#wpoi-stl-file')[0];
                
                if (fileInput.files.length === 0) {
                    $('#wpoi-upload-status').html('Por favor, seleccione un archivo STL o GCODE').show().removeClass('success').addClass('error');
                    return false;
                }
                
                var file = fileInput.files[0];
                var fileName = file.name.toLowerCase();
                
                if (!fileName.endsWith('.stl') && !fileName.endsWith('.gcode')) {
                    $('#wpoi-upload-status').html('Solo se permiten archivos STL o GCODE').show().removeClass('success').addClass('error');
                    return false;
                }
                
                // Añadir el archivo al FormData
                formData.append('stl_file', file);
                formData.append('action', 'wpoi_upload_stl');
                formData.append('nonce', $('#wpoi-stl-nonce').val());
                formData.append('print_immediately', $('#wpoi-print-immediately').is(':checked') ? 'true' : 'false');
                
                // Mostrar el progreso
                $('#wpoi-upload-status').hide();
                $('#wpoi-upload-progress').show();
                $('#wpoi-upload-button').prop('disabled', true).text('Subiendo...');
                
                $.ajax({
                    url: wpoi.ajax_url,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                $('#wpoi-upload-progress-inner').css('width', percentComplete + '%').text(percentComplete + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wpoi-upload-status').html(response.data.message).show().removeClass('error').addClass('success');
                            $('#wpoi-stl-upload-form')[0].reset();
                            
                            // Actualizar la lista de archivos
                            if ($('#wpoi-files-list').length > 0) {
                                loadFilesList();
                            }
                        } else {
                            $('#wpoi-upload-status').html('Error: ' + response.data).show().removeClass('success').addClass('error');
                        }
                    },
                    error: function() {
                        $('#wpoi-upload-status').html('Error de conexión').show().removeClass('success').addClass('error');
                    },
                    complete: function() {
                        $('#wpoi-upload-button').prop('disabled', false).text('Subir e imprimir');
                        setTimeout(function() {
                            $('#wpoi-upload-progress').hide();
                            $('#wpoi-upload-progress-inner').css('width', '0%').text('0%');
                        }, 1000);
                    }
                });
            });
            
            <?php if ($atts['show_files'] === 'true'): ?>
            // Cargar la lista de archivos
            function loadFilesList() {
                $.ajax({
                    url: wpoi.rest_url + 'files',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
                    },
                    success: function(response) {
                        if (response.success && response.data.files) {
                            displayFilesList(response.data.files);
                        } else {
                            $('#wpoi-files-list').html('<p>Error al cargar la lista de archivos</p>');
                        }
                    },
                    error: function() {
                        $('#wpoi-files-list').html('<p>Error de conexión</p>');
                    }
                });
            }
            
            // Mostrar la lista de archivos
            function displayFilesList(files) {
                if (!files.length) {
                    $('#wpoi-files-list').html('<p>No hay archivos disponibles</p>');
                    return;
                }
                
                var html = '<table class="wpoi-files-table">';
                html += '<thead><tr><th>Nombre</th><th>Tamaño</th><th>Acción</th></tr></thead>';
                html += '<tbody>';
                
                files.forEach(function(file) {
                    if (file.type === 'folder') {
                        processFolder(file, html);
                    } else {
                        html += '<tr>';
                        html += '<td>' + file.name + '</td>';
                        html += '<td>' + formatFileSize(file.size) + '</td>';
                        html += '<td><button class="wpoi-button print-file" data-path="local/' + file.path + '">Imprimir</button></td>';
                        html += '</tr>';
                    }
                });
                
                html += '</tbody></table>';
                $('#wpoi-files-list').html(html);
                
                // Añadir listener para los botones de imprimir
                $('.print-file').on('click', function() {
                    var filePath = $(this).data('path');
                    printFile(filePath);
                });
            }
            
            // Procesar carpetas de archivos
            function processFolder(folder, html) {
                if (folder.children && folder.children.length) {
                    folder.children.forEach(function(file) {
                        if (file.type !== 'folder') {
                            html += '<tr>';
                            html += '<td>' + folder.name + '/' + file.name + '</td>';
                            html += '<td>' + formatFileSize(file.size) + '</td>';
                            html += '<td><button class="wpoi-button print-file" data-path="local/' + file.path + '">Imprimir</button></td>';
                            html += '</tr>';
                        }
                    });
                }
            }
            
            // Formatear tamaño de archivo
            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                else return (bytes / 1048576).toFixed(1) + ' MB';
            }
            
            // Imprimir un archivo
            function printFile(filePath) {
                $.ajax({
                    url: wpoi.rest_url + 'print',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
                    },
                    data: {
                        file_path: filePath
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Archivo enviado a imprimir: ' + response.message);
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    }
                });
            }
            
            // Cargar la lista inicial de archivos
            loadFilesList();
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Webcam shortcode to display the OctoPrint webcam stream
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function webcam_shortcode($atts) {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '400px',
            'refresh' => '5', // Refresh rate in seconds for static images
            'mode' => 'stream', // 'stream' or 'snapshot'
            'controls' => 'true' // Show rotation controls
        ), $atts);
        
        // Get webcam URL from OctoPrint settings
        $webcam_url = $this->get_webcam_url($atts['mode']);
        
        if (!$webcam_url) {
            return '<p class="wpoi-error">No se puede obtener la URL de la webcam. Compruebe la configuración.</p>';
        }
        
        // Enqueue necessary scripts
        wp_enqueue_script('jquery');
        
        // Generate unique ID for this webcam instance
        $webcam_id = 'wpoi-webcam-' . uniqid();
        
        $output = '<div class="wpoi-webcam-container" style="width: ' . esc_attr($atts['width']) . '; margin: 0 auto;">';
        
        // Add webcam viewer
        if ($atts['mode'] == 'stream') {
            // For MJPG stream
            $output .= '<div class="wpoi-webcam-viewer" style="position: relative;">';
            $output .= '<img id="' . $webcam_id . '" src="' . esc_url($webcam_url) . '" 
                          alt="OctoPrint Webcam" 
                          style="width: 100%; height: ' . esc_attr($atts['height']) . '; object-fit: contain;" />';
            $output .= '</div>';
        } else {
            // For snapshot mode with refresh
            $output .= '<div class="wpoi-webcam-viewer" style="position: relative;">';
            $output .= '<img id="' . $webcam_id . '" src="' . esc_url($webcam_url) . '" 
                          alt="OctoPrint Webcam" 
                          style="width: 100%; height: ' . esc_attr($atts['height']) . '; object-fit: contain;"
                          data-refresh-rate="' . esc_attr($atts['refresh']) . '"
                          data-src="' . esc_url($webcam_url) . '" />';
            $output .= '</div>';
            
            // Add refresh script for snapshot mode
            $output .= "
            <script type='text/javascript'>
            jQuery(document).ready(function($) {
                setInterval(function() {
                    var webcam = $('#{$webcam_id}');
                    var src = webcam.attr('data-src');
                    webcam.attr('src', src + '?t=' + new Date().getTime());
                }, " . (intval($atts['refresh']) * 1000) . ");
            });
            </script>";
        }
        
        // Add webcam controls if enabled
        if ($atts['controls'] == 'true') {
            $output .= '
            <div class="wpoi-webcam-controls" style="margin-top: 10px; text-align: center;">
                <button class="button wpoi-rotate-left" data-webcam="' . $webcam_id . '">↺ Rotar izquierda</button>
                <button class="button wpoi-flip-horizontal" data-webcam="' . $webcam_id . '">↔ Voltear horizontal</button>
                <button class="button wpoi-flip-vertical" data-webcam="' . $webcam_id . '">↕ Voltear vertical</button>
                <button class="button wpoi-rotate-right" data-webcam="' . $webcam_id . '">↻ Rotar derecha</button>
            </div>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var transforms = {};
                
                // Initialize transform for this webcam
                transforms["' . $webcam_id . '"] = {
                    rotateZ: 0,
                    scaleX: 1,
                    scaleY: 1
                };
                
                function applyTransform(id) {
                    var t = transforms[id];
                    $("#" + id).css("transform", 
                        "rotateZ(" + t.rotateZ + "deg) scaleX(" + t.scaleX + ") scaleY(" + t.scaleY + ")");
                }
                
                $(".wpoi-rotate-left").click(function() {
                    var id = $(this).data("webcam");
                    transforms[id].rotateZ -= 90;
                    applyTransform(id);
                });
                
                $(".wpoi-rotate-right").click(function() {
                    var id = $(this).data("webcam");
                    transforms[id].rotateZ += 90;
                    applyTransform(id);
                });
                
                $(".wpoi-flip-horizontal").click(function() {
                    var id = $(this).data("webcam");
                    transforms[id].scaleX *= -1;
                    applyTransform(id);
                });
                
                $(".wpoi-flip-vertical").click(function() {
                    var id = $(this).data("webcam");
                    transforms[id].scaleY *= -1;
                    applyTransform(id);
                });
            });
            </script>';
        }
        
        $output .= '</div>'; // Close webcam container
        
        return $output;
    }
    
    /**
     * Get the webcam URL based on mode
     * 
     * @param string $mode 'stream' or 'snapshot'
     * @return string Webcam URL or false if not available
     */
    private function get_webcam_url($mode = 'stream') {
        // Get settings directly from options instead of using get_settings() method
        $settings = get_option('wpoi_settings', array());
        
        // Get basic OctoPrint URL
        $octoprint_url = get_option('wpoi_octoprint_url', 'http://localhost:5000');
        
        // Default URLs if not specifically configured
        if ($mode == 'stream') {
            // First check if we have a specific stream URL
            if (!empty($settings['webcam_stream_url'])) {
                return $settings['webcam_stream_url'];
            }
            
            // Fall back to default OctoPrint URL pattern
            $base_url = rtrim($octoprint_url, '/');
            return $base_url . '/webcam/?action=stream';
        } else {
            // First check if we have a specific snapshot URL
            if (!empty($settings['webcam_snapshot_url'])) {
                return $settings['webcam_snapshot_url'];
            }
            
            // Fall back to default OctoPrint URL pattern
            $base_url = rtrim($octoprint_url, '/');
            return $base_url . '/webcam/?action=snapshot';
        }
    }
}
