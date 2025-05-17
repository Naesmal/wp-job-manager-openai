<?php
/**
 * Processeur OpenAI pour les offres d'emploi
 */

 /**
 * Fonction de journalisation pour le débogage
 */
function wpjm_openai_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Processor {
    /**
     * Instance unique de la classe
     */
    private static $instance = null;

    /**
     * Clé API OpenAI
     */
    private $api_key = '';

    /**
     * Modèle OpenAI à utiliser
     */
    private $model = 'gpt-3.5-turbo';

    /**
     * Nombre maximum de tokens
     */
    private $max_tokens = 1000;

    /**
     * Température pour l'IA
     */
    private $temperature = 0.3;

    /**
     * Catégories principales de The Good Feat
     */
    private $main_categories = array(
        'Technical and Sectors',
        'Program Management and Coordination',
        'Finance, HR and Operations',
        'Business Development, Grants Management and Partnerships',
        'Communication and Advocacy',
        'Technology and Innovation',
        'Monitoring, Evaluation and Research',
        'Capacity Building, Training and Development',
        'Localization',
        'Other'
    );

    /**
     * Sous-catégories de The Good Feat
     */
    private $sub_categories = array(
        'Technical and Sectors' => array(
            'Agriculture, Food Security and Livelihoods',
            'Crisis Response',
            'Education and Training',
            'Health and Social Services',
            'Environment and Climate',
            'Gender and Inclusivity',
            'Justice, Migration and Refugees',
            'Protection and Human Rights',
            'Economic Development',
            'Governance and Elections',
            'Infrastructure and Engineering',
            'Criminology and Security',
            'Logistics and Procurement',
            'Peacebuilding',
            'Social Protection'
        ),
        'Program Management and Coordination' => array(
            'Cluster Coordination',
            'Project & Program Planning, Management'
        ),
        'Finance, HR and Operations' => array(
            'Finance and Budget',
            'Human Resources',
            'Ethics and Audit'
        ),
        'Business Development, Grants Management and Partnerships' => array(
            'Business Development',
            'Grant Writing',
            'Partnerships',
            'Capacity Strengthening'
        ),
        'Communication and Advocacy' => array(
            'Strategic Communication',
            'Public Relations',
            'Advocacy'
        ),
        'Technology and Innovation' => array(
            'UX / UI',
            'ICT4D',
            'Data Systems',
            'Tech for Development',
            'E-learning',
            'Innovation'
        ),
        'Monitoring, Evaluation and Research' => array(
            'Monitoring & Evaluation',
            'Research & Data',
            'Policy Analysis',
            'Assessments',
            'Reviews'
        ),
        'Capacity Building, Training and Development' => array(
            'Training and Coaching',
            'Certification & Skills',
            'Organizational Development'
        ),
        'Localization' => array(
            'Local Governance',
            'Civil Society'
        ),
        'Other' => array()
    );

    /**
     * Types de consultance
     */
    private $consultancy_types = array(
        'full_time' => 'Temps plein',
        'part_time' => 'Temps partiel',
        'individual_consultant' => 'Consultant individuel',
        'team_consultant' => 'Équipe de consultants',
        'open_to_firms' => 'Ouvert aux firmes',
        'open_to_ngos' => 'Ouvert aux ONG partenaires',
        'national' => 'National',
        'international' => 'International',
        'short_term' => 'Short term: moins de 12 mois',
        'long_term' => 'Long term: plus de 12 mois',
        'entry_level' => 'Entry level: Moins de 5 ans d\'expérience',
        'mid_level' => 'Mid-level: 5-10 ans d\'expérience',
        'senior_level' => 'Senior level: Plus de 10 d\'expérience'
    );

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
        // Charger les paramètres
        $this->api_key = get_option('wpjm_openai_api_key', '');
        $this->model = get_option('wpjm_openai_model', 'gpt-3.5-turbo');
        $this->max_tokens = (int) get_option('wpjm_openai_max_tokens', 1000);
        $this->temperature = (float) get_option('wpjm_openai_temperature', 0.3);
    }

    /**
     * Traiter une offre d'emploi avec OpenAI
     */
    public function process_job($job_id) {
        if (empty($this->api_key)) {
            throw new Exception(__('Clé API OpenAI non configurée', 'wpjm-openai'));
        }

        // Récupérer les données de l'offre
        $job = get_post($job_id);
        if (!$job || 'job_listing' !== $job->post_type) {
            throw new Exception(__('Offre d\'emploi invalide', 'wpjm-openai'));
        }

        // Récupérer le contenu et les métadonnées de l'offre
        $job_data = $this->get_job_data($job);

        // Analyser le pays et la région
        $this->process_country_and_region($job_id, $job_data);

        // Catégoriser l'offre
        $this->process_job_categories($job_id, $job_data);

        // Déterminer le type de consultance
        $this->process_consultancy_type($job_id, $job_data);

        // Extraire le lien de candidature et l'email
        $this->process_application_link($job_id, $job_data);

        // Traiter la date de clôture
        $this->process_closing_date($job_id, $job_data);

        // Détecter si le poste est en remote
        $this->process_remote_position($job_id, $job_data);

        // Cacher la date d'expiration par défaut
        update_post_meta($job_id, '_hide_expiration_date', 1);

        return true;
    }

    /**
     * Récupérer les données de l'offre d'emploi
     */
    private function get_job_data($job) {
        $job_data = array(
            'id' => $job->ID,
            'title' => $job->post_title,
            'content' => $job->post_content,
            'location' => get_post_meta($job->ID, '_job_location', true),
            'country' => get_post_meta($job->ID, '_job_country', true),
            'region' => get_post_meta($job->ID, '_job_region', true),
            'application' => get_post_meta($job->ID, '_application', true),
            'closing_date' => get_post_meta($job->ID, '_job_expires', true),
            'remote_position' => get_post_meta($job->ID, '_remote_position', true),
        );

        return $job_data;
    }

    /**
     * Faire une requête API à OpenAI
     */
    private function request_openai_api($messages) {
        // Journaliser les messages envoyés à l'API
        wpjm_openai_debug_log("Envoi de la requête à l'API OpenAI");
        wpjm_openai_debug_log("Modèle utilisé: " . $this->model);
        
        $api_url = 'https://api.openai.com/v1/chat/completions';

        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );

        $body = json_encode(array(
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
        ));

        wpjm_openai_debug_log("Paramètres de la requête: max_tokens=" . $this->max_tokens . ", temperature=" . $this->temperature);

        $response = wp_remote_post($api_url, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30, // Augmenter le timeout pour les offres volumineuses
        ));

        if (is_wp_error($response)) {
            wpjm_openai_debug_log("Erreur WP: " . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Erreur API inconnue', 'wpjm-openai');
            
            wpjm_openai_debug_log("Erreur API (Code: $response_code): " . $error_message);
            
            throw new Exception($error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            wpjm_openai_debug_log("Réponse API invalide: " . print_r($data, true));
            throw new Exception(__('Réponse API invalide', 'wpjm-openai'));
        }

        wpjm_openai_debug_log("Réponse API reçue avec succès");
        
        // Journaliser l'utilisation des tokens
        if (isset($data['usage'])) {
            wpjm_openai_debug_log("Tokens utilisés: " . $data['usage']['total_tokens']);
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Analyser et corriger le pays et la région
     */
    private function process_country_and_region($job_id, $job_data) {
        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu corriges ou détermines le pays réel où le poste est basé. ";
        $prompt .= "Si la valeur du pays est incorrecte (ex. nom d'organisation au lieu d'un pays), corrige-la. ";
        $prompt .= "De plus, détermine si l'offre est: ";
        $prompt .= "1. Global (couvre plusieurs pays sur plusieurs continents) ";
        $prompt .= "2. Remote (position totalement en télétravail) ";
        $prompt .= "3. Hybrid (télétravail avec missions terrain) ";
        $prompt .= "Réponds uniquement au format JSON avec les champs: country (nom du pays en anglais), region (nom de la région), is_global (true/false), is_remote (true/false), et is_hybrid (true/false).";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans l\'analyse des offres d\'emploi dans le secteur humanitaire et du développement.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\nLieu indiqué: " . $job_data['location'] . "\nPays indiqué: " . $job_data['country'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 2000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result)) {
                throw new Exception('Format de réponse invalide');
            }
            
            // Mettre à jour le pays
            if (!empty($result['country'])) {
                update_post_meta($job_id, '_job_country', sanitize_text_field($result['country']));
            }
            
            // Mettre à jour la région
            if (!empty($result['region'])) {
                update_post_meta($job_id, '_job_region', sanitize_text_field($result['region']));
            }
            
            // Champ global
            if (isset($result['is_global']) && true === $result['is_global']) {
                update_post_meta($job_id, '_job_is_global', '1');
            } else {
                delete_post_meta($job_id, '_job_is_global');
            }
            
            // Champ remote (sera également mis à jour dans process_remote_position)
            if (isset($result['is_remote']) && true === $result['is_remote']) {
                update_post_meta($job_id, '_remote_position', '1');
            }
            
            // Champ hybrid
            if (isset($result['is_hybrid']) && true === $result['is_hybrid']) {
                update_post_meta($job_id, '_job_is_hybrid', '1');
            } else {
                delete_post_meta($job_id, '_job_is_hybrid');
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur d\'analyse du pays : ' . $e->getMessage());
        }
    }

    /**
     * Catégoriser l'offre d'emploi
     */
    private function process_job_categories($job_id, $job_data) {
        // Créer une liste formatée des catégories et sous-catégories
        $categories_list = '';
        foreach ($this->sub_categories as $main_cat => $sub_cats) {
            $categories_list .= "$main_cat:\n";
            if (!empty($sub_cats)) {
                foreach ($sub_cats as $sub_cat) {
                    $categories_list .= "- $sub_cat\n";
                }
            }
            $categories_list .= "\n";
        }

        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu détermines une ou plusieurs grandes catégories qui correspondent à cette offre. ";
        $prompt .= "Utilise les sous-catégories pour guider ton analyse, mais je veux que tu me retournes uniquement les grandes catégories. ";
        $prompt .= "Voici la liste des catégories disponibles:\n\n$categories_list";
        $prompt .= "Réponds au format JSON avec un champ 'categories' contenant un tableau des grandes catégories. ";
        $prompt .= "Inclus également un champ 'explanation' expliquant brièvement ton choix.";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans la catégorisation des offres d\'emploi dans le secteur humanitaire et du développement.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 3000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result) || !isset($result['categories']) || !is_array($result['categories'])) {
                throw new Exception('Format de réponse invalide');
            }
            
            // Supprimer les catégories existantes
            wp_set_object_terms($job_id, array(), 'job_listing_category');
            
            // Ajouter les nouvelles catégories
            foreach ($result['categories'] as $category) {
                $term = term_exists($category, 'job_listing_category');
                
                if (!$term) {
                    // Créer le terme s'il n'existe pas
                    $term = wp_insert_term($category, 'job_listing_category');
                }
                
                if (!is_wp_error($term)) {
                    wp_set_object_terms($job_id, (int)$term['term_id'], 'job_listing_category', true);
                }
            }
            
            // Sauvegarder l'explication pour référence
            if (isset($result['explanation'])) {
                update_post_meta($job_id, '_ai_category_explanation', sanitize_text_field($result['explanation']));
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur de catégorisation : ' . $e->getMessage());
        }
    }

    /**
     * Déterminer les types de consultance
     */
    private function process_consultancy_type($job_id, $job_data) {
        // Créer une liste formatée des types de consultance
        $types_list = '';
        foreach ($this->consultancy_types as $key => $label) {
            $types_list .= "- $label\n";
        }

        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu détermines quels types de consultance correspondent à cette offre. ";
        $prompt .= "Ne coche que les cases qui correspondent clairement à l'offre. Si l'information n'est pas explicitement mentionnée, ne la coche pas. ";
        $prompt .= "Voici les types de consultance possibles:\n\n$types_list";
        $prompt .= "Réponds au format JSON avec des paires clé-valeur pour chaque type de consultance (true si applicable, false sinon). ";
        $prompt .= "Les clés doivent être: full_time, part_time, individual_consultant, team_consultant, open_to_firms, open_to_ngos, national, international, short_term, long_term, entry_level, mid_level, senior_level.";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans l\'analyse des offres d\'emploi dans le secteur humanitaire et du développement.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 3000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result)) {
                throw new Exception('Format de réponse invalide');
            }
            
            // Mettre à jour chaque type de consultance
            foreach ($this->consultancy_types as $key => $label) {
                if (isset($result[$key]) && true === $result[$key]) {
                    update_post_meta($job_id, '_job_consultancy_' . $key, '1');
                } else {
                    delete_post_meta($job_id, '_job_consultancy_' . $key);
                }
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur d\'analyse des types de consultance : ' . $e->getMessage());
        }
    }

    /**
     * Extraire le lien de candidature et l'email
     */
    private function process_application_link($job_id, $job_data) {
        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu extraies le lien URL pour postuler (différent du lien d'importation de l'offre). ";
        $prompt .= "Si aucun lien URL n'est disponible, extrais l'adresse email de contact pour postuler. ";
        $prompt .= "Réponds au format JSON avec les champs: application_url et application_email.";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans l\'extraction d\'informations des offres d\'emploi.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\nApplication actuelle: " . $job_data['application'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 3000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result)) {
                throw new Exception('Format de réponse invalide');
            }
            
            $application_value = '';
            
            // Priorité au lien URL
            if (!empty($result['application_url']) && filter_var($result['application_url'], FILTER_VALIDATE_URL)) {
                $application_value = esc_url_raw($result['application_url']);
            }
            // Si pas de lien URL valide, utiliser l'email
            elseif (!empty($result['application_email']) && is_email($result['application_email'])) {
                $application_value = sanitize_email($result['application_email']);
            }
            
            // Mettre à jour seulement si une valeur valide a été trouvée et est différente
            if (!empty($application_value) && $application_value !== $job_data['application']) {
                update_post_meta($job_id, '_application', $application_value);
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur d\'extraction du lien de candidature : ' . $e->getMessage());
        }
    }

    /**
     * Extraire la date de clôture
     */
    private function process_closing_date($job_id, $job_data) {
        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu extraies la date de clôture de l'offre (date limite de candidature). ";
        $prompt .= "Réponds au format JSON avec un champ 'closing_date' au format YYYY-MM-DD.";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans l\'extraction d\'informations des offres d\'emploi.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 3000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result) || !isset($result['closing_date'])) {
                throw new Exception('Format de réponse invalide');
            }
            
            // Valider le format de la date
            $date = $result['closing_date'];
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            
            if ($date_obj && $date_obj->format('Y-m-d') === $date) {
                // Mettre à jour la date d'expiration
                update_post_meta($job_id, '_job_expires', $date);
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur d\'extraction de la date de clôture : ' . $e->getMessage());
        }
    }

    /**
     * Détecter si le poste est en remote
     */
    private function process_remote_position($job_id, $job_data) {
        $prompt = "Je vais te donner les informations d'une offre d'emploi. J'ai besoin que tu détermines si le poste est en remote (télétravail). ";
        $prompt .= "Réponds au format JSON avec un champ 'is_remote' (true/false).";

        $messages = array(
            array('role' => 'system', 'content' => 'Tu es un assistant expert dans l\'analyse des offres d\'emploi.'),
            array('role' => 'user', 'content' => $prompt . "\n\nTitre de l'offre: " . $job_data['title'] . "\n\nContenu de l'offre:\n" . substr($job_data['content'], 0, 2000) . "...")
        );

        $response = $this->request_openai_api($messages);
        
        try {
            $result = json_decode($response, true);
            
            if (!$result || !is_array($result) || !isset($result['is_remote'])) {
                throw new Exception('Format de réponse invalide');
            }
            
            // Mettre à jour le statut remote
            if (true === $result['is_remote']) {
                update_post_meta($job_id, '_remote_position', '1');
            } else {
                delete_post_meta($job_id, '_remote_position');
            }
            
        } catch (Exception $e) {
            // Journaliser l'erreur mais continuer le traitement
            error_log('Erreur de détection du remote : ' . $e->getMessage());
        }
    }
}