<?php
/**
 * Template pour afficher la metabox de statut IA dans l'admin
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

$job_id = $post->ID;
$is_processed = get_post_meta($job_id, '_ai_processed', true);
$has_error = get_post_meta($job_id, '_ai_processing_error', true);
$processing_date = get_post_meta($job_id, '_ai_processed_date', true);
$process_source = get_post_meta($job_id, '_ai_process_source', true);
?>

<div class="wpjm-openai-metabox">
    <h4><?php _e('Statut du traitement IA', 'wpjm-openai'); ?></h4>
    
    <?php if ($is_processed) : ?>
        <div class="wpjm-openai-status success">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php _e('Traité par IA', 'wpjm-openai'); ?>
            <?php if ($processing_date) : ?>
                <span class="wpjm-openai-date">
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($processing_date))); ?>
                </span>
            <?php endif; ?>
            
            <?php if ($process_source) : ?>
                <div class="wpjm-openai-source">
                    <?php printf(__('Source: %s', 'wpjm-openai'), esc_html($process_source)); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="wpjm-openai-status pending">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('Non traité par IA', 'wpjm-openai'); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($has_error) : ?>
        <div class="wpjm-openai-error">
            <strong><?php _e('Erreur lors du traitement :', 'wpjm-openai'); ?></strong>
            <p><?php echo esc_html($has_error); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="wpjm-openai-actions">
        <a href="<?php echo esc_url(admin_url('admin-post.php?action=process_job_with_ai&job_id=' . $job_id . '&_wpnonce=' . wp_create_nonce('process_job_with_ai_' . $job_id))); ?>" class="button">
            <?php echo $is_processed ? __('Retraiter avec IA', 'wpjm-openai') : __('Traiter avec IA', 'wpjm-openai'); ?>
        </a>
    </div>
</div>