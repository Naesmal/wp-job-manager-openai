<?php
/**
 * Intégrations avec d'autres plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Integrations {
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
        // Intégration avec GoFetchJobs si installé
        if (class_exists('GoFetchJobs')) {
            add_action('gofetchjobs_after_import_job', array($this, 'process_imported_job'), 10, 1);
        }

        // Autres intégrations possibles
    }

    /**
     * Traiter une offre importée via GoFetchJobs
     */
    public function process_imported_job($job_id) {
        // S'assurer que le traitement automatique est activé
        if ('yes' !== get_option('wpjm_openai_auto_process', 'yes')) {
            return;
        }

        // Récupérer le processeur OpenAI
        $processor = WPJM_OpenAI_Processor::get_instance();
        
        try {
            // Marquer l'offre comme étant en cours de traitement
            update_post_meta($job_id, '_ai_processing_in_progress', '1');
            
            // Traiter l'offre
            $processor->process_job($job_id);
            
            // Journaliser le traitement réussi
            update_post_meta($job_id, '_ai_processed', '1');
            update_post_meta($job_id, '_ai_processed_date', current_time('mysql'));
            
            // Journaliser la source
            update_post_meta($job_id, '_ai_process_source', 'gofetchjobs');
        } catch (Exception $e) {
            // Journaliser l'erreur
            update_post_meta($job_id, '_ai_processing_error', $e->getMessage());
        }

        // Supprimer le marqueur de traitement en cours
        delete_post_meta($job_id, '_ai_processing_in_progress');
    }
}

// Initialiser l'instance
WPJM_OpenAI_Integrations::get_instance();