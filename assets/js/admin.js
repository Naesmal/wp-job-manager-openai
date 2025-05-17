/**
 * JavaScript pour l'interface d'administration du plugin WP Job Manager OpenAI
 */
jQuery(document).ready(function($) {
    // Mise à jour dynamique de la valeur de température
    $('#wpjm_openai_temperature').on('input', function() {
        $('#temperature_value').text($(this).val());
    });

    // Test de connexion API
    $('#wpjm-openai-test-api').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $result = $('#wpjm-openai-test-result');
        
        // Changer le texte du bouton et désactiver
        const originalText = $button.text();
        $button.text('Test en cours...').prop('disabled', true);
        
        // Effacer les résultats précédents
        $result.removeClass('success error').empty();
        
        // Faire une requête AJAX pour tester l'API
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpjm_openai_test_api',
                nonce: wpjm_openai_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').html(
                        '<strong>Connexion réussie!</strong><br>' + 
                        'Modèle: ' + response.data.model + '<br>' +
                        'Message: ' + response.data.message
                    );
                } else {
                    $result.addClass('error').html(
                        '<strong>Erreur de connexion:</strong><br>' + 
                        response.data.message
                    );
                }
            },
            error: function() {
                $result.addClass('error').text('Erreur de connexion au serveur. Veuillez réessayer.');
            },
            complete: function() {
                // Restaurer le bouton
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Confirmation avant traitement manuel
    $('.ai-process-button').on('click', function(e) {
        if (!confirm('Voulez-vous vraiment traiter cette offre avec l\'IA? Cela peut écraser certaines données existantes.')) {
            e.preventDefault();
        }
    });

    // Initialiser les tooltips pour les champs d'aide
    if (typeof $.fn.tooltip === 'function') {
        $('.wpjm-openai-help-tip').tooltip({
            content: function() {
                return $(this).attr('title');
            },
            position: {
                my: 'center bottom-5',
                at: 'center top',
                collision: 'none'
            }
        });
    }
});