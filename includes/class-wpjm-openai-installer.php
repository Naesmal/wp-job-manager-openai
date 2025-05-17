<?php
/**
 * Installation des données initiales pour WP Job Manager OpenAI
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Installer {
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
        // Rien à faire ici
    }

    /**
     * Installer toutes les données initiales
     */
    public function install() {
        // Créer les catégories principales
        $this->create_main_categories();
        
        // Ajouter les champs personnalisés pour les types de consultance
        $this->add_consultancy_custom_fields();
        
        // Mettre à jour les options par défaut
        $this->set_default_options();
        
        // Journaliser l'installation
        update_option('wpjm_openai_installed', '1');
        update_option('wpjm_openai_installed_version', WPJM_OPENAI_VERSION);
        update_option('wpjm_openai_installed_date', current_time('mysql'));
    }

    /**
     * Créer les catégories principales
     */
    private function create_main_categories() {
        $main_categories = array(
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

        // Vérifier si la taxonomie existe
        if (!taxonomy_exists('job_listing_category')) {
            return;
        }

        // Créer les catégories principales si elles n'existent pas déjà
        foreach ($main_categories as $category_name) {
            if (!term_exists($category_name, 'job_listing_category')) {
                wp_insert_term($category_name, 'job_listing_category', array(
                    'description' => sprintf(__('Catégorie %s pour les offres d\'emploi', 'wpjm-openai'), $category_name)
                ));
            }
        }
    }

    /**
     * Ajouter les champs personnalisés pour les types de consultance
     */
    private function add_consultancy_custom_fields() {
        // Liste des types de consultance
        $consultancy_types = array(
            'full_time' => __('Temps plein', 'wpjm-openai'),
            'part_time' => __('Temps partiel', 'wpjm-openai'),
            'individual_consultant' => __('Consultant individuel', 'wpjm-openai'),
            'team_consultant' => __('Équipe de consultants', 'wpjm-openai'),
            'open_to_firms' => __('Ouvert aux firmes', 'wpjm-openai'),
            'open_to_ngos' => __('Ouvert aux ONG partenaires', 'wpjm-openai'),
            'national' => __('National', 'wpjm-openai'),
            'international' => __('International', 'wpjm-openai'),
            'short_term' => __('Short term: moins de 12 mois', 'wpjm-openai'),
            'long_term' => __('Long term: plus de 12 mois', 'wpjm-openai'),
            'entry_level' => __('Entry level: Moins de 5 ans d\'expérience', 'wpjm-openai'),
            'mid_level' => __('Mid-level: 5-10 ans d\'expérience', 'wpjm-openai'),
            'senior_level' => __('Senior level: Plus de 10 d\'expérience', 'wpjm-openai')
        );

        // Nous utilisons l'option pour stocker les définitions des types de consultance
        update_option('wpjm_openai_consultancy_types', $consultancy_types);

        // Vérifier si le filtre pour les champs personnalisés existe
        if (!function_exists('wpjm_openai_add_job_fields')) {
            // Ajouter le filtre pour ajouter nos champs personnalisés à WP Job Manager
            add_filter('job_manager_job_listing_data_fields', 'wpjm_openai_add_job_fields');

            /**
             * Ajouter les champs personnalisés aux offres d'emploi
             */
            function wpjm_openai_add_job_fields($fields) {
                // Champs pour le pays et la région
                $fields['_job_country'] = array(
                    'label' => __('Pays', 'wpjm-openai'),
                    'type' => 'text',
                    'placeholder' => __('Pays où le poste est basé', 'wpjm-openai'),
                    'description' => __('Pays où le poste est basé. Ce champ sera automatiquement rempli par l\'IA.', 'wpjm-openai'),
                    'priority' => 8,
                );

                $fields['_job_region'] = array(
                    'label' => __('Région', 'wpjm-openai'),
                    'type' => 'text',
                    'placeholder' => __('Région où le poste est basé', 'wpjm-openai'),
                    'description' => __('Région où le poste est basé. Ce champ sera automatiquement rempli par l\'IA.', 'wpjm-openai'),
                    'priority' => 9,
                );

                $fields['_job_is_global'] = array(
                    'label' => __('Poste Global', 'wpjm-openai'),
                    'type' => 'checkbox',
                    'description' => __('Cochez si le poste couvre plusieurs pays sur plusieurs continents.', 'wpjm-openai'),
                    'priority' => 10,
                );

                $fields['_job_is_hybrid'] = array(
                    'label' => __('Poste Hybride', 'wpjm-openai'),
                    'type' => 'checkbox',
                    'description' => __('Cochez si le poste combine télétravail et missions terrain.', 'wpjm-openai'),
                    'priority' => 11,
                );

                // Champs pour les types de consultance
                $consultancy_types = get_option('wpjm_openai_consultancy_types', array());
                $priority = 50;

                foreach ($consultancy_types as $key => $label) {
                    $fields['_job_consultancy_' . $key] = array(
                        'label' => $label,
                        'type' => 'checkbox',
                        'description' => '',
                        'priority' => $priority++,
                    );
                }

                return $fields;
            }
        }
    }

    /**
     * Définir les options par défaut
     */
    private function set_default_options() {
        $default_options = array(
            'wpjm_openai_model' => 'gpt-3.5-turbo',
            'wpjm_openai_max_tokens' => 1000,
            'wpjm_openai_temperature' => 0.3,
            'wpjm_openai_auto_process' => 'yes',
        );

        foreach ($default_options as $option_name => $option_value) {
            if (false === get_option($option_name)) {
                update_option($option_name, $option_value);
            }
        }
    }
}

// Fonction pour exécuter l'installation
function wpjm_openai_install() {
    $installer = WPJM_OpenAI_Installer::get_instance();
    $installer->install();
}

// Exécuter l'installation lors de l'activation du plugin
register_activation_hook(WPJM_OPENAI_PLUGIN_DIR . 'wp-job-manager-openai.php', 'wpjm_openai_install');