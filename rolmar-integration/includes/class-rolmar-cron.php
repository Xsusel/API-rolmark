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
        // Register cron hooks.
        add_action( 'rolmar_scheduled_product_sync', array( $this, 'run_product_sync' ) );
        add_action( 'rolmar_scheduled_stock_sync', array( $this, 'run_stock_sync' ) );
        add_action( 'rolmar_run_product_import', array( $this, 'run_product_sync' ) );
        add_action( 'rolmar_run_stock_sync', array( $this, 'run_stock_sync' ) );
        add_action( 'rolmar_run_photo_sync', array( $this, 'run_photo_sync' ) );
        add_action( 'rolmar_cleanup_logs', array( $this, 'cleanup_logs' ) );

        // Reschedule on frequency change.
        add_action( 'update_option_rolmar_sync_frequency', array( __CLASS__, 'on_frequency_change' ), 10, 2 );
    }

    /**
     * Schedule all recurring events.
     */
    public static function schedule_events() {
        $frequency = get_option( 'rolmar_sync_frequency', 'twicedaily' );

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
        wp_schedule_event( time() + HOUR_IN_SECONDS, $new_value, 'rolmar_scheduled_product_sync' );
    }

    /**
     * Run product sync (called by cron).
     */
    public function run_product_sync() {
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
        Rolmar_Logger::info( 'Cron: Starting scheduled stock sync.', 'cron' );

        $importer = new Rolmar_Product_Importer();
        $importer->sync_stock();

        Rolmar_Logger::info( 'Cron: Stock sync finished.', 'cron' );
    }

    /**
     * Run photo sync (called manually via AJAX).
     */
    public function run_photo_sync() {
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
