/**
 * WordPress OctoPrint Integration - Scripts para subida de STL y GCODE
 */
jQuery(document).ready(function($) {
    // Verificar si existe el formulario de subida
    if ($('#wpoi-stl-upload-form').length > 0) {
        
        // Configurar el formulario de subida
        $('#wpoi-stl-upload-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData();
            var fileInput = $('#wpoi-stl-file')[0];
            
            if (fileInput.files.length === 0) {
                showMessage('Por favor, seleccione un archivo STL o GCODE', 'error');
                return false;
            }
            
            var file = fileInput.files[0];
            var fileName = file.name.toLowerCase();
            
            if (!fileName.endsWith('.stl') && !fileName.endsWith('.gcode')) {
                showMessage('Solo se permiten archivos STL o GCODE', 'error');
                return false;
            }
            
            // Verificar tamaño máximo (20MB)
            if (file.size > 20 * 1024 * 1024) {
                showMessage('El archivo es demasiado grande. El tamaño máximo es 20MB.', 'error');
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
                        showMessage(response.data.message, 'success');
                        $('#wpoi-stl-upload-form')[0].reset();
                        
                        // Actualizar la lista de archivos
                        if ($('#wpoi-files-list').length > 0) {
                            loadFilesList();
                        }
                    } else {
                        showMessage('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showMessage('Error de conexión al servidor', 'error');
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
        
        // Cargar lista de archivos si está habilitada
        if ($('#wpoi-files-list').length > 0) {
            loadFilesList();
            
            // Refrescar la lista cada minuto
            setInterval(function() {
                loadFilesList();
            }, 60000);
        }
    }
    
    /**
     * Mostrar mensaje de estado
     */
    function showMessage(message, type) {
        $('#wpoi-upload-status')
            .html(message)
            .removeClass('success error')
            .addClass(type)
            .show();
    }
    
    /**
     * Cargar lista de archivos disponibles
     */
    function loadFilesList() {
        $.ajax({
            url: wpoi.rest_url + 'files',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            success: function(response) {
                if (response.success && response.data && response.data.files) {
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
    
    /**
     * Mostrar lista de archivos
     */
    function displayFilesList(files) {
        if (!files || !files.length) {
            $('#wpoi-files-list').html('<p>No hay archivos disponibles</p>');
            return;
        }
        
        var html = '<table class="wpoi-files-table">';
        html += '<thead><tr><th>Nombre</th><th>Tamaño</th><th>Acción</th></tr></thead>';
        html += '<tbody>';
        
        // Procesar archivos en la raíz
        files.forEach(function(item) {
            if (item.type === 'folder' && item.children) {
                // Es una carpeta, iterar por sus hijos
                item.children.forEach(function(file) {
                    if (file.type === 'machinecode' || 
                        (file.name && (file.name.toLowerCase().endsWith('.stl') || 
                                      file.name.toLowerCase().endsWith('.gcode')))) {
                        html += createFileRow(file.name, file.size, 'local/' + file.path);
                    }
                });
            } else if (item.type === 'machinecode' || 
                      (item.name && (item.name.toLowerCase().endsWith('.stl') || 
                                    item.name.toLowerCase().endsWith('.gcode')))) {
                // Es un archivo directamente en la raíz
                html += createFileRow(item.name, item.size, 'local/' + item.path);
            }
        });
        
        html += '</tbody></table>';
        
        // Si no hay archivos válidos
        if (html === '<table class="wpoi-files-table"><thead><tr><th>Nombre</th><th>Tamaño</th><th>Acción</th></tr></thead><tbody></tbody></table>') {
            $('#wpoi-files-list').html('<p>No hay archivos STL o GCode disponibles</p>');
            return;
        }
        
        $('#wpoi-files-list').html(html);
        
        // Añadir listeners para los botones
        $('.print-file').on('click', function() {
            var filePath = $(this).data('path');
            printFile(filePath);
        });
    }
    
    /**
     * Crear fila para un archivo
     */
    function createFileRow(name, size, path) {
        return '<tr>' +
               '<td>' + name + '</td>' +
               '<td>' + formatFileSize(size) + '</td>' +
               '<td><button class="wpoi-button print-file" data-path="' + path + '">Imprimir</button></td>' +
               '</tr>';
    }
    
    /**
     * Formatear tamaño de archivo
     */
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        
        return bytes.toFixed(1) + ' ' + units[i];
    }
    
    /**
     * Enviar archivo a imprimir
     */
    function printFile(filePath) {
        if (!confirm('¿Estás seguro de que quieres imprimir este archivo?')) {
            return;
        }
        
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
                    alert('Archivo enviado a imprimir correctamente');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error de conexión');
            }
        });
    }
});
