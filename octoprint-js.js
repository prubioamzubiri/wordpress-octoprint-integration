/**
 * WordPress OctoPrint Integration - Frontend Scripts
 */
jQuery(document).ready(function($) {
    // Variables
    let refreshInterval;
    const updateInterval = 5000; // Actualizar cada 5 segundos
    
    // Inicializar si existe el contenedor
    if ($('.wpoi-container').length > 0) {
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
                    if (response.data.temperature) {
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
                        if (progress.completion) {
                            const completionPercent = progress.completion.toFixed(1);
                            $('#wpoi-progress-inner').css('width', completionPercent + '%').text(completionPercent + '%');
                            
                            // Tiempo estimado
                            const printTimeLeft = formatTime(progress.printTimeLeft);
                            jobHtml += `<p><strong>Tiempo restante:</strong> ${printTimeLeft}</p>`;
                            
                            // Tiempo impreso
                            const printTime = formatTime(progress.printTime);
                            jobHtml += `<p><strong>Tiempo impreso:</strong> ${printTime}</p>`;
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
            }
        });
    }
    
    /**
     * Actualizar imagen de webcam
     */
    function updateWebcam() {
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
        
        // Botón Pausar
        $('#wpoi-btn-pause').on('click', function() {
            sendCommand('pause', { action: 'pause' });
        });
        
        // Botón Reanudar
        $('#wpoi-btn-resume').on('click', function() {
            sendCommand('pause', { action: 'resume' });
        });
        
        // Botón Cancelar
        $('#wpoi-btn-cancel').on('click', function() {
            if (confirm('¿Estás seguro de que quieres cancelar la impresión?')) {
                sendCommand('cancel');
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
                params: params
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
     * Formatear tiempo en segundos a formato legible
     */
    function formatTime(seconds) {
        if (!seconds || seconds < 0) {
            return 'Desconocido';
        }
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        
        return hours + 'h ' + minutes + 'm';
    }
});
