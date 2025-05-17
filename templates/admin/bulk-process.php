<?php
/**
 * Template pour le traitement par lot des offres d'emploi avec OpenAI
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

// Gérer la soumission du formulaire
$message = '';
$message_type = '';

if (isset($_POST['bulk_process_jobs']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'bulk_process_jobs')) {
    // Nombre d'offres à traiter
    $limit = isset($_POST['job_limit']) ? intval($_POST['job_limit']) : 10;
    $limit = max(1, min(50, $limit)); // Limiter entre 1 et 50
    
    // Statut des offres
    $job_status = isset($_POST['job_status']) ? sanitize_text_field($_POST['job_status']) : 'all';
    
    // Type des offres
    $process_type = isset($_POST['process_type']) ? sanitize_text_field($_POST['process_type']) : 'not_processed';
    
    // Récupérer les IDs des offres à traiter
    global $wpdb;
    
    $query = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'job_listing'
    ";
    
    // Filtrer par statut
    if ($job_status !== 'all') {
        $query .= " AND p.post_status = '" . esc_sql($job_status) . "'";
    } else {
        $query .= " AND p.post_status IN ('publish', 'draft', 'pending')";
    }
    
    // Filtrer par type de traitement
    if ($process_type === 'not_processed') {
        $query .= "
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta}
                WHERE post_id = p.ID
                AND meta_key = '_ai_processed'
                AND meta_value = '1'
            )
        ";
    } elseif ($process_type === 'with_errors') {
        $query .= "
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta}
                WHERE post_id = p.ID
                AND meta_key = '_ai_processing_error'
                AND meta_value != ''
            )
        ";
    } elseif ($process_type === 'all') {
        // Aucun filtre supplémentaire
    } elseif ($process_type === 'already_processed') {
        $query .= "
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta}
                WHERE post_id = p.ID
                AND meta_key = '_ai_processed'
                AND meta_value = '1'
            )
        ";
    }
    
    // Limiter le nombre de résultats
    $query .= " ORDER BY p.post_date DESC LIMIT " . $limit;
    
    $job_ids = $wpdb->get_col($query);
    
    if (empty($job_ids)) {
        $message = __('Aucune offre d\'emploi à traiter selon les critères sélectionnés.', 'wpjm-openai');
        $message_type = 'warning';
    } else {
        // Traiter les offres
        $processor = WPJM_OpenAI_Processor::get_instance();
        $success_count = 0;
        $error_count = 0;
        $errors = array();
        
        foreach ($job_ids as $job_id) {
            try {
                // Marquer comme étant en cours de traitement
                update_post_meta($job_id, '_ai_processing_in_progress', '1');
                
                // Traiter l'offre
                $processor->process_job($job_id);
                
                // Mettre à jour les métadonnées
                update_post_meta($job_id, '_ai_processed', '1');
                update_post_meta($job_id, '_ai_processed_date', current_time('mysql'));
                delete_post_meta($job_id, '_ai_processing_error');
                
                // Journaliser
                $logs = WPJM_OpenAI_Logs::get_instance();
                $logs->add_log($job_id, 'bulk_process', 'success', __('Traitement par lot réussi', 'wpjm-openai'), 0);
                
                $success_count++;
            } catch (Exception $e) {
                update_post_meta($job_id, '_ai_processing_error', $e->getMessage());
                
                // Journaliser
                $logs = WPJM_OpenAI_Logs::get_instance();
                $logs->add_log($job_id, 'bulk_process', 'error', $e->getMessage(), 0);
                
                $error_count++;
                $errors[] = array(
                    'id' => $job_id,
                    'title' => get_the_title($job_id),
                    'error' => $e->getMessage()
                );
            } finally {
                // Supprimer le marqueur de traitement en cours
                delete_post_meta($job_id, '_ai_processing_in_progress');
            }
        }
        
        if ($success_count > 0) {
            $message = sprintf(
                __('%d offre(s) traitée(s) avec succès.', 'wpjm-openai'),
                $success_count
            );
            
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    __('%d offre(s) avec erreur(s).', 'wpjm-openai'),
                    $error_count
                );
            }
            
            $message_type = ($error_count > 0) ? 'warning' : 'success';
        } else {
            $message = __('Aucune offre n\'a été traitée avec succès.', 'wpjm-openai');
            
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    __('%d offre(s) ont rencontré des erreurs.', 'wpjm-openai'),
                    $error_count
                );
            }
            
            $message_type = 'error';
        }
    }
}

// Récupérer les statistiques des offres
global $wpdb;

$stats = array(
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status IN ('publish', 'draft', 'pending')"),
    'processed' => $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'job_listing'
        AND p.post_status IN ('publish', 'draft', 'pending')
        AND pm.meta_key = '_ai_processed'
        AND pm.meta_value = '1'
    "),
    'with_errors' => $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'job_listing'
        AND p.post_status IN ('publish', 'draft', 'pending')
        AND pm.meta_key = '_ai_processing_error'
        AND pm.meta_value != ''
    ")
);

$stats['not_processed'] = $stats['total'] - $stats['processed'];
?>

<div class="wrap wpjm-openai-bulk-process">
    <h1><?php _e('Traitement par lot des offres d\'emploi', 'wpjm-openai'); ?></h1>
    
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        
        <?php if (!empty($errors)) : ?>
            <div class="wpjm-openai-error-details">
                <h3><?php _e('Détail des erreurs', 'wpjm-openai'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'wpjm-openai'); ?></th>
                            <th><?php _e('Titre', 'wpjm-openai'); ?></th>
                            <th><?php _e('Erreur', 'wpjm-openai'); ?></th>
                            <th><?php _e('Actions', 'wpjm-openai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error) : ?>
                            <tr>
                                <td><?php echo esc_html($error['id']); ?></td>
                                <td><?php echo esc_html($error['title']); ?></td>
                                <td><?php echo esc_html($error['error']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($error['id'])); ?>" class="button button-small">
                                        <?php _e('Éditer', 'wpjm-openai'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="wpjm-openai-stats-cards">
        <div class="wpjm-openai-card">
            <h2><?php _e('Statistiques des offres', 'wpjm-openai'); ?></h2>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Total des offres:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html($stats['total']); ?></span>
            </div>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Offres traitées:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html($stats['processed']); ?></span>
            </div>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Offres non traitées:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html($stats['not_processed']); ?></span>
            </div>
            <div class="wpjm-openai-stat-item">
                <span class="wpjm-openai-stat-label"><?php _e('Offres avec erreurs:', 'wpjm-openai'); ?></span>
                <span class="wpjm-openai-stat-value"><?php echo esc_html($stats['with_errors']); ?></span>
            </div>
        </div>
    </div>
    
    <div class="wpjm-openai-card">
        <h2><?php _e('Traiter des offres en lot', 'wpjm-openai'); ?></h2>
        
        <form method="post" action="" class="wpjm-openai-bulk-form">
            <?php wp_nonce_field('bulk_process_jobs'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="process_type"><?php _e('Offres à traiter', 'wpjm-openai'); ?></label>
                    </th>
                    <td>
                        <select name="process_type" id="process_type">
                            <option value="not_processed"><?php _e('Non traitées uniquement', 'wpjm-openai'); ?></option>
                            <option value="with_errors"><?php _e('Avec erreurs uniquement', 'wpjm-openai'); ?></option>
                            <option value="already_processed"><?php _e('Déjà traitées (retraitement)', 'wpjm-openai'); ?></option>
                            <option value="all"><?php _e('Toutes les offres', 'wpjm-openai'); ?></option>
                        </select>
                        <p class="description"><?php _e('Sélectionnez les offres que vous souhaitez traiter.', 'wpjm-openai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="job_status"><?php _e('Statut des offres', 'wpjm-openai'); ?></label>
                    </th>
                    <td>
                        <select name="job_status" id="job_status">
                            <option value="all"><?php _e('Tous les statuts', 'wpjm-openai'); ?></option>
                            <option value="publish"><?php _e('Publiées', 'wpjm-openai'); ?></option>
                            <option value="draft"><?php _e('Brouillons', 'wpjm-openai'); ?></option>
                            <option value="pending"><?php _e('En attente', 'wpjm-openai'); ?></option>
                        </select>
                        <p class="description"><?php _e('Filtrer par statut de publication.', 'wpjm-openai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="job_limit"><?php _e('Nombre d\'offres à traiter', 'wpjm-openai'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="job_limit" id="job_limit" value="10" min="1" max="50" step="1" class="small-text" />
                        <p class="description"><?php _e('Nombre maximum d\'offres à traiter en une fois (1-50).', 'wpjm-openai'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div class="wpjm-openai-warning">
                <p>
                    <strong><?php _e('Attention:', 'wpjm-openai'); ?></strong>
                    <?php _e('Le traitement par lot peut prendre du temps et consomme des crédits OpenAI. Assurez-vous que votre clé API dispose de crédits suffisants.', 'wpjm-openai'); ?>
                </p>
            </div>
            
            <p class="submit">
                <input type="submit" name="bulk_process_jobs" id="bulk_process_jobs" class="button button-primary" value="<?php esc_attr_e('Lancer le traitement', 'wpjm-openai'); ?>" />
            </p>
        </form>
    </div>
</div>