<?php
/**
 * Template pour la page de paramètres OpenAI
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifier les droits d'accès
if (!current_user_can('manage_options')) {
    wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wpjm_openai_settings');
        do_settings_sections('wpjm_openai_settings');
        submit_button();
        ?>
    </form>

    <div class="wpjm-openai-test-section">
        <h2><?php _e('Tester l\'API OpenAI', 'wpjm-openai'); ?></h2>
        <p><?php _e('Cliquez sur le bouton ci-dessous pour tester la connexion à l\'API OpenAI.', 'wpjm-openai'); ?></p>
        <button id="wpjm-openai-test-api" class="button button-secondary">
            <?php _e('Tester la connexion', 'wpjm-openai'); ?>
        </button>
        <div id="wpjm-openai-test-result" style="margin-top: 10px;"></div>
    </div>
    
    <div class="wpjm-openai-usage-stats" style="margin-top: 30px;">
        <h2><?php _e('Statistiques d\'utilisation', 'wpjm-openai'); ?></h2>
        <p><?php _e('Voici les statistiques d\'utilisation de l\'API OpenAI par le plugin.', 'wpjm-openai'); ?></p>
        
        <?php
        $total_processed = get_option('wpjm_openai_stats_processed', 0);
        $total_tokens = get_option('wpjm_openai_stats_tokens', 0);
        $last_process = get_option('wpjm_openai_stats_last_process', '');
        ?>
        
        <table class="wpjm-openai-settings-table">
            <tr>
                <th><?php _e('Offres traitées', 'wpjm-openai'); ?></th>
                <td><?php echo esc_html($total_processed); ?></td>
            </tr>
            <tr>
                <th><?php _e('Tokens utilisés (estimation)', 'wpjm-openai'); ?></th>
                <td><?php echo esc_html(number_format($total_tokens)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Dernier traitement', 'wpjm-openai'); ?></th>
                <td>
                    <?php 
                    if (!empty($last_process)) {
                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_process)));
                    } else {
                        _e('Aucun', 'wpjm-openai');
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
</div>