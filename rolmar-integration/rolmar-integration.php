<?php
/**
 * Plugin Name: Rolmar Integration for WooCommerce
 * Plugin URI: 
 * Description: Integracja WooCommerce z API hurtowni Rolmar - import produktów, synchronizacja stanów magazynowych i zdjęć.
 * Version: 1.0.0
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

define( 'ROLMAR_PLUGIN_VERSION', '1.0.0' );
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
            'default_language'  => 'pl',
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
