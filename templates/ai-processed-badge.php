<?php
/**
 * Template pour afficher un badge de traitement IA
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

$job_id = get_the_ID();
$is_processed = get_post_meta($job_id, '_ai_processed', true);

if (!$is_processed) {
    return;
}
?>

<span class="ai-badge" title="<?php esc_attr_e('Cette offre a été enrichie par IA', 'wpjm-openai'); ?>">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z" fill="currentColor"/>
    </svg>
    <?php _e('IA', 'wpjm-openai'); ?>
</span>