<?php
/**
 * Gestion des logs OpenAI
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Logs {
    /**
     * Instance unique de la classe
     */
    private static $instance = null;

    /**
     * Nom de la table de logs
     */
    private $table_name;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpjm_openai_logs';
    }

    /**
     * Créer la table de logs
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_id bigint(20) DEFAULT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            details longtext DEFAULT NULL,
            tokens int(11) DEFAULT 0,
            log_date datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY log_date (log_date)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Ajouter un log
     */
    public function add_log($job_id, $action, $status, $details = '', $tokens = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            [
                'job_id' => $job_id,
                'action' => $action,
                'status' => $status,
                'details' => $details,
                'tokens' => $tokens,
                'log_date' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );
        
        // Mettre à jour les statistiques
        $this->update_stats($status, $tokens);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Mettre à jour les statistiques
     */
    private function update_stats($status, $tokens) {
        if ('success' !== $status) {
            return;
        }
        
        // Incrémenter le compteur d'offres traitées
        $total_processed = get_option('wpjm_openai_stats_processed', 0);
        update_option('wpjm_openai_stats_processed', $total_processed + 1);
        
        // Ajouter les tokens utilisés
        $total_tokens = get_option('wpjm_openai_stats_tokens', 0);
        update_option('wpjm_openai_stats_tokens', $total_tokens + $tokens);
        
        // Mettre à jour la date du dernier traitement
        update_option('wpjm_openai_stats_last_process', current_time('mysql'));
    }
    
    /**
     * Récupérer les logs pour une offre
     */
    public function get_logs_for_job($job_id, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE job_id = %d ORDER BY log_date DESC LIMIT %d",
                $job_id,
                $limit
            )
        );
    }
    
    /**
     * Récupérer les derniers logs
     */
    public function get_recent_logs($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY log_date DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Nettoyer les anciens logs
     */
    public function clean_old_logs($days = 90) {
        global $wpdb;
        
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE log_date < %s",
                $date
            )
        );
    }
}