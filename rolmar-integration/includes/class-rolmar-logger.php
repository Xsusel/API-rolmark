<?php
/**
 * Simple file logger for Rolmar Integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolmar_Logger {

    const LOG_DIR = 'rolmar-logs';

    /**
     * Create log directory with .htaccess protection.
     */
    public static function create_log_dir() {
        $dir = self::get_log_dir();
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            file_put_contents( $dir . '/.htaccess', 'deny from all' );
        }
    }

    public static function get_log_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . self::LOG_DIR;
    }

    /**
     * Log a message.
     *
     * @param string $message  Log message.
     * @param string $level    Log level: info, warning, error.
     * @param string $context  Context identifier (e.g. 'import', 'stock', 'api').
     */
    public static function log( $message, $level = 'info', $context = 'general' ) {
        $dir = self::get_log_dir();
        if ( ! file_exists( $dir ) ) {
            self::create_log_dir();
        }

        $date     = current_time( 'Y-m-d' );
        $time     = current_time( 'Y-m-d H:i:s' );
        $file     = trailingslashit( $dir ) . "{$context}-{$date}.log";
        $level    = strtoupper( $level );
        $entry    = "[{$time}] [{$level}] {$message}" . PHP_EOL;

        error_log( $entry, 3, $file );
    }

    public static function info( $message, $context = 'general' ) {
        self::log( $message, 'info', $context );
    }

    public static function warning( $message, $context = 'general' ) {
        self::log( $message, 'warning', $context );
    }

    public static function error( $message, $context = 'general' ) {
        self::log( $message, 'error', $context );
    }

    /**
     * Get recent log entries.
     *
     * @param string $context  Context name.
     * @param int    $lines    Number of lines to return.
     * @return string
     */
    public static function get_recent_logs( $context = 'general', $lines = 100 ) {
        $date = current_time( 'Y-m-d' );
        $file = trailingslashit( self::get_log_dir() ) . "{$context}-{$date}.log";

        if ( ! file_exists( $file ) ) {
            return __( 'Brak logów na dzisiaj.', 'rolmar-integration' );
        }

        $all_lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( empty( $all_lines ) ) {
            return __( 'Plik logów jest pusty.', 'rolmar-integration' );
        }

        $recent = array_slice( $all_lines, -$lines );
        return implode( PHP_EOL, $recent );
    }

    /**
     * Clear old log files (older than 30 days).
     */
    public static function cleanup_old_logs() {
        $dir   = self::get_log_dir();
        $files = glob( $dir . '/*.log' );
        $now   = time();

        foreach ( $files as $file ) {
            if ( $now - filemtime( $file ) > 30 * DAY_IN_SECONDS ) {
                unlink( $file );
            }
        }
    }
}
