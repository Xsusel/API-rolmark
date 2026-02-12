<?php
/**
 * Rolmar API Client.
 *
 * Handles all communication with the Rolmar datalink API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolmar_API_Client {

    /**
     * API base URLs.
     */
    const BASE_URL_PRODUCTION = 'https://datalink.rol-mar.com.pl/v1/';
    const BASE_URL_TEST       = 'https://datalink.rol-mar.com.pl/v1_test/';

    /**
     * Request timeout in seconds.
     */
    const TIMEOUT = 120;

    private $api_key;
    private $base_url;
    private $language;

    public function __construct() {
        $this->api_key   = get_option( 'rolmar_api_key', '' );
        $environment     = get_option( 'rolmar_api_environment', 'production' );
        $this->base_url  = ( 'test' === $environment ) ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
        $this->language  = get_option( 'rolmar_default_language', 'pl' );
    }

    /**
     * Check if API key is configured.
     */
    public function is_configured() {
        return ! empty( $this->api_key );
    }

    /**
     * Get all products from the API.
     *
     * @param string $product_index  Specific product index (empty = all).
     * @param string $brand          Filter by brand.
     * @param int    $type           Offer type: 1=nowy, 2=standard, 3=wyprzedaż.
     * @return array|WP_Error
     */
    public function get_products( $product_index = '', $brand = '', $type = 0 ) {
        $params = array();

        if ( ! empty( $product_index ) ) {
            $params['productIndex'] = $product_index;
        }
        if ( ! empty( $brand ) ) {
            $params['brand'] = $brand;
        }
        if ( $type > 0 ) {
            $params['type'] = $type;
        }

        $params['categorySeparator'] = '>';

        return $this->request( 'product/products.php', 'getProducts', $params );
    }

    /**
     * Get inactive products (deactivated within last 5 days).
     *
     * @return array|WP_Error
     */
    public function get_inactive_products() {
        return $this->request( 'product/products.php', 'getInactiveProducts' );
    }

    /**
     * Get stock levels for all products.
     *
     * @return array|WP_Error
     */
    public function get_stock() {
        return $this->request( 'stock/stock.php', 'getStock' );
    }

    /**
     * Get photo URLs for all products.
     *
     * @return array|WP_Error
     */
    public function get_photos() {
        return $this->request( 'photo/photo.php', 'getPhotos' );
    }

    /**
     * Save order to Rolmar.
     *
     * @param array $product_data   Array of product strings "index;qty;price;01;;;unit".
     * @param array $delivery_data  Delivery information array.
     * @return array|WP_Error
     */
    public function save_order( $product_data, $delivery_data ) {
        $params = array(
            'productData'  => $product_data,
            'deliveryData' => $delivery_data,
        );

        return $this->request( 'order/order.php', 'saveOrder', $params );
    }

    /**
     * Test the API connection.
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        // Request a single known product to test; if none specified, request with empty params.
        return $this->request( 'product/products.php', 'getProducts', array(
            'categorySeparator' => '>',
        ) );
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint  API endpoint path.
     * @param string $method    API method name.
     * @param array  $params    Request parameters.
     * @return array|WP_Error
     */
    private function request( $endpoint, $method, $params = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'rolmar_no_api_key', __( 'Klucz API Rolmar nie jest skonfigurowany.', 'rolmar-integration' ) );
        }

        $url = $this->base_url . $endpoint . '?' . http_build_query( array(
            'm'    => $method,
            'lang' => $this->language,
        ) );

// Budowa body zgodnie z dokumentacją API Rolmar
        $body = wp_json_encode( array(
            'wsKey' => $this->api_key,
            'data'  => array(
                array(
                    'param' => ! empty( $params ) ? $params : array(),
                ),
            ),
        ) );

        Rolmar_Logger::info( "API Request: {$method} -> {$url}", 'api' );

        $response = wp_remote_post( $url, array(
            'timeout' => self::TIMEOUT,
            'headers' => array(
                'Content-Type' => 'application/json',
                'wsKey'        => $this->api_key, // Zmiana z 'key' na 'wsKey'
            ),
            'body' => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            Rolmar_Logger::error( "API Error ({$method}): " . $response->get_error_message(), 'api' );
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );

        if ( $http_code < 200 || $http_code >= 300 ) {
            $error_msg = "API HTTP {$http_code} ({$method}): {$raw_body}";
            Rolmar_Logger::error( $error_msg, 'api' );
            return new WP_Error( 'rolmar_http_error', $error_msg );
        }

        // DEBUG: Log first 5000 chars of raw response for getPhotos
        if ( 'getPhotos' === $method ) {
            $preview = substr( $raw_body, 0, 5000 );
            Rolmar_Logger::info( "DEBUG getPhotos RAW Response (first 5000 chars): {$preview}", 'api' );
        }

        $data = json_decode( $raw_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $error_msg = "API JSON parse error ({$method}): " . json_last_error_msg();
            Rolmar_Logger::error( $error_msg, 'api' );
            return new WP_Error( 'rolmar_json_error', $error_msg );
        }

        // The API wraps results in an array with 'result' key.
        if ( is_array( $data ) && isset( $data[0]['result'] ) ) {
            $result = $data[0]['result'];
            $count  = is_array( $result ) ? count( $result ) : 0;
            Rolmar_Logger::info( "API Response ({$method}): {$count} records", 'api' );
            return $result;
        }

        // Return raw data if structure is different.
        Rolmar_Logger::info( "API Response ({$method}): raw response returned", 'api' );
        return $data;
    }
}
