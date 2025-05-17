<?php
/**
 * Fonctions AJAX pour WP Job Manager OpenAI
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Ajax {
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
        add_action('wp_ajax_wpjm_openai_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_wpjm_openai_get_jobs_to_process', array($this, 'get_jobs_to_process'));
    }

    /**
     * Tester la connexion à l'API OpenAI
     */
    public function test_api_connection() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpjm_openai_admin_nonce')) {
            wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'wpjm-openai')));
        }

        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions suffisantes.', 'wpjm-openai')));
        }

        // Récupérer la clé API
        $api_key = get_option('wpjm_openai_api_key', '');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Aucune clé API configurée. Veuillez saisir votre clé API OpenAI.', 'wpjm-openai')));
        }

        // URL de l'API et modèle
        $api_url = 'https://api.openai.com/v1/chat/completions';
        $model = get_option('wpjm_openai_model', 'gpt-3.5-turbo');

        // Configurer la requête
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = json_encode(array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                array('role' => 'user', 'content' => 'Hello! This is a test message to verify the API connection. Please respond with a short message confirming the connection works.'),
            ),
            'max_tokens' => 50,
            'temperature' => 0.3,
        ));

        // Faire la requête
        $response = wp_remote_post($api_url, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ));

        // Analyser la réponse
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            $error_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($error_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Erreur API inconnue', 'wpjm-openai');
            
            wp_send_json_error(array(
                'message' => sprintf(__('Erreur API (Code: %d): %s', 'wpjm-openai'), $response_code, $error_message),
                'details' => $error_data
            ));
        }

        // Réponse réussie
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            wp_send_json_error(array('message' => __('Réponse API invalide', 'wpjm-openai')));
        }

        // Envoyer la réponse
        wp_send_json_success(array(
            'model' => $model,
            'message' => $data['choices'][0]['message']['content'],
            'tokens_used' => isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 'N/A'
        ));
    }
        /**
     * Récupérer les offres à traiter par lot
     */
    public function get_jobs_to_process() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpjm_openai_dashboard_nonce')) {
            wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'wpjm-openai')));
        }

        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions suffisantes.', 'wpjm-openai')));
        }

        // Récupérer les paramètres
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $limit = max(1, min(50, $limit)); // Limiter entre 1 et 50
        
        $job_status = isset($_POST['job_status']) ? sanitize_text_field($_POST['job_status']) : 'all';
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
            wp_send_json_success(array(
                'message' => __('Aucune offre d\'emploi à traiter selon les critères sélectionnés.', 'wpjm-openai'),
                'job_ids' => array()
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(__('%d offres trouvées.', 'wpjm-openai'), count($job_ids)),
                'job_ids' => $job_ids
            ));
        }
    }
}

// Initialiser la classe
WPJM_OpenAI_Ajax::get_instance();