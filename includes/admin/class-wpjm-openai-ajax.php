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
}

// Initialiser la classe
WPJM_OpenAI_Ajax::get_instance();