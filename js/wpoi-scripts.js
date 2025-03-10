/**
 * WordPress OctoPrint Integration - Scripts principales
 */
jQuery(document).ready(function($) {
    // Variables
    let refreshInterval;
    const updateInterval = 5000; // Actualizar cada 5 segundos
    
    // Variables para el seguimiento del estado
    let printerStatus = 'Offline';
    let isOperational = false;
    let isPrinting = false;
    let isPaused = false;
    
    // Inicializar si existe el contenedor
    if ($('.wpoi-container').length > 0 && $('#wpoi-printer-status').length > 0) {
        // Iniciar actualizaciones periódicas
        initUpdates();
        
        // Configurar eventos de botones
        setupButtons();
        
        // Actualizar webcam
        updateWebcam();
    }
    
    /**
     * Iniciar actualizaciones periódicas
     */
    function initUpdates() {
        // Primera actualización inmediatamente
        updatePrinterStatus();
        updateJobStatus();
        
        // Configurar intervalo para actualizaciones periódicas
        refreshInterval = setInterval(function() {
            updatePrinterStatus();
            updateJobStatus();
            updateWebcam();
        }, updateInterval);
    }
    
    /**
     * Actualizar estado de la impresora
     */
    function updatePrinterStatus() {
        $.ajax({
            url: wpoi.rest_url + 'printer',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar estado general
                    const operational = response.data.state.flags.operational;
                    const printing = response.data.state.flags.printing;
                    const paused = response.data.state.flags.paused;
                    
                    let statusText = '';
                    if (!operational) {
                        statusText = '<span class="wpoi-status-offline">Desconectada</span>';
                    } else if (printing) {
                        statusText = '<span class="wpoi-status-printing">Imprimiendo</span>';
                    } else if (paused) {
                        statusText = '<span class="wpoi-status-paused">Pausada</span>';
                    } else {
                        statusText = '<span class="wpoi-status-operational">Lista</span>';
                    }
                    
                    $('#wpoi-printer-status').html(statusText);
                    
                    // Actualizar temperaturas si están disponibles
                    if (response.data.temperature && $('#wpoi-temperature-data').length > 0) {
                        let tempHtml = '<table class="wpoi-temp-table">';
                        tempHtml += '<tr><th>Sensor</th><th>Actual</th><th>Objetivo</th></tr>';
                        
                        // Extrusor
                        if (response.data.temperature.tool0) {
                            const tool = response.data.temperature.tool0;
                            tempHtml += `<tr>
                                <td>Extrusor</td>
                                <td>${tool.actual.toFixed(1)}°C</td>
                                <td>${tool.target.toFixed(1)}°C</td>
                            </tr>`;
                        }
                        
                        // Cama caliente
                        if (response.data.temperature.bed) {
                            const bed = response.data.temperature.bed;
                            tempHtml += `<tr>
                                <td>Cama</td>
                                <td>${bed.actual.toFixed(1)}°C</td>
                                <td>${bed.target.toFixed(1)}°C</td>
                            </tr>`;
                        }
                        
                        tempHtml += '</table>';
                        $('#wpoi-temperature-data').html(tempHtml);
                    }
                } else {
                    $('#wpoi-printer-status').html('<span class="wpoi-status-error">Error: ' + response.message + '</span>');
                }
            },
            error: function() {
                $('#wpoi-printer-status').html('<span class="wpoi-status-error">Error de conexión</span>');
            }
        });
    }
    
    /**
     * Actualizar estado del trabajo actual
     */
    function updateJobStatus() {
        if ($('#wpoi-progress-inner').length === 0) return;
        
        $.ajax({
            url: wpoi.rest_url + 'job',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            success: function(response) {
                if (response.success && response.data.job) {
                    const job = response.data.job;
                    const progress = response.data.progress;
                    
                    if (job.file && job.file.name) {
                        // Actualizar información del trabajo
                        let jobHtml = `<p><strong>Archivo:</strong> ${job.file.name}</p>`;
                        
                        // Actualizar barra de progreso
                        if (progress.completion !== null) {
                            const completionPercent = progress.completion.toFixed(1);
                            $('#wpoi-progress-inner').css('width', completionPercent + '%').text(completionPercent + '%');
                            
                            // Tiempo estimado
                            if (progress.printTimeLeft !== null) {
                                const printTimeLeft = formatTime(progress.printTimeLeft);
                                jobHtml += `<p><strong>Tiempo restante:</strong> ${printTimeLeft}</p>`;
                            }
                            
                            // Tiempo impreso
                            if (progress.printTime !== null) {
                                const printTime = formatTime(progress.printTime);
                                jobHtml += `<p><strong>Tiempo impreso:</strong> ${printTime}</p>`;
                            }
                        } else {
                            $('#wpoi-progress-inner').css('width', '0%').text('0%');
                        }
                        
                        $('#wpoi-job-info').html(jobHtml);
                    } else {
                        $('#wpoi-progress-inner').css('width', '0%').text('0%');
                        $('#wpoi-job-info').html('<p>Sin trabajo activo</p>');
                    }
                } else {
                    $('#wpoi-progress-inner').css('width', '0%').text('0%');
                    $('#wpoi-job-info').html('<p>Sin trabajo activo</p>');
                }
            },
            error: function() {
                $('#wpoi-progress-inner').css('width', '0%').text('0%');
                $('#wpoi-job-info').html('<p>Error al obtener información del trabajo</p>');
            }
        });
    }
    
    /**
     * Actualizar imagen de webcam
     */
    function updateWebcam() {
        if ($('#wpoi-webcam-image').length === 0) return;
        
        // Utiliza timestamp para evitar caché
        const timestamp = new Date().getTime();
        const webcamUrl = wpoi.octoprint_url + '/webcam/?_=' + timestamp;
        $('#wpoi-webcam-image').attr('src', webcamUrl);
    }
    
    /**
     * Configurar botones de control
     */
    function setupButtons() {
        // Botón Home
        $('#wpoi-btn-home').on('click', function() {
            sendCommand('home', { axes: ['x', 'y', 'z'] });
        });
        
        // Botón Pausar - Ahora usa sendJobCommand en lugar de sendCommand
        $('#wpoi-btn-pause').on('click', function() {
            sendJobCommand('pause', 'pause');
        });
        
        // Botón Reanudar - Ahora usa sendJobCommand en lugar de sendCommand
        $('#wpoi-btn-resume').on('click', function() {
            sendJobCommand('pause', 'resume');
        });
        
        // Botón Cancelar - Ahora usa sendJobCommand en lugar de sendCommand
        $('#wpoi-btn-cancel').on('click', function() {
            if (confirm('¿Estás seguro de que quieres cancelar la impresión?')) {
                sendJobCommand('cancel');
            }
        });
    }
    
    /**
     * Enviar comando a OctoPrint
     */
    function sendCommand(command, params = {}) {
        $.ajax({
            url: wpoi.rest_url + 'command',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            data: {
                command: command,
                action: params
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar inmediatamente después de un comando
                    updatePrinterStatus();
                    updateJobStatus();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error de conexión al enviar el comando');
            }
        });
    }
    
    /**
     * Enviar comando de trabajo a OctoPrint (pause, resume, cancel)
     */
    function sendJobCommand(command, action = null) {
        console.log('Sending job command:', command, 'Action:', action);
        
        const data = {
            command: command
        };
        
        // Si se proporciona una acción (para pausar/reanudar)
        if (action) {
            data.action = action;
        }
        
        $.ajax({
            url: wpoi.rest_url + 'job',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpoi.nonce);
            },
            data: data,
            success: function(response) {
                console.log('Job command response:', response);
                
                if (response.success) {
                    // Actualizar estado según el comando
                    if (command === 'pause' && action === 'pause') {
                        isPaused = true;
                        isPrinting = false;
                        $('#wpoi-printer-status').text('Pausada').removeClass().addClass('wpoi-status-paused');
                    } else if (command === 'pause' && action === 'resume') {
                        isPaused = false;
                        isPrinting = true;
                        $('#wpoi-printer-status').text('Imprimiendo').removeClass().addClass('wpoi-status-printing');
                    } else if (command === 'cancel') {
                        isPaused = false;
                        isPrinting = false;
                        $('#wpoi-printer-status').text('Conectada y lista').removeClass().addClass('wpoi-status-operational');
                        $('#wpoi-progress-inner').css('width', '0%').text('0%');
                        $('#wpoi-job-info').html('Sin trabajo activo');
                    }
                    
                    // Actualizar inmediatamente después de un comando
                    setTimeout(function() {
                        updatePrinterStatus();
                        updateJobStatus();
                    }, 1000); // Small delay to allow OctoPrint to update
                } else {
                    alert('Error: ' + (response.message || 'Fallo al enviar el comando de trabajo'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.log('Response text:', xhr.responseText);
                alert('Error de conexión al enviar el comando de trabajo');
            }
        });
    }
    
    /**
     * Formatear tiempo en segundos a formato legible
     */
    function formatTime(seconds) {
        if (seconds === null || seconds === undefined || seconds < 0) {
            return 'Desconocido';
        }
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        
        return hours + 'h ' + minutes + 'm';
    }
});
