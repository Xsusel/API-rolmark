<?php
/**
 * Rolmar Cron / Scheduled Tasks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolmar_Cron {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register cron hooks. The rolmar_scheduled_* hooks are recurring events
        // and acquire the sync lock themselves; the rolmar_run_* hooks are one-off
        // events spawned by the admin AJAX handlers, which set the lock beforehand.
        add_action( 'rolmar_scheduled_product_sync', array( $this, 'run_scheduled_product_sync' ) );
        add_action( 'rolmar_scheduled_stock_sync', array( $this, 'run_scheduled_stock_sync' ) );
        add_action( 'rolmar_run_product_import', array( $this, 'run_product_sync' ) );
        add_action( 'rolmar_run_stock_sync', array( $this, 'run_stock_sync' ) );
        add_action( 'rolmar_run_photo_sync', array( $this, 'run_photo_sync' ) );
        add_action( 'rolmar_cleanup_logs', array( $this, 'cleanup_logs' ) );

        // Reschedule on frequency change. add_option_* fires when the option is
        // saved for the first time; both hooks pass the new value as 2nd arg.
        add_action( 'update_option_rolmar_sync_frequency', array( __CLASS__, 'on_frequency_change' ), 10, 2 );
        add_action( 'add_option_rolmar_sync_frequency', array( __CLASS__, 'on_frequency_change' ), 10, 2 );

        // Self-heal: schedule_events() is idempotent, so make sure the recurring
        // events exist on every request. This repairs installations where the
        // activation hook never ran and the schedule was never created.
        self::schedule_events();
    }

    /**
     * Validate a sync frequency against schedules we offer in settings.
     */
    private static function sanitize_frequency( $frequency ) {
        $valid = array( 'hourly', 'twicedaily', 'daily' );
        return in_array( $frequency, $valid, true ) ? $frequency : 'twicedaily';
    }

    /**
     * Schedule all recurring events.
     */
    public static function schedule_events() {
        $frequency = self::sanitize_frequency( get_option( 'rolmar_sync_frequency', 'twicedaily' ) );

        if ( ! wp_next_scheduled( 'rolmar_scheduled_product_sync' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, 'rolmar_scheduled_product_sync' );
        }

        // Stock sync more frequently (hourly).
        if ( ! wp_next_scheduled( 'rolmar_scheduled_stock_sync' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'rolmar_scheduled_stock_sync' );
        }

        // Log cleanup daily.
        if ( ! wp_next_scheduled( 'rolmar_cleanup_logs' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'rolmar_cleanup_logs' );
        }
    }

    /**
     * Clear all scheduled events.
     */
    public static function clear_events() {
        wp_clear_scheduled_hook( 'rolmar_scheduled_product_sync' );
        wp_clear_scheduled_hook( 'rolmar_scheduled_stock_sync' );
        wp_clear_scheduled_hook( 'rolmar_cleanup_logs' );
        wp_clear_scheduled_hook( 'rolmar_run_product_import' );
        wp_clear_scheduled_hook( 'rolmar_run_stock_sync' );
        wp_clear_scheduled_hook( 'rolmar_run_photo_sync' );
    }

    /**
     * Reschedule when sync frequency option changes.
     */
    public static function on_frequency_change( $old_value, $new_value ) {
        wp_clear_scheduled_hook( 'rolmar_scheduled_product_sync' );
        wp_schedule_event( time() + HOUR_IN_SECONDS, self::sanitize_frequency( $new_value ), 'rolmar_scheduled_product_sync' );
    }

    /**
     * Check that WooCommerce is active before running any sync.
     *
     * The recurring events keep firing even when WooCommerce gets deactivated;
     * without this guard every run would fatal on wc_* functions.
     */
    private function woocommerce_available( $job ) {
        if ( class_exists( 'WooCommerce' ) ) {
            return true;
        }
        Rolmar_Logger::error( "Cron: Skipping {$job} - WooCommerce is not active.", 'cron' );
        delete_transient( 'rolmar_sync_in_progress' );
        return false;
    }

    /**
     * Run the recurring product sync, guarding against overlapping runs.
     */
    public function run_scheduled_product_sync() {
        if ( get_transient( 'rolmar_sync_in_progress' ) ) {
            Rolmar_Logger::warning( 'Cron: Skipping scheduled product sync - another sync is already running.', 'cron' );
            return;
        }
        set_transient( 'rolmar_sync_in_progress', 'products', HOUR_IN_SECONDS );
        $this->run_product_sync();
    }

    /**
     * Run the recurring stock sync, guarding against overlapping runs.
     */
    public function run_scheduled_stock_sync() {
        if ( get_transient( 'rolmar_sync_in_progress' ) ) {
            Rolmar_Logger::warning( 'Cron: Skipping scheduled stock sync - another sync is already running.', 'cron' );
            return;
        }
        set_transient( 'rolmar_sync_in_progress', 'stock', HOUR_IN_SECONDS );
        $this->run_stock_sync();
    }

    /**
     * Run product sync (called by cron).
     */
    public function run_product_sync() {
        if ( ! $this->woocommerce_available( 'product sync' ) ) {
            return;
        }

        // Increase limits for long-running import.
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }
        @ini_set( 'memory_limit', '512M' );

        Rolmar_Logger::info( 'Cron: Starting scheduled product sync.', 'cron' );

        $importer = new Rolmar_Product_Importer();
        $result   = $importer->run_import();

        if ( $result ) {
            Rolmar_Logger::info( 'Cron: Product sync completed successfully.', 'cron' );
        } else {
            Rolmar_Logger::error( 'Cron: Product sync failed.', 'cron' );
        }
    }

    /**
     * Run stock sync (called by cron).
     */
    public function run_stock_sync() {
        if ( ! $this->woocommerce_available( 'stock sync' ) ) {
            return;
        }

        Rolmar_Logger::info( 'Cron: Starting scheduled stock sync.', 'cron' );

        $importer = new Rolmar_Product_Importer();
        $importer->sync_stock();

        Rolmar_Logger::info( 'Cron: Stock sync finished.', 'cron' );
    }

    /**
     * Run photo sync (called manually via AJAX).
     */
    public function run_photo_sync() {
        if ( ! $this->woocommerce_available( 'photo sync' ) ) {
            return;
        }

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }
        @ini_set( 'memory_limit', '512M' );

        Rolmar_Logger::info( 'Starting photo sync.', 'cron' );

        $importer = new Rolmar_Product_Importer();
        $importer->sync_photos();

        Rolmar_Logger::info( 'Photo sync finished.', 'cron' );
    }

    /**
     * Clean up old log files.
     */
    public function cleanup_logs() {
        Rolmar_Logger::cleanup_old_logs();
    }
}
