<?php
/**
 * Gestion de l'administration du plugin WP Job Manager OpenAI
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPJM_OpenAI_Admin {
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
        // Ajouter les menus d'administration
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enregistrer les paramètres
        add_action('admin_init', array($this, 'register_settings'));
        
        // Ajouter des styles et scripts admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Ajouter le menu d'administration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('WP Job Manager OpenAI Settings', 'wpjm-openai'),
            __('OpenAI Settings', 'wpjm-openai'),
            'manage_options',
            'wpjm-openai-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enregistrer les paramètres
     */
    public function register_settings() {
        register_setting('wpjm_openai_settings', 'wpjm_openai_api_key');
        register_setting('wpjm_openai_settings', 'wpjm_openai_model', array(
            'default' => 'gpt-3.5-turbo', // Utiliser GPT-3.5 par défaut pour un meilleur rapport coût/performances
        ));
        register_setting('wpjm_openai_settings', 'wpjm_openai_max_tokens', array(
            'default' => 1000,
            'sanitize_callback' => 'absint',
        ));
        register_setting('wpjm_openai_settings', 'wpjm_openai_temperature', array(
            'default' => 0.3,
            'sanitize_callback' => array($this, 'sanitize_temperature'),
        ));
        register_setting('wpjm_openai_settings', 'wpjm_openai_auto_process', array(
            'default' => 'yes',
        ));

        // Sections des paramètres
        add_settings_section(
            'wpjm_openai_api_settings',
            __('Paramètres de l\'API OpenAI', 'wpjm-openai'),
            array($this, 'render_api_settings_section'),
            'wpjm_openai_settings'
        );
        
        add_settings_section(
            'wpjm_openai_job_settings',
            __('Paramètres de traitement des offres', 'wpjm-openai'),
            array($this, 'render_job_settings_section'),
            'wpjm_openai_settings'
        );

        // Champs des paramètres
        add_settings_field(
            'wpjm_openai_api_key',
            __('Clé API OpenAI', 'wpjm-openai'),
            array($this, 'render_api_key_field'),
            'wpjm_openai_settings',
            'wpjm_openai_api_settings'
        );

        add_settings_field(
            'wpjm_openai_model',
            __('Modèle OpenAI', 'wpjm-openai'),
            array($this, 'render_model_field'),
            'wpjm_openai_settings',
            'wpjm_openai_api_settings'
        );

        add_settings_field(
            'wpjm_openai_max_tokens',
            __('Tokens maximum', 'wpjm-openai'),
            array($this, 'render_max_tokens_field'),
            'wpjm_openai_settings',
            'wpjm_openai_api_settings'
        );

        add_settings_field(
            'wpjm_openai_temperature',
            __('Température', 'wpjm-openai'),
            array($this, 'render_temperature_field'),
            'wpjm_openai_settings',
            'wpjm_openai_api_settings'
        );

        add_settings_field(
            'wpjm_openai_auto_process',
            __('Traitement automatique', 'wpjm-openai'),
            array($this, 'render_auto_process_field'),
            'wpjm_openai_settings',
            'wpjm_openai_job_settings'
        );
    }

    /**
     * Validation de la température
     */
    public function sanitize_temperature($input) {
        $input = floatval($input);
        if ($input < 0) {
            $input = 0;
        } elseif ($input > 1) {
            $input = 1;
        }
        return $input;
    }

    /**
     * Afficher les scripts d'administration
     */
    public function enqueue_admin_scripts($hook) {
        if ('job_listing_page_wpjm-openai-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('wpjm-openai-admin', WPJM_OPENAI_PLUGIN_URL . 'assets/css/admin.css', array(), WPJM_OPENAI_VERSION);
        wp_enqueue_script('wpjm-openai-admin', WPJM_OPENAI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WPJM_OPENAI_VERSION, true);
    }

    /**
     * Afficher la page de paramètres
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'wpjm-openai'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wpjm_openai_settings');
                do_settings_sections('wpjm_openai_settings');
                submit_button();
                ?>
            </form>

            <div class="wpjm-openai-test-section">
                <h2><?php _e('Tester l\'API OpenAI', 'wpjm-openai'); ?></h2>
                <p><?php _e('Cliquez sur le bouton ci-dessous pour tester la connexion à l\'API OpenAI.', 'wpjm-openai'); ?></p>
                <button id="wpjm-openai-test-api" class="button button-secondary">
                    <?php _e('Tester la connexion', 'wpjm-openai'); ?>
                </button>
                <div id="wpjm-openai-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Afficher la section des paramètres API
     */
    public function render_api_settings_section() {
        echo '<p>' . __('Configurez les paramètres de l\'API OpenAI pour le traitement automatique des offres d\'emploi.', 'wpjm-openai') . '</p>';
    }

    /**
     * Afficher la section des paramètres des offres
     */
    public function render_job_settings_section() {
        echo '<p>' . __('Configurez les paramètres de traitement des offres d\'emploi.', 'wpjm-openai') . '</p>';
    }

    /**
     * Afficher le champ de la clé API
     */
    public function render_api_key_field() {
        $api_key = get_option('wpjm_openai_api_key', '');
        echo '<input type="password" id="wpjm_openai_api_key" name="wpjm_openai_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Votre clé API OpenAI. Vous pouvez l\'obtenir sur le site <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>.', 'wpjm-openai') . '</p>';
    }

    /**
     * Afficher le champ du modèle
     */
    public function render_model_field() {
        $model = get_option('wpjm_openai_model', 'gpt-3.5-turbo');
        ?>
        <select id="wpjm_openai_model" name="wpjm_openai_model">
            <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>><?php _e('GPT-3.5 Turbo (Recommandé)', 'wpjm-openai'); ?></option>
            <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>><?php _e('GPT-4 (Plus puissant mais plus coûteux)', 'wpjm-openai'); ?></option>
            <option value="gpt-4-turbo" <?php selected($model, 'gpt-4-turbo'); ?>><?php _e('GPT-4 Turbo', 'wpjm-openai'); ?></option>
        </select>
        <p class="description"><?php _e('Le modèle OpenAI à utiliser pour le traitement des offres.', 'wpjm-openai'); ?></p>
        <?php
    }

    /**
     * Afficher le champ du nombre maximum de tokens
     */
    public function render_max_tokens_field() {
        $max_tokens = get_option('wpjm_openai_max_tokens', 1000);
        echo '<input type="number" id="wpjm_openai_max_tokens" name="wpjm_openai_max_tokens" value="' . esc_attr($max_tokens) . '" min="100" max="4000" step="100" />';
        echo '<p class="description">' . __('Le nombre maximum de tokens à utiliser pour chaque requête. Plus cette valeur est élevée, plus la requête est coûteuse.', 'wpjm-openai') . '</p>';
    }

    /**
     * Afficher le champ de la température
     */
    public function render_temperature_field() {
        $temperature = get_option('wpjm_openai_temperature', 0.3);
        echo '<input type="range" id="wpjm_openai_temperature" name="wpjm_openai_temperature" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
        echo '<span id="temperature_value">' . esc_html($temperature) . '</span>';
        echo '<p class="description">' . __('La température contrôle la créativité des réponses. Une valeur plus basse donne des résultats plus déterministes et précis.', 'wpjm-openai') . '</p>';
    }

    /**
     * Afficher le champ du traitement automatique
     */
    public function render_auto_process_field() {
        $auto_process = get_option('wpjm_openai_auto_process', 'yes');
        ?>
        <label>
            <input type="radio" name="wpjm_openai_auto_process" value="yes" <?php checked($auto_process, 'yes'); ?> />
            <?php _e('Oui - Traiter automatiquement les nouvelles offres', 'wpjm-openai'); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="wpjm_openai_auto_process" value="no" <?php checked($auto_process, 'no'); ?> />
            <?php _e('Non - Traiter manuellement uniquement', 'wpjm-openai'); ?>
        </label>
        <p class="description"><?php _e('Si activé, les offres d\'emploi seront automatiquement traitées lors de leur création ou mise à jour.', 'wpjm-openai'); ?></p>
        <?php
    }
}