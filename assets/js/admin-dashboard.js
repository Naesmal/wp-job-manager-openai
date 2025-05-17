/**
 * Script JavaScript pour le tableau de bord WP Job Manager OpenAI
 */
jQuery(document).ready(function($) {
    
    // Fonction pour traiter une offre en AJAX (utilisée dans le traitement par lot)
    window.processJobWithAjax = function(jobId, callback) {
        $.ajax({
            url: wpjm_openai_dashboard.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpjm_openai_bulk_process',
                nonce: wpjm_openai_dashboard.nonce,
                job_id: jobId
            },
            beforeSend: function() {
                if (typeof callback === 'function') {
                    callback('processing', {
                        message: wpjm_openai_dashboard.processing_text,
                        job_id: jobId
                    });
                }
            },
            success: function(response) {
                if (response.success) {
                    if (typeof callback === 'function') {
                        callback('success', {
                            message: response.data.message,
                            job_id: response.data.job_id,
                            job_title: response.data.job_title
                        });
                    }
                } else {
                    if (typeof callback === 'function') {
                        callback('error', {
                            message: response.data.message,
                            job_id: response.data.job_id,
                            job_title: response.data.job_title
                        });
                    }
                }
            },
            error: function(xhr, status, error) {
                if (typeof callback === 'function') {
                    callback('error', {
                        message: error,
                        job_id: jobId
                    });
                }
            }
        });
    };
    
    // Traitement par lot AJAX
    $('#wpjm-openai-ajax-bulk-process').on('click', function(e) {
        e.preventDefault();
        
        // Récupérer les paramètres
        var limit = $('#job_limit').val();
        var jobStatus = $('#job_status').val();
        var processType = $('#process_type').val();
        
        // Vérifier que limit est un nombre valide
        limit = parseInt(limit);
        if (isNaN(limit) || limit < 1) {
            limit = 10;
        }
        
        // Récupérer les IDs des offres à traiter
        $.ajax({
            url: wpjm_openai_dashboard.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpjm_openai_get_jobs_to_process',
                nonce: wpjm_openai_dashboard.nonce,
                limit: limit,
                job_status: jobStatus,
                process_type: processType
            },
            beforeSend: function() {
                // Afficher l'indicateur de chargement
                $('#wpjm-openai-progress-container').show();
                $('#wpjm-openai-progress-bar').css('width', '0%').text('0%');
                $('#wpjm-openai-progress-status').text(wpjm_openai_dashboard.processing_text);
                $('#wpjm-openai-results-container').hide();
                $('.wpjm-openai-bulk-form').find('input, select, button').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    var jobIds = response.data.job_ids;
                    
                    if (jobIds.length === 0) {
                        // Aucune offre à traiter
                        $('#wpjm-openai-progress-status').text(response.data.message);
                        $('#wpjm-openai-progress-bar').css('width', '100%').text('100%');
                        $('.wpjm-openai-bulk-form').find('input, select, button').prop('disabled', false);
                        return;
                    }
                    
                    // Initialiser les résultats
                    var results = {
                        success: 0,
                        error: 0,
                        errors: []
                    };
                    
                    // Traiter chaque offre séquentiellement
                    var totalJobs = jobIds.length;
                    var processedJobs = 0;
                    
                    function processNextJob() {
                        if (processedJobs >= totalJobs) {
                            // Tous les jobs ont été traités
                            updateProgressBar(100);
                            
                            // Afficher les résultats
                            $('#wpjm-openai-success-count').text(results.success);
                            $('#wpjm-openai-error-count').text(results.error);
                            
                            if (results.error > 0) {
                                // Afficher les détails des erreurs
                                var errorsHtml = '';
                                $.each(results.errors, function(index, error) {
                                    errorsHtml += '<tr>' +
                                        '<td>' + error.job_id + '</td>' +
                                        '<td>' + error.job_title + '</td>' +
                                        '<td>' + error.message + '</td>' +
                                        '<td><a href="' + error.edit_url + '" class="button button-small">' + wpjm_openai_dashboard.edit_text + '</a></td>' +
                                        '</tr>';
                                });
                                
                                $('#wpjm-openai-error-details tbody').html(errorsHtml);
                                $('#wpjm-openai-error-details').show();
                            } else {
                                $('#wpjm-openai-error-details').hide();
                            }
                            
                            $('#wpjm-openai-progress-status').text(wpjm_openai_dashboard.completed_text);
                            $('#wpjm-openai-results-container').show();
                            $('.wpjm-openai-bulk-form').find('input, select, button').prop('disabled', false);
                            
                            return;
                        }
                        
                        var currentJobId = jobIds[processedJobs];
                        
                        // Traiter l'offre actuelle
                        processJobWithAjax(currentJobId, function(status, data) {
                            if (status === 'success') {
                                results.success++;
                                $('#wpjm-openai-progress-status').text(wpjm_openai_dashboard.success_text + ': ' + data.job_title);
                            } else if (status === 'error') {
                                results.error++;
                                results.errors.push({
                                    job_id: data.job_id,
                                    job_title: data.job_title || 'Job #' + data.job_id,
                                    message: data.message,
                                    edit_url: ajaxurl.replace('admin-ajax.php', 'post.php?post=' + data.job_id + '&action=edit')
                                });
                                $('#wpjm-openai-progress-status').text(wpjm_openai_dashboard.error_text + ': ' + data.message);
                            }
                            
                            // Mettre à jour la progression
                            processedJobs++;
                            updateProgressBar(Math.floor((processedJobs / totalJobs) * 100));
                            
                            // Traiter l'offre suivante
                            setTimeout(processNextJob, 500);
                        });
                    }
                    
                    // Fonction pour mettre à jour la barre de progression
                    function updateProgressBar(percentage) {
                        $('#wpjm-openai-progress-bar').css('width', percentage + '%').text(percentage + '%');
                    }
                    
                    // Démarrer le traitement
                    processNextJob();
                } else {
                    // Erreur lors de la récupération des offres
                    $('#wpjm-openai-progress-status').text(response.data.message);
                    $('.wpjm-openai-bulk-form').find('input, select, button').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                // Erreur AJAX
                $('#wpjm-openai-progress-status').text('Erreur: ' + error);
                $('.wpjm-openai-bulk-form').find('input, select, button').prop('disabled', false);
            }
        });
    });
    
    // Gestion des onglets dans toute l'interface
    $('.wpjm-openai-tabs .tab-item').on('click', function() {
        var targetTab = $(this).data('tab');
        
        // Activer l'onglet
        $('.wpjm-openai-tabs .tab-item').removeClass('active');
        $(this).addClass('active');
        
        // Afficher le contenu correspondant
        $('.wpjm-openai-tab-content').removeClass('active');
        $('#tab-' + targetTab).addClass('active');
    });
    
    // Mise à jour du témoin de température
    $('#wpjm_openai_temperature').on('input', function() {
        $('#temperature_value').text($(this).val());
    });
    
    // Confirmation pour les actions potentiellement dangereuses
    $('.wpjm-openai-confirm-action').on('click', function(e) {
        if (!confirm($(this).data('confirm-message') || 'Êtes-vous sûr de vouloir effectuer cette action?')) {
            e.preventDefault();
        }
    });
    
    // Popup pour afficher les détails complets des logs
    $('.wpjm-openai-details-preview').on('click', function() {
        var details = $(this).next('.wpjm-openai-details-popup').html();
        
        $('<div></div>')
            .html('<pre>' + details + '</pre>')
            .dialog({
                title: wpjm_openai_dashboard.details_text || 'Détails',
                dialogClass: 'wp-dialog',
                autoOpen: true,
                draggable: true,
                width: 600,
                maxHeight: 400,
                modal: true,
                buttons: {
                    "Fermer": function() {
                        $(this).dialog('close');
                    }
                },
                close: function(event, ui) {
                    $(this).remove();
                }
            });
    });
    
    // Actions en lot dans la liste des offres
    $('#doaction, #doaction2').on('click', function(e) {
        var selectedAction = $(this).prev('select').val();
        
        if (selectedAction === 'reprocess') {
            e.preventDefault();
            
            // Récupérer les IDs des offres sélectionnées
            var jobIds = [];
            $('input[name="jobs[]"]:checked').each(function() {
                jobIds.push($(this).val());
            });
            
            if (jobIds.length === 0) {
                alert(wpjm_openai_dashboard.no_jobs_selected_text || 'Aucune offre sélectionnée.');
                return;
            }
            
            if (!confirm(wpjm_openai_dashboard.reprocess_confirm_text || 'Êtes-vous sûr de vouloir retraiter ces offres avec l\'IA?')) {
                return;
            }
            
            // Afficher l'indicateur de traitement
            var $processingDiv = $('<div id="wpjm-openai-batch-processing"></div>')
                .html('<p>' + wpjm_openai_dashboard.processing_text + ' <span id="wpjm-openai-batch-progress">0/' + jobIds.length + '</span></p>' +
                      '<div class="wpjm-openai-batch-progress-bar"><div class="wpjm-openai-batch-progress-fill" style="width: 0%"></div></div>')
                .appendTo('body')
                .dialog({
                    title: wpjm_openai_dashboard.batch_processing_text || 'Traitement par lot',
                    dialogClass: 'wp-dialog',
                    autoOpen: true,
                    draggable: false,
                    width: 400,
                    modal: true,
                    closeOnEscape: false,
                    open: function(event, ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog || ui).hide();
                    }
                });
            
            // Traiter chaque offre séquentiellement
            var processedCount = 0;
            var successCount = 0;
            var errorCount = 0;
            
            function processBatchNextJob() {
                if (processedCount >= jobIds.length) {
                    // Toutes les offres ont été traitées
                    setTimeout(function() {
                        $processingDiv.dialog('close');
                        alert(wpjm_openai_dashboard.batch_completed_text + '\n' +
                              successCount + ' ' + wpjm_openai_dashboard.success_text + '\n' +
                              errorCount + ' ' + wpjm_openai_dashboard.error_text);
                        location.reload();
                    }, 1000);
                    return;
                }
                
                var currentJobId = jobIds[processedCount];
                
                // Traiter l'offre actuelle
                processJobWithAjax(currentJobId, function(status, data) {
                    processedCount++;
                    
                    if (status === 'success') {
                        successCount++;
                    } else if (status === 'error') {
                        errorCount++;
                    }
                    
                    // Mettre à jour l'indicateur de progression
                    var percentage = Math.floor((processedCount / jobIds.length) * 100);
                    $('#wpjm-openai-batch-progress').text(processedCount + '/' + jobIds.length);
                    $('.wpjm-openai-batch-progress-fill').css('width', percentage + '%');
                    
                    // Traiter l'offre suivante
                    setTimeout(processBatchNextJob, 500);
                });
            }
            
            // Démarrer le traitement
            processBatchNextJob();
        }
    });
});