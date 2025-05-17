<?php
/**
 * Plugin Name: WP Job Manager OpenAI Extension
 * Plugin URI: https://thegoodfeat.com
 * Description: Automatise la catégorisation et l'enrichissement des offres d'emploi avec OpenAI.
 * Version: 1.0.0
 * Author: The Good Feat
 * License: GPL-2.0+
 * Text Domain: wpjm-openai
 * Domain Path: /languages
 */

// Si le fichier est accédé directement, sortir
if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI {
    /**
     * Instance unique de la classe
     */
    private static $instance = null;

    /**
     * Clé API OpenAI
     */
    private $api_key = '';

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
        // Définir les constantes du plugin
        $this->define_constants();

        // Charger les dépendances
        $this->includes();

        // Initialiser les hooks
        $this->init_hooks();
        
        // Initialiser les templates
        $this->init_templates();

        // Charger la clé API depuis les paramètres
        $this->api_key = get_option('wpjm_openai_api_key', '');

        // Ajouter un hook direct pour GoFetchJobs dans le init_hooks
        add_action('plugins_loaded', function() {
            if (class_exists('GoFetchJobs')) {
                error_log('GoFetchJobs détecté - Configuration des hooks pour WP Job Manager OpenAI');
                add_action('gofetchjobs_after_import_job', function($job_id) {
                    error_log('Hook gofetchjobs_after_import_job déclenché pour le job #' . $job_id);
                    
                    // Vérifier si la clé API est définie
                    if (empty(get_option('wpjm_openai_api_key', ''))) {
                        error_log('Clé API non définie - Traitement annulé pour le job #' . $job_id);
                        return;
                    }
                    
                    // S'assurer que le traitement automatique est activé
                    if ('yes' !== get_option('wpjm_openai_auto_process', 'yes')) {
                        error_log('Traitement automatique désactivé - Offre #' . $job_id . ' non traitée');
                        return;
                    }
                    
                    try {
                        // Traiter l'offre d'emploi
                        $processor = WPJM_OpenAI_Processor::get_instance();
                        $processor->process_job($job_id);
                        
                        // Marquer comme traité
                        update_post_meta($job_id, '_ai_processed', '1');
                        update_post_meta($job_id, '_ai_processed_date', current_time('mysql'));
                        
                        error_log('Traitement IA réussi pour le job #' . $job_id);
                    } catch (Exception $e) {
                        error_log('Erreur lors du traitement IA pour le job #' . $job_id . ': ' . $e->getMessage());
                        update_post_meta($job_id, '_ai_processing_error', $e->getMessage());
                    }
                }, 10, 1);
            }
        });
    }

    /**
     * Définir les constantes du plugin
     */
    private function define_constants() {
        define('WPJM_OPENAI_VERSION', '1.0.0');
        define('WPJM_OPENAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WPJM_OPENAI_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Inclure les fichiers nécessaires
     */
    private function includes() {
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/admin/class-wpjm-openai-admin.php';
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/admin/class-wpjm-openai-ajax.php';
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-processor.php';
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-installer.php';
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-integrations.php';
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-logs.php';
    }

    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Vérifier si WP Job Manager est activé
        add_action('admin_init', array($this, 'check_wp_job_manager'));

        // Hooks d'activation et de désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Ajouter les hooks pour traiter les offres d'emploi
        if ($this->is_api_key_set()) {
            // Hooks pour le traitement automatique des offres d'emploi
            add_action('job_manager_update_job_data', array($this, 'process_job_on_save'), 20, 2);
            add_action('save_post_job_listing', array($this, 'process_job_on_save_post'), 20, 3);
            
            // Hook pour ajouter un bouton de traitement manuel
            add_action('job_manager_job_dashboard_columns', array($this, 'add_ai_process_button'), 25);
            add_action('admin_post_process_job_with_ai', array($this, 'handle_manual_job_processing'));
            
            // Hook pour traiter les offres importées via GoFetchJobs (si vous utilisez ce plugin)
            add_action('gofetchjobs_after_import_job', array($this, 'process_imported_job'), 10, 1);
        }

        // Ajouter menu d'administration
        if (is_admin()) {
            WPJM_OpenAI_Admin::get_instance();
        }
    }
    
    /**
     * Initialiser les templates
     */
    private function init_templates() {
        // Ajouter le badge IA aux offres d'emploi
        add_action('single_job_listing_meta_start', array($this, 'display_ai_processed_badge'));
        
        // Ajouter le bouton IA au tableau de bord
        add_filter('job_manager_job_dashboard_columns', array($this, 'add_ai_process_button'), 25);
        add_action('job_manager_job_dashboard_column_ai_process', array($this, 'render_ai_process_button'), 10, 2);
    }

    /**
     * Vérifier si la clé API est définie
     */
    private function is_api_key_set() {
        return !empty($this->api_key);
    }

    /**
     * Vérifier si WP Job Manager est activé
     */
    public function check_wp_job_manager() {
        if (!class_exists('WP_Job_Manager')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    /**
     * Afficher une notification si WP Job Manager est manquant
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WP Job Manager OpenAI Extension nécessite WP Job Manager pour fonctionner.', 'wpjm-openai'); ?></p>
        </div>
        <?php
    }

    /**
     * Actions d'activation du plugin
     */
    public function activate() {
        // Installer les données initiales
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-installer.php';
        wpjm_openai_install();
        
        // Créer la table de logs
        require_once WPJM_OPENAI_PLUGIN_DIR . 'includes/class-wpjm-openai-logs.php';
        WPJM_OpenAI_Logs::get_instance()->create_table();
    }

    /**
     * Actions de désactivation du plugin
     */
    public function deactivate() {
        // Rien pour l'instant
    }

    /**
     * Traitement d'une offre d'emploi lors de la sauvegarde
     */
    public function process_job_on_save($job_id, $values) {
        // Journaliser le début du traitement
        wpjm_openai_debug_log("Début du traitement de l'offre #$job_id");
        
        // Vérifier si la clé API est définie
        if (empty($this->api_key)) {
            wpjm_openai_debug_log("Clé API non définie - Traitement annulé");
            return;
        }
        
        // S'assurer que le traitement automatique est activé
        if ('yes' !== get_option('wpjm_openai_auto_process', 'yes')) {
            wpjm_openai_debug_log("Traitement automatique désactivé");
            return;
        }
        
        // S'assurer que le traitement n'est pas déjà en cours pour éviter les doublons
        if (get_post_meta($job_id, '_ai_processing_in_progress', true)) {
            wpjm_openai_debug_log("Traitement déjà en cours pour l'offre #$job_id");
            return;
        }
    
        // Marquer l'offre comme étant en cours de traitement
        update_post_meta($job_id, '_ai_processing_in_progress', '1');
    
        try {
            // Récupérer le processeur OpenAI
            $processor = WPJM_OpenAI_Processor::get_instance();
            
            // Traiter l'offre
            wpjm_openai_debug_log("Lancement du traitement pour l'offre #$job_id");
            $processor->process_job($job_id);
            
            // Journaliser le traitement réussi
            update_post_meta($job_id, '_ai_processed', '1');
            update_post_meta($job_id, '_ai_processed_date', current_time('mysql'));
            wpjm_openai_debug_log("Traitement terminé avec succès pour l'offre #$job_id");
        } catch (Exception $e) {
            // Journaliser l'erreur
            update_post_meta($job_id, '_ai_processing_error', $e->getMessage());
            wpjm_openai_debug_log("Erreur lors du traitement de l'offre #$job_id: " . $e->getMessage());
        }
    
        // Supprimer le marqueur de traitement en cours
        delete_post_meta($job_id, '_ai_processing_in_progress');
    }

    /**
     * Traitement d'une offre d'emploi lors de la sauvegarde via save_post
     */
    public function process_job_on_save_post($post_id, $post, $update) {
        // Ignorer les auto-saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Vérifier le type de poste
        if ('job_listing' !== $post->post_type) {
            return;
        }

        // Appeler la fonction de traitement
        $this->process_job_on_save($post_id, array());
    }

    /**
     * Traiter une offre importée via GoFetchJobs
     */
    public function process_imported_job($job_id) {
        $this->process_job_on_save($job_id, array());
    }

    /**
     * Ajouter un bouton pour traiter manuellement une offre avec l'IA
     */
    public function add_ai_process_button($columns) {
        // Ajouter une colonne pour le bouton de traitement IA
        $columns['ai_process'] = __('IA', 'wpjm-openai');
        
        // Ajouter l'action pour afficher le bouton
        add_action('job_manager_job_dashboard_column_ai_process', array($this, 'render_ai_process_button'), 10, 2);
        
        return $columns;
    }

    /**
     * Afficher le bouton de traitement IA pour une offre
     */
    public function render_ai_process_button($job) {
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/job-dashboard-ai-button.php';
    }

    /**
     * Gérer le traitement manuel d'une offre avec l'IA
     */
    public function handle_manual_job_processing() {
        // Vérifier le nonce pour la sécurité
        if (
            !isset($_GET['_wpnonce']) || 
            !isset($_GET['job_id']) || 
            !wp_verify_nonce($_GET['_wpnonce'], 'process_job_with_ai_' . $_GET['job_id'])
        ) {
            wp_die(__('Action non autorisée.', 'wpjm-openai'));
        }

        $job_id = intval($_GET['job_id']);
        
        // Traiter l'offre d'emploi
        $this->process_job_on_save($job_id, array());
        
        // Rediriger vers la page précédente
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Afficher le badge "Traité par IA"
     */
    public function display_ai_processed_badge() {
        include WPJM_OPENAI_PLUGIN_DIR . 'templates/ai-processed-badge.php';
    }
}

// Initialiser le plugin
function wpjm_openai() {
    return WPJM_OpenAI::get_instance();
}

// Démarrer le plugin
wpjm_openai();