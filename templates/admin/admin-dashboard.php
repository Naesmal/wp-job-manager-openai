<?php
/**
 * Template pour le tableau de bord principal du plugin WP Job Manager OpenAI
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les statistiques
$total_processed = get_option('wpjm_openai_stats_processed', 0);
$total_tokens = get_option('wpjm_openai_stats_tokens', 0);
$last_process = get_option('wpjm_openai_stats_last_process', '');

// Récupérer les nombres d'offres par statut
global $wpdb;
$total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status != 'trash'");
$ai_processed_jobs = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'job_listing' 
    AND p.post_status != 'trash'
    AND pm.meta_key = '_ai_processed'
    AND pm.meta_value = '1'
");
$ai_error_jobs = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'job_listing' 
    AND p.post_status != 'trash'
    AND pm.meta_key = '_ai_processing_error'
    AND pm.meta_value != ''
");
$ai_not_processed = $total_jobs - $ai_processed_jobs;

// Récupérer les logs récents
$logs_instance = WPJM_OpenAI_Logs::get_instance();
$recent_logs = $logs_instance->get_recent_logs(10);

// Récupérer les dernières offres traitées
$recent_processed_jobs = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_date, pm.meta_value as processed_date
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'job_listing'
    AND pm.meta_key = '_ai_processed_date'
    ORDER BY pm.meta_value DESC
    LIMIT 5
");
?>

<div class="wrap wpjm-openai-dashboard">
    <h1><?php _e('Tableau de bord WP Job Manager OpenAI', 'wpjm-openai'); ?></h1>
    
    <div class="wpjm-openai-stats-cards">
        <div class="wpjm-openai-card">
            <h2><?php _e('Statistiques générales', 'wpjm-openai'); ?></h2>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Offres traitées par IA:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html($total_processed); ?></span>
            </div>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Tokens utilisés:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html(number_format($total_tokens)); ?></span>
            </div>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Dernier traitement:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value">
                    <?php 
                    if (!empty($last_process)) {
                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_process)));
                    } else {
                        _e('Aucun', 'wpjm-openai');
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="wpjm-openai-card">
            <h2><?php _e('État des offres', 'wpjm-openai'); ?></h2>
            <div class="wpjm-openai-chart">
                <canvas id="jobStatusChart" width="300" height="200"></canvas>
            </div>
            <div class="wpjm-openai-stat-details">
                <div class="wpjm-openai-stat-item">
                    <span class="wpjm-openai-stat-label"><?php _e('Traitées:', 'wpjm-openai'); ?></span>
                    <span class="wpjm-openai-stat-value"><?php echo esc_html($ai_processed_jobs); ?></span>
                </div>
                <div class="wpjm-openai-stat-item">
                    <span class="wpjm-openai-stat-label"><?php _e('Non traitées:', 'wpjm-openai'); ?></span>
                    <span class="wpjm-openai-stat-value"><?php echo esc_html($ai_not_processed); ?></span>
                </div>
                <div class="wpjm-openai-stat-item">
                    <span class="wpjm-openai-stat-label"><?php _e('Avec erreurs:', 'wpjm-openai'); ?></span>
                    <span class="wpjm-openai-stat-value"><?php echo esc_html($ai_error_jobs); ?></span>
                </div>
            </div>
        </div>
        
        <div class="wpjm-openai-card wpjm-openai-actions-card">
            <h2><?php _e('Actions rapides', 'wpjm-openai'); ?></h2>
            <div class="wpjm-openai-actions">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-processed')); ?>" class="button button-primary">
                    <?php _e('Voir les offres traitées', 'wpjm-openai'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-process-bulk')); ?>" class="button">
                    <?php _e('Traitement par lot', 'wpjm-openai'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-logs')); ?>" class="button">
                    <?php _e('Voir les logs', 'wpjm-openai'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-settings')); ?>" class="button">
                    <?php _e('Configuration', 'wpjm-openai'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="wpjm-openai-card-row">
        <div class="wpjm-openai-card">
            <h2><?php _e('Offres récemment traitées', 'wpjm-openai'); ?></h2>
            <?php if (!empty($recent_processed_jobs)) : ?>
                <table class="wpjm-openai-table">
                    <thead>
                        <tr>
                            <th><?php _e('Titre', 'wpjm-openai'); ?></th>
                            <th><?php _e('Date de création', 'wpjm-openai'); ?></th>
                            <th><?php _e('Date de traitement', 'wpjm-openai'); ?></th>
                            <th><?php _e('Actions', 'wpjm-openai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_processed_jobs as $job) : ?>
                            <tr>
                                <td><?php echo esc_html($job->post_title); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($job->post_date))); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->processed_date))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($job->ID)); ?>" class="button button-small">
                                        <?php _e('Voir/Éditer', 'wpjm-openai'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=process_job_with_ai&job_id=' . $job->ID . '&_wpnonce=' . wp_create_nonce('process_job_with_ai_' . $job->ID))); ?>" class="button button-small">
                                        <?php _e('Retraiter', 'wpjm-openai'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="wpjm-openai-view-all">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-processed')); ?>" class="button">
                        <?php _e('Voir toutes les offres traitées', 'wpjm-openai'); ?>
                    </a>
                </div>
            <?php else : ?>
                <p><?php _e('Aucune offre n\'a encore été traitée.', 'wpjm-openai'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="wpjm-openai-card">
            <h2><?php _e('Logs récents', 'wpjm-openai'); ?></h2>
            <?php if (!empty($recent_logs)) : ?>
                <table class="wpjm-openai-table">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'wpjm-openai'); ?></th>
                            <th><?php _e('Offre', 'wpjm-openai'); ?></th>
                            <th><?php _e('Action', 'wpjm-openai'); ?></th>
                            <th><?php _e('Statut', 'wpjm-openai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log) : 
                            $job_title = '';
                            if (!empty($log->job_id)) {
                                $job = get_post($log->job_id);
                                if ($job) {
                                    $job_title = $job->post_title;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->log_date))); ?></td>
                                <td>
                                    <?php if (!empty($job_title)) : ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($log->job_id)); ?>">
                                            <?php echo esc_html($job_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php _e('N/A', 'wpjm-openai'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->action); ?></td>
                                <td>
                                    <span class="wpjm-openai-status-bubble status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html($log->status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="wpjm-openai-view-all">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=job_listing&page=wpjm-openai-logs')); ?>" class="button">
                        <?php _e('Voir tous les logs', 'wpjm-openai'); ?>
                    </a>
                </div>
            <?php else : ?>
                <p><?php _e('Aucun log disponible.', 'wpjm-openai'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialiser le graphique d'état des offres
    var ctx = document.getElementById('jobStatusChart').getContext('2d');
    var jobStatusChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: [
                '<?php _e('Traitées', 'wpjm-openai'); ?>',
                '<?php _e('Non traitées', 'wpjm-openai'); ?>',
                '<?php _e('Avec erreurs', 'wpjm-openai'); ?>'
            ],
            datasets: [{
                data: [
                    <?php echo esc_js($ai_processed_jobs); ?>,
                    <?php echo esc_js($ai_not_processed); ?>,
                    <?php echo esc_js($ai_error_jobs); ?>
                ],
                backgroundColor: [
                    '#4CAF50',
                    '#FFC107',
                    '#F44336'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom'
            }
        }
    });
});
</script>