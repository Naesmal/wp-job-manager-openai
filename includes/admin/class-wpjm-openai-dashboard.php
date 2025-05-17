<?php
/**
 * Intégration des templates de dashboard et de gestion pour WP Job Manager OpenAI
 *
 * @package wp-job-manager-openai
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Dashboard {
    /**
     * Instance unique de la classe
     */
    private static $instance = null;

    /**
     * Obtenir l'instance unique
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur
     */
    private function __construct() {
        // Ajouter les pages de menu
        add_action('admin_menu', array($this, 'add_admin_menu_pages'));
        
        // Ajouter la metabox détaillée
        add_action('add_meta_boxes', array($this, 'add_detailed_metabox'));
        
        // Enregistrer les données de la metabox détaillée
        add_action('save_post_job_listing', array($this, 'save_detailed_metabox_data'), 10, 2);
        
        // Ajouter les styles d'administration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_styles'));
        
        // Traiter l'exportation CSV
        add_action('admin_post_wpjm_openai_export_csv', array($this, 'export_jobs_to_csv'));
        
        // Ajouter une action pour le traitement par lot AJAX
        add_action('wp_ajax_wpjm_openai_bulk_process', array($this, 'handle_ajax_bulk_process'));
    }

    /**
     * Ajouter les pages de menu d'administration
     */
    public function add_admin_menu_pages() {
        // Page de tableau de bord
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Tableau de bord OpenAI', 'wpjm-openai'),
            __('Dashboard OpenAI', 'wpjm-openai'),
            'manage_options',
            'wpjm-openai-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        // Page des offres traitées
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Offres traitées par IA', 'wpjm-openai'),
            __('Offres traitées IA', 'wpjm-openai'),
            'manage_options',
            'wpjm-openai-processed',
            array($this, 'render_processed_jobs_page')
        );
        
        // Page de traitement par lot
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Traitement par lot', 'wpjm-openai'),
            __('Traitement par lot', 'wpjm-openai'),
            'manage_options',
            'wpjm-openai-process-bulk',
            array($this, 'render_bulk_process_page')
        );
        
        // Page des logs
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Logs OpenAI', 'wpjm-openai'),
            __('Logs OpenAI', 'wpjm-openai'),
            'manage_options',
            'wpjm-openai-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Ajouter la metabox détaillée
     */
    public function add_detailed_metabox() {
        add_meta_box(
            'wpjm_openai_detailed',
            __('Analyse IA détaillée', 'wpjm-openai'),
            array($this, 'render_detailed_metabox'),
            'job_listing',
            'normal',
            'high'
        );
    }
    
    /**
     * Enregistrer les données de la metabox détaillée
     */
    public function save_detailed_metabox_data($post_id, $post) {
        // Vérifier le nonce
        if (!isset($_POST['wpjm_openai_metabox_nonce']) || !wp_verify_nonce($_POST['wpjm_openai_metabox_nonce'], 'wpjm_openai_save_metabox')) {
            return;
        }
        
        // Vérifier si c'est une sauvegarde automatique
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Mettre à jour les champs
        
        // Champs de localisation
        if (isset($_POST['_job_country'])) {
            update_post_meta($post_id, '_job_country', sanitize_text_field($_POST['_job_country']));
        }
        
        if (isset($_POST['_job_region'])) {
            update_post_meta($post_id, '_job_region', sanitize_text_field($_POST['_job_region']));
        }
        
        // Cases à cocher
        update_post_meta($post_id, '_job_is_global', isset($_POST['_job_is_global']) ? '1' : '');
        update_post_meta($post_id, '_remote_position', isset($_POST['_remote_position']) ? '1' : '');
        update_post_meta($post_id, '_job_is_hybrid', isset($_POST['_job_is_hybrid']) ? '1' : '');
        
        // Types de consultance
        $consultancy_types = get_option('wpjm_openai_consultancy_types', array());
        foreach ($consultancy_types as $key => $label) {
            $meta_key = '_job_consultancy_' . $key;
            update_post_meta($post_id, $meta_key, isset($_POST[$meta_key]) ? '1' : '');
        }
    }
    
    /**
     * Rendre la metabox détaillée
     */
    public function render_detailed_metabox($post) {
        // Ajouter un nonce pour la sécurité
        wp_nonce_field('wpjm_openai_save_metabox', 'wpjm_openai_metabox_nonce');
        
        // Inclure le template
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/admin/job-ai-detailed-metabox.php';
    }
    
    /**
     * Enregistrer les styles d'administration
     */
    public function enqueue_dashboard_styles($hook) {
        // Pages du plugin pour charger les styles
        $plugin_pages = array(
            'job_listing_page_wpjm-openai-dashboard',
            'job_listing_page_wpjm-openai-processed',
            'job_listing_page_wpjm-openai-process-bulk',
            'job_listing_page_wpjm-openai-logs',
            'post.php',
            'post-new.php'
        );
        
        // Vérifier si nous sommes sur une page du plugin
        if (!in_array($hook, $plugin_pages)) {
            // Pour post.php et post-new.php, vérifier le type de post
            if (($hook === 'post.php' || $hook === 'post-new.php') && isset($_GET['post_type']) && $_GET['post_type'] !== 'job_listing') {
                if ($hook === 'post.php' && isset($_GET['post'])) {
                    $post_type = get_post_type($_GET['post']);
                    if ($post_type !== 'job_listing') {
                        return;
                    }
                } else {
                    return;
                }
            } elseif ($hook === 'post.php' && isset($_GET['post'])) {
                $post_type = get_post_type($_GET['post']);
                if ($post_type !== 'job_listing') {
                    return;
                }
            } else {
                return;
            }
        }
        
        // Charger les styles
        wp_enqueue_style('wpjm-openai-dashboard', WPJM_OPENAI_PLUGIN_URL . 'assets/css/admin-dashboard.css', array(), WPJM_OPENAI_VERSION);
        
        // Charger Chart.js pour les graphiques
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js', array(), '3.7.1', true);
        
        // jQuery UI pour les dialogues
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Script personnalisé pour le dashboard
        wp_enqueue_script('wpjm-openai-dashboard-js', WPJM_OPENAI_PLUGIN_URL . 'assets/js/admin-dashboard.js', array('jquery', 'chart-js'), WPJM_OPENAI_VERSION, true);
        
        // Ajouter le nonce pour la sécurité AJAX
        wp_localize_script('wpjm-openai-dashboard-js', 'wpjm_openai_dashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpjm_openai_dashboard_nonce'),
            'processing_text' => __('Traitement en cours...', 'wpjm-openai'),
            'success_text' => __('Succès', 'wpjm-openai'),
            'error_text' => __('Erreur', 'wpjm-openai')
        ));
    }
    
    /**
     * Rendre la page de tableau de bord
     */
    public function render_dashboard_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
        }
        
        // Inclure le template
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/admin/admin-dashboard.php';
    }
    
    /**
     * Rendre la page des offres traitées
     */
    public function render_processed_jobs_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
        }
        
        // Inclure le template
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/admin/processed-jobs-list.php';
        
        // Appeler la fonction qui affiche le contenu
        wpjm_openai_render_processed_jobs_page();
    }
    
    /**
     * Rendre la page de traitement par lot
     */
    public function render_bulk_process_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
        }
        
        // Inclure le template
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/admin/bulk-process.php';
    }
    
    /**
     * Rendre la page des logs
     */
    public function render_logs_page() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
        }
        
        // Inclure le template
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/admin/logs-list.php';
        
        // Appeler la fonction qui affiche le contenu
        wpjm_openai_render_logs_page();
    }
    
    /**
     * Exporter les offres en CSV
     */
    public function export_jobs_to_csv() {
        // Vérifier le nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpjm_openai_export_csv')) {
            wp_die(__('Action non autorisée.', 'wpjm-openai'));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour effectuer cette action.', 'wpjm-openai'));
        }
        
        // Paramètres d'export
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
        
        // Récupérer les IDs des offres à exporter
        global $wpdb;
        
        $query = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'job_listing'
            AND p.post_status IN ('publish', 'draft', 'pending')
        ";
        
        // Filtrer par statut IA
        if ($status === 'processed') {
            $query .= "
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta}
                    WHERE post_id = p.ID
                    AND meta_key = '_ai_processed'
                    AND meta_value = '1'
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta}
                    WHERE post_id = p.ID
                    AND meta_key = '_ai_processing_error'
                    AND meta_value != ''
                )
            ";
        } elseif ($status === 'error') {
            $query .= "
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta}
                    WHERE post_id = p.ID
                    AND meta_key = '_ai_processing_error'
                    AND meta_value != ''
                )
            ";
        } elseif ($status === 'not_processed') {
            $query .= "
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta}
                    WHERE post_id = p.ID
                    AND meta_key = '_ai_processed'
                    AND meta_value = '1'
                )
            ";
        }
        
        // Filtrer par catégorie
        if (!empty($category)) {
            $query .= $wpdb->prepare("
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'job_listing_category'
                    AND tt.term_id = %d
                )
            ", $category);
        }
        
        $job_ids = $wpdb->get_col($query);
        
        if (empty($job_ids)) {
            wp_die(__('Aucune offre d\'emploi à exporter.', 'wpjm-openai'));
        }
        
        // Préparer les données CSV
        $csv_data = array();
        
        // En-têtes
        $headers = array(
            'ID',
            __('Titre', 'wpjm-openai'),
            __('Statut', 'wpjm-openai'),
            __('Lieu', 'wpjm-openai'),
            __('Pays', 'wpjm-openai'),
            __('Région', 'wpjm-openai'),
            __('Global', 'wpjm-openai'),
            __('Remote', 'wpjm-openai'),
            __('Hybride', 'wpjm-openai'),
            __('Catégories', 'wpjm-openai'),
            __('Types de consultance', 'wpjm-openai'),
            __('Date de traitement', 'wpjm-openai'),
            __('Statut IA', 'wpjm-openai'),
            __('Erreur IA', 'wpjm-openai'),
            __('URL', 'wpjm-openai')
        );
        
        $csv_data[] = $headers;
        
        // Consultancy types
        $consultancy_types = get_option('wpjm_openai_consultancy_types', array());
        
        // Ajouter les données de chaque offre
        foreach ($job_ids as $job_id) {
            $job = get_post($job_id);
            
            if (!$job) {
                continue;
            }
            
            // Récupérer les métadonnées
            $location = get_post_meta($job_id, '_job_location', true);
            $country = get_post_meta($job_id, '_job_country', true);
            $region = get_post_meta($job_id, '_job_region', true);
            $is_global = get_post_meta($job_id, '_job_is_global', true) ? __('Oui', 'wpjm-openai') : __('Non', 'wpjm-openai');
            $is_remote = get_post_meta($job_id, '_remote_position', true) ? __('Oui', 'wpjm-openai') : __('Non', 'wpjm-openai');
            $is_hybrid = get_post_meta($job_id, '_job_is_hybrid', true) ? __('Oui', 'wpjm-openai') : __('Non', 'wpjm-openai');
            $processed_date = get_post_meta($job_id, '_ai_processed_date', true);
            $is_processed = get_post_meta($job_id, '_ai_processed', true) ? __('Traité', 'wpjm-openai') : __('Non traité', 'wpjm-openai');
            $error = get_post_meta($job_id, '_ai_processing_error', true);
            
            // Récupérer les catégories
            $categories = get_the_terms($job_id, 'job_listing_category');
            $category_names = array();
            
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }
            }
            
            // Récupérer les types de consultance
            $active_types = array();
            foreach ($consultancy_types as $key => $label) {
                if (get_post_meta($job_id, '_job_consultancy_' . $key, true)) {
                    $active_types[] = $label;
                }
            }
            
            // Formater la date
            $formatted_date = !empty($processed_date) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($processed_date)) : '';
            
            // Données de la ligne
            $row = array(
                $job_id,
                $job->post_title,
                get_post_status_object($job->post_status)->label,
                $location,
                $country,
                $region,
                $is_global,
                $is_remote,
                $is_hybrid,
                implode(', ', $category_names),
                implode(', ', $active_types),
                $formatted_date,
                $is_processed,
                $error,
                get_permalink($job_id)
            );
            
            $csv_data[] = $row;
        }
        
        // Générer le fichier CSV
        $filename = 'wpjm-openai-jobs-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Gérer le traitement par lot AJAX
     */
    public function handle_ajax_bulk_process() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpjm_openai_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'wpjm-openai')));
            exit;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions suffisantes.', 'wpjm-openai')));
            exit;
        }
        
        // Récupérer l'ID de l'offre à traiter
        if (!isset($_POST['job_id']) || !intval($_POST['job_id'])) {
            wp_send_json_error(array('message' => __('ID d\'offre invalide.', 'wpjm-openai')));
            exit;
        }
        
        $job_id = intval($_POST['job_id']);
        
        // Vérifier que l'offre existe et est du bon type
        if (!get_post($job_id) || get_post_type($job_id) !== 'job_listing') {
            wp_send_json_error(array('message' => __('Offre d\'emploi invalide.', 'wpjm-openai')));
            exit;
        }
        
        // Traiter l'offre
        try {
            // Récupérer le processeur OpenAI
            $processor = WPJM_OpenAI_Processor::get_instance();
            
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
            $logs->add_log($job_id, 'ajax_process', 'success', __('Traitement AJAX réussi', 'wpjm-openai'), 0);
            
            // Supprimer le marqueur de traitement en cours
            delete_post_meta($job_id, '_ai_processing_in_progress');
            
            // Envoyer la réponse de succès
            wp_send_json_success(array(
                'message' => __('Offre traitée avec succès.', 'wpjm-openai'),
                'job_id' => $job_id,
                'job_title' => get_the_title($job_id)
            ));
        } catch (Exception $e) {
            // Journaliser l'erreur
            update_post_meta($job_id, '_ai_processing_error', $e->getMessage());
            
            // Journaliser
            $logs = WPJM_OpenAI_Logs::get_instance();
            $logs->add_log($job_id, 'ajax_process', 'error', $e->getMessage(), 0);
            
            // Supprimer le marqueur de traitement en cours
            delete_post_meta($job_id, '_ai_processing_in_progress');
            
            // Envoyer la réponse d'erreur
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'job_id' => $job_id,
                'job_title' => get_the_title($job_id)
            ));
        }
        
        exit;
    }
}

// Initialiser le dashboard
function wpjm_openai_init_dashboard() {
    return WPJM_OpenAI_Dashboard::get_instance();
}

// Démarrer le dashboard
add_action('plugins_loaded', 'wpjm_openai_init_dashboard');