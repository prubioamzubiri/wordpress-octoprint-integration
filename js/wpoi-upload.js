/**
 * WordPress OctoPrint Integration - Scripts para subida de STL y GCODE
 */
jQuery(document).ready(function($) {
    // Verificar si existe el formulario de subida
    if ($('#wpoi-stl-upload-form').length > 0) {
        
        // Variable para seguimiento de carpeta actual
        var currentPath = '';
        
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
            
            // Add delegated event handlers
            $('#wpoi-files-list').on('click', '.delete-file', function() {
                var filePath = $(this).data('path');
                if (confirm('¿Está seguro de que desea eliminar este archivo?')) {
                    deleteFile(filePath);
                }
            });
            
            $('#wpoi-files-list').on('click', '.delete-folder', function() {
                var folderPath = $(this).data('path');
                if (confirm('¿Está seguro de que desea eliminar esta carpeta y todo su contenido?')) {
                    deleteFolder(folderPath);
                }
            });
            
            $('#wpoi-files-list').on('click', '.folder-link', function(e) {
                e.preventDefault();
                currentPath = $(this).data('path');
                loadFilesList();
            });
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
        var url = wpoi.rest_url + 'files';
        
        // Add path parameter if in a subfolder
        if (currentPath) {
            url += '?path=' + encodeURIComponent(currentPath);
        }
        
        // Show loading message
        $('#wpoi-files-list').html('<p>Cargando archivos...</p>');
        
        console.log('Loading files from path:', currentPath);
        
        $.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            success: function(response) {
                console.log('API Response:', response);
                if (response.success) {
                    // Handle different response structures
                    if (response.data && response.data.files) {
                        // Root folder response
                        displayFilesList(response.data.files);
                    } else if (response.data && response.data.children) {
                        // Subfolder response
                        displayFilesList(response.data.children);
                    } else if (response.data) {
                        // Direct data response
                        displayFilesList(response.data);
                    } else {
                        $('#wpoi-files-list').html('<p>No se encontraron archivos</p>');
                    }
                } else {
                    var errorMsg = response.message || 'Error desconocido';
                    $('#wpoi-files-list').html('<p>Error al cargar la lista de archivos: ' + errorMsg + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', status, error);
                $('#wpoi-files-list').html('<p>Error de conexión</p>');
            }
        });
    }
    
    /**
     * Mostrar lista de archivos
     */
    function displayFilesList(files) {
        // Handle edge cases first
        if (!files) {
            $('#wpoi-files-list').html('<p>No hay archivos disponibles</p>');
            return;
        }
        
        // Ensure files is an array
        if (!Array.isArray(files)) {
            // If it's a single object, convert to array
            if (typeof files === 'object') {
                files = [files];
            } else {
                $('#wpoi-files-list').html('<p>Formato de respuesta no válido</p>');
                return;
            }
        }
        
        // Build navigation breadcrumb
        var navHtml = '';
        if (currentPath) {
            navHtml += '<div class="wpoi-folder-nav">';
            navHtml += '<a href="#" class="folder-link" data-path="">Inicio</a>';
            
            var pathParts = currentPath.split('/');
            var buildPath = '';
            for (var i = 0; i < pathParts.length; i++) {
                if (pathParts[i]) {
                    if (buildPath) buildPath += '/';
                    buildPath += pathParts[i];
                    navHtml += ' / ';
                    if (i === pathParts.length - 1) {
                        navHtml += pathParts[i];
                    } else {
                        navHtml += '<a href="#" class="folder-link" data-path="' + buildPath + '">' + pathParts[i] + '</a>';
                    }
                }
            }
            navHtml += '</div>';
        }
        
        var html = navHtml;
        html += '<table class="wpoi-files-table">';
        html += '<thead><tr><th>Nombre</th><th>Tamaño</th><th>Acción</th></tr></thead>';
        html += '<tbody>';
        
        // Add up-directory link if in a subfolder
        if (currentPath) {
            var parentPath = getParentPath(currentPath);
            html += '<tr class="folder-row">';
            html += '<td colspan="2"><a href="#" class="folder-link" data-path="' + parentPath + 
                   '"><span class="folder-icon">📁</span> ..</a></td>';
            html += '<td></td></tr>';
        }
        
        // Separate folders and files
        var folders = [];
        var fileList = [];
        
        // Process files and folders
        files.forEach(function(item) {
            if (item.type === 'folder') {
                folders.push(item);
            } else if (item.type === 'machinecode' || item.type === 'model' || 
                      (item.name && (item.name.toLowerCase().endsWith('.stl') || 
                                   item.name.toLowerCase().endsWith('.gcode')))) {
                fileList.push(item);
            }
        });
        
        console.log('Folders found:', folders.length);
        console.log('Files found:', fileList.length);
        
        // Display folders first
        folders.forEach(function(folder) {
            var folderPath = currentPath ? currentPath + '/' + folder.name : folder.name;
            html += '<tr class="folder-row">';
            html += '<td colspan="2"><a href="#" class="folder-link" data-path="' + folderPath + 
                   '"><span class="folder-icon">📁</span> ' + folder.name + '</a></td>';
            html += '<td><button class="wpoi-button delete-folder" data-path="local/' + folderPath + '">Eliminar</button></td></tr>';
        });
        
        // Then display files
        fileList.forEach(function(file) {
            var filePath = 'local/';
            if (file.path) {
                filePath += file.path;
            } else if (currentPath) {
                filePath += currentPath + '/' + file.name;
            } else {
                filePath += file.name;
            }
            
            html += createFileRow(file.name, file.size, filePath);
        });
        
        html += '</tbody></table>';
        
        // Add create folder button
        html += '<div class="wpoi-folder-actions">';
        html += '<button class="wpoi-button create-folder">Crear carpeta</button>';
        html += '</div>';
        
        // If no files or folders found
        if (folders.length === 0 && fileList.length === 0) {
            var emptyHtml = navHtml;
            emptyHtml += '<p>No hay archivos ni carpetas en esta ubicación</p>';
            emptyHtml += '<div class="wpoi-folder-actions">';
            emptyHtml += '<button class="wpoi-button create-folder">Crear carpeta</button>';
            if (currentPath) {
                emptyHtml += ' <button class="wpoi-button folder-link" data-path="' + getParentPath(currentPath) + '">Subir un nivel</button>';
            }
            emptyHtml += '</div>';
            $('#wpoi-files-list').html(emptyHtml);
        } else {
            $('#wpoi-files-list').html(html);
        }
        
        // Set up event handlers
        setupEventHandlers();
    }
    
    /**
     * Crear fila para un archivo
     */
    function createFileRow(name, size, path) {
        return '<tr>' +
               '<td>' + name + '</td>' +
               '<td>' + formatFileSize(size) + '</td>' +
               '<td>' + 
               '<button class="wpoi-button print-file" data-path="' + path + '">Imprimir</button> ' +
               '<button class="wpoi-button delete-file" data-path="' + path + '">Eliminar</button>' +
               '</td>' +
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
    
    /**
     * Eliminar un archivo
     */
    function deleteFile(filePath) {
        $.ajax({
            url: wpoi.rest_url + 'delete-file',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            data: {
                file_path: filePath
            },
            success: function(response) {
                if (response.success) {
                    alert('Archivo eliminado correctamente');
                    // Recargar la lista de archivos
                    loadFilesList();
                } else {
                    var errorMsg = response.message || 'No se pudo eliminar el archivo';
                    alert('Error: ' + errorMsg);
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error de conexión';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert('Error: ' + errorMsg);
            }
        });
    }
    
    /**
     * Get parent path from current path
     */
    function getParentPath(path) {
        if (!path) return '';
        var parts = path.split('/');
        parts.pop();
        return parts.join('/');
    }
    
    /**
     * Create a new folder
     */
    function createFolder() {
        var folderName = prompt('Ingrese el nombre para la nueva carpeta:');
        if (!folderName) return;
        
        // Validate folder name
        if (!/^[a-zA-Z0-9_\-\s]+$/.test(folderName)) {
            alert('Nombre de carpeta inválido. Use solo letras, números, espacios, guiones y guiones bajos.');
            return;
        }
        
        // Show a loading message
        var loadingMsg = 'Creando carpeta...';
        if ($('#wpoi-upload-status').length) {
            $('#wpoi-upload-status').html(loadingMsg).removeClass('error success').addClass('info').show();
        } else {
            alert(loadingMsg);
        }
        
        console.log('Creating folder:', folderName, 'in path:', currentPath);
        
        // Use FormData to properly construct multipart/form-data request
        var formData = new FormData();
        formData.append('folder_name', folderName);
        if (currentPath) {
            formData.append('path', currentPath);
        }
        
        $.ajax({
            url: wpoi.rest_url + 'create-folder',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            success: function(response) {
                console.log('Folder creation response:', response);
                
                if (response.success) {
                    if ($('#wpoi-upload-status').length) {
                        $('#wpoi-upload-status').html('Carpeta creada correctamente').removeClass('error info').addClass('success');
                    } else {
                        alert('Carpeta creada correctamente');
                    }
                    loadFilesList();
                } else {
                    var errorMsg = response.message || 'No se pudo crear la carpeta';
                    console.error('Folder creation error:', errorMsg);
                    
                    if ($('#wpoi-upload-status').length) {
                        $('#wpoi-upload-status').html('Error: ' + errorMsg).removeClass('success info').addClass('error');
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Folder creation AJAX error:', status, error);
                
                var errorMsg = 'Error de conexión';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                if ($('#wpoi-upload-status').length) {
                    $('#wpoi-upload-status').html('Error: ' + errorMsg).removeClass('success info').addClass('error');
                } else {
                    alert('Error al crear la carpeta: ' + errorMsg);
                }
            }
        });
    }
    
    /**
     * Delete a folder
     */
    function deleteFolder(folderPath) {
        $.ajax({
            url: wpoi.rest_url + 'delete-folder',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            data: {
                folder_path: folderPath
            },
            success: function(response) {
                if (response.success) {
                    alert('Carpeta eliminada correctamente');
                    
                    // If we deleted the current folder, go up one level
                    if (currentPath && folderPath.endsWith(currentPath)) {
                        currentPath = getParentPath(currentPath);
                    }
                    
                    loadFilesList();
                } else {
                    alert('Error: ' + (response.message || 'No se pudo eliminar la carpeta'));
                }
            },
            error: function() {
                alert('Error al eliminar la carpeta');
            }
        });
    }
    
    /**
     * Setup event handlers for file and folder actions
     */
    function setupEventHandlers() {
        // File print button
        $('.print-file').on('click', function() {
            var filePath = $(this).data('path');
            printFile(filePath);
        });
        
        // File delete button
        $('.delete-file').on('click', function() {
            var filePath = $(this).data('path');
            if (confirm('¿Está seguro de que desea eliminar este archivo?')) {
                deleteFile(filePath);
            }
        });
        
        // Folder delete button
        $('.delete-folder').on('click', function() {
            var folderPath = $(this).data('path');
            if (confirm('¿Está seguro de que desea eliminar esta carpeta y todo su contenido?')) {
                deleteFolder(folderPath);
            }
        });
        
        // Folder navigation
        $('.folder-link').on('click', function(e) {
            e.preventDefault();
            currentPath = $(this).data('path');
            loadFilesList();
        });
        
        // Create folder button
        $('.create-folder').on('click', function() {
            createFolder();
        });
    }
});
