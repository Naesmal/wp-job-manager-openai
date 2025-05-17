<?php
/**
 * Template pour la metabox détaillée du traitement IA d'une offre d'emploi
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
$category_explanation = get_post_meta($job_id, '_ai_category_explanation', true);

// Récupérer les logs spécifiques à cette offre
$logs_instance = WPJM_OpenAI_Logs::get_instance();
$job_logs = $logs_instance->get_logs_for_job($job_id, 10);

// Catégories assignées
$categories = get_the_terms($job_id, 'job_listing_category');
if (!$categories) {
    $categories = array();
}

// Types de consultance
$consultancy_types = get_option('wpjm_openai_consultancy_types', array());
$active_consultancy_types = array();
foreach ($consultancy_types as $key => $label) {
    $active_consultancy_types[$key] = get_post_meta($job_id, '_job_consultancy_' . $key, true) ? true : false;
}

// Caractéristiques du poste
$job_location = get_post_meta($job_id, '_job_location', true);
$job_country = get_post_meta($job_id, '_job_country', true);
$job_region = get_post_meta($job_id, '_job_region', true);
$job_is_global = get_post_meta($job_id, '_job_is_global', true) ? true : false;
$job_is_hybrid = get_post_meta($job_id, '_job_is_hybrid', true) ? true : false;
$job_is_remote = get_post_meta($job_id, '_remote_position', true) ? true : false;
$job_application = get_post_meta($job_id, '_application', true);
$job_expires = get_post_meta($job_id, '_job_expires', true);
?>

<div class="wpjm-openai-metabox-detailed">
    <!-- Onglets -->
    <ul class="wpjm-openai-metabox-tabs">
        <li class="tab-item active" data-tab="status"><?php _e('Statut', 'wpjm-openai'); ?></li>
        <li class="tab-item" data-tab="location"><?php _e('Localisation', 'wpjm-openai'); ?></li>
        <li class="tab-item" data-tab="categories"><?php _e('Catégories', 'wpjm-openai'); ?></li>
        <li class="tab-item" data-tab="consultancy"><?php _e('Type de consultance', 'wpjm-openai'); ?></li>
        <li class="tab-item" data-tab="logs"><?php _e('Logs', 'wpjm-openai'); ?></li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="wpjm-openai-tab-content active" id="tab-status">
        <?php if ($is_processed) : ?>
            <div class="wpjm-openai-status success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php _e('Traité par IA', 'wpjm-openai'); ?>
                <?php if ($processing_date) : ?>
                    <span class="wpjm-openai-date">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($processing_date))); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($process_source) : ?>
                <div class="wpjm-openai-detail-item">
                    <span class="wpjm-openai-detail-label"><?php _e('Source:', 'wpjm-openai'); ?></span>
                    <span class="wpjm-openai-detail-value"><?php echo esc_html($process_source); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="wpjm-openai-detail-item">
                <span class="wpjm-openai-detail-label"><?php _e('Lien de candidature:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-detail-value">
                    <?php if (is_email($job_application)) : ?>
                        <a href="mailto:<?php echo esc_attr($job_application); ?>"><?php echo esc_html($job_application); ?></a>
                    <?php elseif (filter_var($job_application, FILTER_VALIDATE_URL)) : ?>
                        <a href="<?php echo esc_url($job_application); ?>" target="_blank"><?php echo esc_html($job_application); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($job_application); ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="wpjm-openai-detail-item">
                <span class="wpjm-openai-detail-label"><?php _e('Date d\'expiration:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-detail-value">
                    <?php 
                    if (!empty($job_expires)) {
                        echo esc_html(date_i18n(get_option('date_format'), strtotime($job_expires)));
                    } else {
                        echo '—';
                    }
                    ?>
                </span>
            </div>
        <?php elseif ($has_error) : ?>
            <div class="wpjm-openai-status error">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Erreur lors du traitement', 'wpjm-openai'); ?>
            </div>
            
            <div class="wpjm-openai-error">
                <strong><?php _e('Message d\'erreur:', 'wpjm-openai'); ?></strong>
                <p><?php echo esc_html($has_error); ?></p>
            </div>
        <?php else : ?>
            <div class="wpjm-openai-status pending">
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Non traité par IA', 'wpjm-openai'); ?>
            </div>
        <?php endif; ?>
        
        <div class="wpjm-openai-actions">
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=process_job_with_ai&job_id=' . $job_id . '&_wpnonce=' . wp_create_nonce('process_job_with_ai_' . $job_id))); ?>" class="button ai-process-button">
                <?php echo $is_processed ? __('Retraiter avec IA', 'wpjm-openai') : __('Traiter avec IA', 'wpjm-openai'); ?>
            </a>
        </div>
    </div>
    
    <div class="wpjm-openai-tab-content" id="tab-location">
        <div class="wpjm-openai-detail-item">
            <span class="wpjm-openai-detail-label"><?php _e('Lieu original:', 'wpjm-openai'); ?></span>
            <span class="wpjm-openai-detail-value"><?php echo esc_html($job_location); ?></span>
        </div>
        
        <div class="wpjm-openai-detail-item">
            <span class="wpjm-openai-detail-label"><?php _e('Pays (détecté par IA):', 'wpjm-openai'); ?></span>
            <input type="text" name="_job_country" value="<?php echo esc_attr($job_country); ?>" class="regular-text" />
        </div>
        
        <div class="wpjm-openai-detail-item">
            <span class="wpjm-openai-detail-label"><?php _e('Région (détectée par IA):', 'wpjm-openai'); ?></span>
            <input type="text" name="_job_region" value="<?php echo esc_attr($job_region); ?>" class="regular-text" />
        </div>
        
        <div class="wpjm-openai-detail-item">
            <label>
                <input type="checkbox" name="_job_is_global" <?php checked($job_is_global, true); ?> />
                <?php _e('Poste Global (couvre plusieurs pays sur plusieurs continents)', 'wpjm-openai'); ?>
            </label>
        </div>
        
        <div class="wpjm-openai-detail-item">
            <label>
                <input type="checkbox" name="_remote_position" <?php checked($job_is_remote, true); ?> />
                <?php _e('Poste 100% Remote (télétravail complet)', 'wpjm-openai'); ?>
            </label>
        </div>
        
        <div class="wpjm-openai-detail-item">
            <label>
                <input type="checkbox" name="_job_is_hybrid" <?php checked($job_is_hybrid, true); ?> />
                <?php _e('Poste Hybride (télétravail avec missions terrain)', 'wpjm-openai'); ?>
            </label>
        </div>
    </div>
    
    <div class="wpjm-openai-tab-content" id="tab-categories">
        <?php
        // Récupérer toutes les catégories
        $all_categories = get_terms(array(
            'taxonomy' => 'job_listing_category',
            'hide_empty' => false,
        ));
        
        // Regrouper les catégories par parent pour afficher les catégories principales d'abord
        $main_categories = array();
        $sub_categories = array();
        
        foreach ($all_categories as $cat) {
            if ($cat->parent === 0) {
                $main_categories[] = $cat;
            } else {
                $sub_categories[] = $cat;
            }
        }
        
        // Liste des catégories assignées (IDs)
        $assigned_category_ids = array();
        foreach ($categories as $cat) {
            $assigned_category_ids[] = $cat->term_id;
        }
        ?>
        
        <div class="wpjm-openai-detail-item">
            <span class="wpjm-openai-detail-label"><?php _e('Explication IA des catégories:', 'wpjm-openai'); ?></span>
            <span class="wpjm-openai-detail-value">
                <?php echo !empty($category_explanation) ? esc_html($category_explanation) : __('Aucune explication disponible.', 'wpjm-openai'); ?>
            </span>
        </div>
        
        <div class="wpjm-openai-detail-item">
            <span class="wpjm-openai-detail-label"><?php _e('Catégories assignées:', 'wpjm-openai'); ?></span>
            
            <?php if (!empty($main_categories)) : ?>
                <div class="wpjm-openai-categories-selector">
                    <?php foreach ($main_categories as $main_cat) : ?>
                        <div class="wpjm-openai-category-main">
                            <label>
                                <input type="checkbox" name="tax_input[job_listing_category][]" value="<?php echo esc_attr($main_cat->term_id); ?>" <?php checked(in_array($main_cat->term_id, $assigned_category_ids), true); ?> class="wpjm-openai-checkbox" />
                                <strong><?php echo esc_html($main_cat->name); ?></strong>
                            </label>
                            
                            <?php
                            // Afficher les sous-catégories de cette catégorie principale
                            $children = array_filter($sub_categories, function($sub) use ($main_cat) {
                                return $sub->parent === $main_cat->term_id;
                            });
                            
                            if (!empty($children)) :
                            ?>
                                <div class="wpjm-openai-subcategories" style="margin-left: 20px; margin-top: 5px;">
                                    <?php foreach ($children as $child) : ?>
                                        <div>
                                            <label>
                                                <input type="checkbox" name="tax_input[job_listing_category][]" value="<?php echo esc_attr($child->term_id); ?>" <?php checked(in_array($child->term_id, $assigned_category_ids), true); ?> class="wpjm-openai-checkbox" />
                                                <?php echo esc_html($child->name); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php _e('Aucune catégorie disponible.', 'wpjm-openai'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="wpjm-openai-tab-content" id="tab-consultancy">
        <?php if (!empty($consultancy_types)) : ?>
            <div class="wpjm-openai-detail-item">
                <span class="wpjm-openai-detail-label"><?php _e('Types de consultance détectés:', 'wpjm-openai'); ?></span>
                
                <div class="wpjm-openai-consultancy-grid">
                    <?php foreach ($consultancy_types as $key => $label) : ?>
                        <div class="wpjm-openai-consultancy-item">
                            <label>
                                <input type="checkbox" name="_job_consultancy_<?php echo esc_attr($key); ?>" <?php checked($active_consultancy_types[$key], true); ?> class="wpjm-openai-checkbox" />
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <p><?php _e('Aucun type de consultance défini.', 'wpjm-openai'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="wpjm-openai-tab-content" id="tab-logs">
        <?php if (!empty($job_logs)) : ?>
            <div class="wpjm-openai-logs-list">
                <?php foreach ($job_logs as $log) : ?>
                    <div class="log-item">
                        <span class="log-date">
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->log_date))); ?>
                            - 
                            <span class="wpjm-openai-status-bubble status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html($log->status); ?>
                            </span>
                        </span>
                        <span class="log-message">
                            <strong><?php echo esc_html(ucfirst($log->action)); ?>:</strong> 
                            <?php echo esc_html($log->details); ?>
                            <?php if (!empty($log->tokens)) : ?>
                                (<?php printf(__('%s tokens utilisés', 'wpjm-openai'), number_format($log->tokens)); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php _e('Aucun log disponible pour cette offre.', 'wpjm-openai'); ?></p>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestion des onglets
    $('.wpjm-openai-metabox-tabs .tab-item').click(function() {
        var targetTab = $(this).data('tab');
        
        // Activer l'onglet
        $('.wpjm-openai-metabox-tabs .tab-item').removeClass('active');
        $(this).addClass('active');
        
        // Afficher le contenu correspondant
        $('.wpjm-openai-tab-content').removeClass('active');
        $('#tab-' + targetTab).addClass('active');
    });
});
</script>