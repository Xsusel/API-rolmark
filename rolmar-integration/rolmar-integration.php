<?php
/**
 * Plugin Name: Rolmar Integration for WooCommerce
 * Plugin URI: 
 * Description: Integracja WooCommerce z API hurtowni Rolmar - import produktów, synchronizacja stanów magazynowych i zdjęć.
 * Version: 1.0.6
 * Author: Jakub Wcisło
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * Text Domain: rolmar-integration
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ROLMAR_PLUGIN_VERSION', '1.0.6' );
define( 'ROLMAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROLMAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ROLMAR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Rolmar_Integration {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once ROLMAR_PLUGIN_DIR . 'includes/class-rolmar-logger.php';
        require_once ROLMAR_PLUGIN_DIR . 'includes/class-rolmar-api-client.php';
        require_once ROLMAR_PLUGIN_DIR . 'includes/class-rolmar-admin.php';
        require_once ROLMAR_PLUGIN_DIR . 'includes/class-rolmar-product-importer.php';
        require_once ROLMAR_PLUGIN_DIR . 'includes/class-rolmar-cron.php';
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'admin_init', array( $this, 'check_woocommerce' ) );
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init() {
        Rolmar_Admin::instance();
        Rolmar_Cron::instance();
    }

    public function activate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( ROLMAR_PLUGIN_BASENAME );
            wp_die(
                __( 'Wtyczka Rolmar Integration wymaga zainstalowanego i aktywnego WooCommerce.', 'rolmar-integration' ),
                __( 'Brak WooCommerce', 'rolmar-integration' ),
                array( 'back_link' => true )
            );
        }

        // Set default options.
        $defaults = array(
            'api_key'           => '',
            'api_environment'   => 'production',
            'discount_percent'  => 0,
            'sync_frequency'    => 'twicedaily',
            'batch_size'        => 50,
            'import_images'     => 'yes',
            'manage_stock'      => 'yes',
            'default_language'      => 'pl',
            'allowed_categories'    => array(),
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'rolmar_' . $key ) ) {
                add_option( 'rolmar_' . $key, $value );
            }
        }

        // Schedule cron.
        Rolmar_Cron::schedule_events();

        // Create log directory.
        Rolmar_Logger::create_log_dir();

        // Create common product attributes.
        $this->create_common_attributes();
    }

    /**
     * Create common product attributes used by Rolmar products.
     */
    private function create_common_attributes() {
        global $wpdb;

        $attributes_to_create = array(
            array(
                'slug'  => 'marka',
                'label' => __( 'Marka', 'rolmar-integration' ),
            ),
        );

        foreach ( $attributes_to_create as $attr ) {
            $slug  = $attr['slug'];
            $label = $attr['label'];

            // Check if attribute already exists in database.
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                    $slug
                )
            );

            if ( ! $existing ) {
                // Insert directly into database.
                $wpdb->insert(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                    array(
                        'attribute_name'    => $slug,
                        'attribute_label'   => $label,
                        'attribute_type'    => 'select',
                        'attribute_orderby' => 'menu_order',
                        'attribute_public'  => 0,
                    ),
                    array( '%s', '%s', '%s', '%s', '%d' )
                );

                // Clear the cache.
                delete_transient( 'wc_attribute_taxonomies' );
            }

            // Register the taxonomy.
            $taxonomy = 'pa_' . $slug;
            if ( ! taxonomy_exists( $taxonomy ) ) {
                register_taxonomy(
                    $taxonomy,
                    'product',
                    array(
                        'labels'       => array( 'name' => $label ),
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    )
                );
            }
        }

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    public function deactivate() {
        Rolmar_Cron::clear_events();
    }

    public function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'Rolmar Integration wymaga zainstalowanego i aktywnego WooCommerce.', 'rolmar-integration' );
                echo '</p></div>';
            } );
        }
    }
}

/**
 * Boot the plugin after all plugins loaded.
 */
function rolmar_integration_init() {
    return Rolmar_Integration::instance();
}
add_action( 'plugins_loaded', 'rolmar_integration_init' );
