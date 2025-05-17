<?php
/**
 * Template pour afficher le bouton de traitement IA dans le tableau de bord
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

$job_id = $job->ID;
$is_processed = get_post_meta($job_id, '_ai_processed', true);
$has_error = get_post_meta($job_id, '_ai_processing_error', true);
$processing_date = get_post_meta($job_id, '_ai_processed_date', true);

$url = admin_url('admin-post.php');
$url = add_query_arg(array(
    'action' => 'process_job_with_ai',
    'job_id' => $job_id,
    '_wpnonce' => wp_create_nonce('process_job_with_ai_' . $job_id),
), $url);

// Classe et texte du bouton
$button_class = 'button ai-process-button';
$button_text = __('Traiter avec IA', 'wpjm-openai');

// Si déjà traité, modifier le texte
if ($is_processed) {
    $button_text = __('Retraiter avec IA', 'wpjm-openai');
}
?>

<a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($button_class); ?>">
    <?php echo esc_html($button_text); ?>
</a>

<?php if ($is_processed || $has_error) : ?>
    <div class="ai-status">
        <?php if ($is_processed) : ?>
            <span class="ai-processed-indicator processed">
                <?php echo esc_html__('Traité le', 'wpjm-openai'); ?> 
                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($processing_date))); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($has_error) : ?>
            <span class="ai-processed-indicator error" title="<?php echo esc_attr($has_error); ?>">
                <?php echo esc_html__('Erreur AI', 'wpjm-openai'); ?>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>