<?php
/**
 * Rolmar Product Importer.
 *
 * Handles importing products from Rolmar API into WooCommerce,
 * including categories, images, stock, and inactive product handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolmar_Product_Importer {

    private $api;
    private $discount_percent;
    private $batch_size;
    private $import_images;
    private $manage_stock;
    private $allowed_categories;
    private $attribute_creation_cache = array();

    public function __construct() {
        $this->api              = new Rolmar_API_Client();
        $this->discount_percent = floatval( get_option( 'rolmar_discount_percent', 0 ) );
        $this->batch_size       = intval( get_option( 'rolmar_batch_size', 50 ) );
        $this->import_images    = get_option( 'rolmar_import_images', 'yes' ) === 'yes';
        $this->manage_stock     = get_option( 'rolmar_manage_stock', 'yes' ) === 'yes';
        $this->allowed_categories = get_option( 'rolmar_allowed_categories', array() );
        if ( ! is_array( $this->allowed_categories ) ) {
            $this->allowed_categories = array();
        }
    }

    /**
     * Run full product import.
     */
    public function run_import() {
        Rolmar_Logger::info( 'Starting product import...', 'import' );

        // Ensure common attributes exist before import starts.
        $this->ensure_common_attributes();

        $this->update_progress( 'fetching', __( 'Pobieranie produktów z API...', 'rolmar-integration' ) );

        $products = $this->api->get_products();

        if ( is_wp_error( $products ) ) {
            Rolmar_Logger::error( 'Failed to fetch products: ' . $products->get_error_message(), 'import' );
            $this->update_progress( 'error', $products->get_error_message() );
            delete_transient( 'rolmar_sync_in_progress' );
            return false;
        }

        if ( ! is_array( $products ) ) {
            Rolmar_Logger::error( 'Invalid products response from API.', 'import' );
            $this->update_progress( 'error', __( 'Nieprawidłowa odpowiedź z API.', 'rolmar-integration' ) );
            delete_transient( 'rolmar_sync_in_progress' );
            return false;
        }

        $total   = count( $products );
        $created = 0;
        $updated = 0;
        $errors  = 0;
        $skipped = 0;

        $has_category_filter = ! empty( $this->allowed_categories );
        if ( $has_category_filter ) {
            Rolmar_Logger::info( 'Category filter active with ' . count( $this->allowed_categories ) . ' allowed paths.', 'import' );
        }

        Rolmar_Logger::info( "Fetched {$total} products from API. Starting import...", 'import' );
        $this->update_progress( 'importing', sprintf( __( 'Importowanie 0 / %d produktów...', 'rolmar-integration' ), $total ), $total );

        foreach ( $products as $index => $product_data ) {
            // Category filter check.
            if ( $has_category_filter && ! $this->is_product_allowed( $product_data ) ) {
                $skipped++;

                // Update progress every batch_size items even for skipped.
                if ( ( $index + 1 ) % $this->batch_size === 0 || ( $index + 1 ) === $total ) {
                    $processed = $index + 1;
                    $this->update_progress(
                        'importing',
                        sprintf(
                            __( 'Importowanie %1$d / %2$d produktów (nowych: %3$d, zaktualizowanych: %4$d, pominiętych: %5$d, błędów: %6$d)', 'rolmar-integration' ),
                            $processed,
                            $total,
                            $created,
                            $updated,
                            $skipped,
                            $errors
                        ),
                        $total,
                        $processed,
                        $created,
                        $updated,
                        $errors
                    );
                }
                continue;
            }

            try {
                $result = $this->import_single_product( $product_data );

                if ( 'created' === $result ) {
                    $created++;
                } elseif ( 'updated' === $result ) {
                    $updated++;
                }
            } catch ( Exception $e ) {
                $sku = isset( $product_data['productIndex'] ) ? $product_data['productIndex'] : 'unknown';
                Rolmar_Logger::error( "Error importing product {$sku}: " . $e->getMessage(), 'import' );
                $errors++;
            }

            // Update progress every batch_size items.
            if ( ( $index + 1 ) % $this->batch_size === 0 || ( $index + 1 ) === $total ) {
                $processed = $index + 1;
                $this->update_progress(
                    'importing',
                    sprintf(
                        __( 'Importowanie %1$d / %2$d produktów (nowych: %3$d, zaktualizowanych: %4$d, pominiętych: %5$d, błędów: %6$d)', 'rolmar-integration' ),
                        $processed,
                        $total,
                        $created,
                        $updated,
                        $skipped,
                        $errors
                    ),
                    $total,
                    $processed,
                    $created,
                    $updated,
                    $errors
                );

                // Free memory.
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                }
            }
        }

        // Handle inactive products.
        $this->handle_inactive_products();

        $message = sprintf(
            __( 'Import zakończony. Łącznie: %1$d, nowych: %2$d, zaktualizowanych: %3$d, pominiętych: %4$d, błędów: %5$d', 'rolmar-integration' ),
            $total,
            $created,
            $updated,
            $skipped,
            $errors
        );
        Rolmar_Logger::info( $message, 'import' );
        $this->update_progress( 'done', $message, $total, $total, $created, $updated, $errors );

        update_option( 'rolmar_last_product_sync', current_time( 'mysql' ) );
        delete_transient( 'rolmar_sync_in_progress' );

        return true;
    }

    /**
     * Import a single product into WooCommerce.
     *
     * @param array $data  Product data from API.
     * @return string      'created', 'updated', or 'skipped'.
     */
    private function import_single_product( $data ) {
        $sku = isset( $data['productIndex'] ) ? sanitize_text_field( $data['productIndex'] ) : '';

        if ( empty( $sku ) ) {
            return 'skipped';
        }

        // Find existing product by SKU.
        $product_id = wc_get_product_id_by_sku( $sku );
        $is_new     = empty( $product_id );

        if ( $is_new ) {
            $product = new WC_Product_Simple();
        } else {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $product = new WC_Product_Simple();
                $is_new  = true;
            }
        }

        // Basic data.
        $product->set_sku( $sku );
        $product->set_name( isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $sku );
        $product->set_description( isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '' );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );

        // Price calculation: retailPrice * (1 - discount/100).
        if ( ! empty( $data['retailPrice'] ) ) {
            $retail_price = $this->parse_price( $data['retailPrice'] );
            $shop_price   = $retail_price * ( 1 - $this->discount_percent / 100 );
            $shop_price   = round( $shop_price, 2 );

            $product->set_regular_price( $shop_price );
        }

        // Weight (convert comma decimal to dot).
        if ( ! empty( $data['weight'] ) ) {
            $weight = str_replace( ',', '.', $data['weight'] );
            $product->set_weight( floatval( $weight ) );
        }

        // EAN.
        if ( ! empty( $data['ean'] ) ) {
            $product->update_meta_data( '_rolmar_ean', sanitize_text_field( $data['ean'] ) );
            // Also set GTIN for WooCommerce (if supported).
            $product->update_meta_data( '_global_unique_id', sanitize_text_field( $data['ean'] ) );
        }

        // Rolmar internal ID.
        if ( ! empty( $data['id'] ) ) {
            $product->update_meta_data( '_rolmar_id', sanitize_text_field( $data['id'] ) );
        }

        // Product type (1=nowy, 2=standard, 3=wyprzedaż).
        if ( isset( $data['type'] ) ) {
            $product->update_meta_data( '_rolmar_type', intval( $data['type'] ) );
        }

        // Brand.
        if ( ! empty( $data['brand'] ) ) {
            $product->update_meta_data( '_rolmar_brand', sanitize_text_field( $data['brand'] ) );
            $this->set_product_brand( $product, $data['brand'] );
        }

        // CN code (customs nomenclature).
        if ( ! empty( $data['cn'] ) ) {
            $product->update_meta_data( '_rolmar_cn', sanitize_text_field( $data['cn'] ) );
        }

        // Unit.
        if ( ! empty( $data['unit'] ) ) {
            $product->update_meta_data( '_rolmar_unit', sanitize_text_field( $data['unit'] ) );
        }

        // Cubature.
        if ( ! empty( $data['cubature'] ) ) {
            $product->update_meta_data( '_rolmar_cubature', sanitize_text_field( $data['cubature'] ) );
        }

        // Fits (compatible products).
        if ( ! empty( $data['fits'] ) ) {
            $product->update_meta_data( '_rolmar_fits', sanitize_text_field( $data['fits'] ) );
        }

        // Retail price (original, for reference).
        if ( ! empty( $data['retailPrice'] ) ) {
            $product->update_meta_data( '_rolmar_retail_price', sanitize_text_field( $data['retailPrice'] ) );
        }

        // Mark as Rolmar product.
        $product->update_meta_data( '_rolmar_product', 'yes' );
        $product->update_meta_data( '_rolmar_last_sync', current_time( 'mysql' ) );

        // Specifications -> product attributes.
        if ( ! empty( $data['specifications'] ) && is_array( $data['specifications'] ) ) {
            $this->set_product_specifications( $product, $data['specifications'] );
        }

        // Save product to get an ID (needed for categories and images).
        $product_id = $product->save();

        // Categories.
        if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
            $this->set_product_categories( $product_id, $data['categories'] );
        }

        // Main photo (only on creation or if no image exists).
        if ( $this->import_images && ! empty( $data['mainPhoto'] ) ) {
            // Debug: Log first few raw mainPhoto values to understand API response format.
            static $debug_count = 0;
            if ( $debug_count < 3 ) {
                Rolmar_Logger::info( "DEBUG RAW mainPhoto for {$sku}: " . print_r( $data['mainPhoto'], true ), 'import' );
                $debug_count++;
            }
            $this->maybe_set_product_image( $product_id, $data['mainPhoto'], $sku );
        }

        return $is_new ? 'created' : 'updated';
    }

    /**
     * Parse price string from API (comma as decimal separator).
     *
     * @param string $price_string  e.g. "1009,89".
     * @return float
     */
    private function parse_price( $price_string ) {
        $price = str_replace( array( ' ', "\xc2\xa0" ), '', $price_string ); // Remove spaces/nbsp.
        $price = str_replace( ',', '.', $price );
        return floatval( $price );
    }

    /**
     * Set product brand as a product attribute and/or taxonomy.
     */
    private function set_product_brand( $product, $brand_name ) {
        $brand_name = sanitize_text_field( $brand_name );

        if ( empty( $brand_name ) ) {
            return;
        }

        // Use pa_marka taxonomy for brand.
        $taxonomy = 'pa_marka';
        if ( ! taxonomy_exists( $taxonomy ) ) {
            // Create the attribute if it doesn't exist.
            $attribute_id = $this->ensure_product_attribute( 'marka', __( 'Marka', 'rolmar-integration' ) );
            if ( false === $attribute_id ) {
                // If attribute creation failed, store brand as meta data instead.
                $product->update_meta_data( '_product_brand', $brand_name );
                return;
            }
        }

        // Ensure the taxonomy is registered before creating terms.
        if ( ! taxonomy_exists( $taxonomy ) ) {
            Rolmar_Logger::warning( "Taxonomy {$taxonomy} does not exist, cannot set brand '{$brand_name}'", 'import' );
            $product->update_meta_data( '_product_brand', $brand_name );
            return;
        }

        $term = term_exists( $brand_name, $taxonomy );
        if ( ! $term ) {
            $term = wp_insert_term( $brand_name, $taxonomy );
        }

        if ( is_wp_error( $term ) ) {
            Rolmar_Logger::warning( "Failed to create brand term '{$brand_name}': " . $term->get_error_message(), 'import' );
            $product->update_meta_data( '_product_brand', $brand_name );
            return;
        }

        $term_id = is_array( $term ) ? $term['term_id'] : $term;

        $attributes = $product->get_attributes();
        $attribute  = new WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
        $attribute->set_name( $taxonomy );
        $attribute->set_options( array( (int) $term_id ) );
        $attribute->set_visible( true );
        $attribute->set_variation( false );
        $attributes[ $taxonomy ] = $attribute;
        $product->set_attributes( $attributes );
    }

    /**
     * Set product specifications as WooCommerce attributes.
     */
    private function set_product_specifications( $product, $specifications ) {
        $attributes = $product->get_attributes();

        foreach ( $specifications as $spec ) {
            if ( empty( $spec['name'] ) || ! isset( $spec['value'] ) ) {
                continue;
            }

            $attr_name  = sanitize_text_field( $spec['name'] );
            $attr_value = sanitize_text_field( $spec['value'] );
            $unit_name  = isset( $spec['unit_name'] ) ? sanitize_text_field( $spec['unit_name'] ) : '';

            if ( ! empty( $unit_name ) ) {
                $attr_value .= ' ' . $unit_name;
            }

            // Use local (non-taxonomy) attributes for specifications.
            $attr_slug = sanitize_title( $attr_name );
            $attribute = new WC_Product_Attribute();
            $attribute->set_id( 0 ); // Local attribute.
            $attribute->set_name( $attr_name );
            $attribute->set_options( array( $attr_value ) );
            $attribute->set_visible( true );
            $attribute->set_variation( false );
            $attributes[ $attr_slug ] = $attribute;
        }

        $product->set_attributes( $attributes );
    }

    /**
     * Set product categories from Rolmar category paths.
     *
     * @param int   $product_id  WooCommerce product ID.
     * @param array $categories  Array of category path strings like "Hydraulika siłowa>Węże>Podtyp".
     */
        private function set_product_categories( $product_id, $categories ) {
        $term_ids = array();

        foreach ( $categories as $category_path ) {
            $parts     = array_map( 'trim', explode( '>', $category_path ) );
            $parent_id = 0;
            $last_term_id = 0;

            foreach ( $parts as $cat_name ) {
                if ( empty( $cat_name ) ) continue;

                // Find or create term with proper parent handling.
                $term_id = $this->get_or_create_category( $cat_name, $parent_id );

                if ( $term_id ) {
                    $last_term_id = $term_id;
                    $parent_id = $term_id; // Next category will be child of this one.
                }
            }

            if ( $last_term_id ) {
                $term_ids[] = $last_term_id; // Add only the last (deepest) category in path.
            }
        }

        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $product_id, array_unique( $term_ids ), 'product_cat' );
        }
    }

    /**
     * Get or create a category term with proper parent handling.
     *
     * This function properly checks for existing categories by name AND parent,
     * which term_exists() doesn't always do reliably.
     *
     * @param string $name       Category name.
     * @param int    $parent_id  Parent category ID (0 for top-level).
     * @return int|false         Term ID on success, false on failure.
     */
    private function get_or_create_category( $name, $parent_id = 0 ) {
        global $wpdb;

        // Find existing term by name and parent.
        $term = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.term_id, tt.parent
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE t.name = %s
             AND tt.taxonomy = 'product_cat'
             AND tt.parent = %d
             LIMIT 1",
            $name,
            $parent_id
        ) );

        if ( $term ) {
            return intval( $term->term_id );
        }

        // Term doesn't exist, create it.
        $result = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent_id ) );

        if ( is_wp_error( $result ) ) {
            Rolmar_Logger::warning( "Failed to create category '{$name}' (parent: {$parent_id}): " . $result->get_error_message(), 'import' );
            return false;
        }

        return isset( $result['term_id'] ) ? intval( $result['term_id'] ) : false;
    }

    /**
     * Check if a product is allowed by the category filter.
     *
     * A product is allowed if any of its category paths matches or is a subcategory
     * of any allowed path.
     *
     * @param array $data  Product data from API.
     * @return bool
     */
    private function is_product_allowed( $data ) {
        if ( empty( $data['categories'] ) || ! is_array( $data['categories'] ) ) {
            return false;
        }

        foreach ( $data['categories'] as $product_path ) {
            $product_path = trim( $product_path );
            foreach ( $this->allowed_categories as $allowed_path ) {
                // Exact match or the product path starts with the allowed path (subcategory).
                if ( $product_path === $allowed_path || strpos( $product_path, $allowed_path . '/' ) === 0 ) {
                    return true;
                }
                // Also allow if the allowed path is a child of the product path
                // (user selected a more specific category and product belongs to it).
                if ( strpos( $allowed_path, $product_path . '/' ) === 0 || $allowed_path === $product_path ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set product featured image if not already set.
     */
    private function maybe_set_product_image( $product_id, $image_url, $sku ) {
        // Log image processing attempt.
        Rolmar_Logger::info( "Processing image for {$sku}: URL = " . esc_url( $image_url ), 'import' );

        // Check if product already has a valid featured image.
        $existing_thumbnail = get_post_thumbnail_id( $product_id );
        if ( $existing_thumbnail ) {
            // Verify the existing image actually exists.
            $existing_file = get_attached_file( $existing_thumbnail );
            if ( $existing_file && file_exists( $existing_file ) ) {
                Rolmar_Logger::info( "Product {$sku} already has valid image (ID: {$existing_thumbnail}), skipping download.", 'import' );
                return;
            } else {
                // Existing thumbnail ID is invalid - remove it and download new image.
                Rolmar_Logger::info( "Product {$sku} has invalid image reference (ID: {$existing_thumbnail}), will download new image.", 'import' );
                delete_post_thumbnail( $product_id );
            }
        }

        $image_id = $this->upload_image_from_url( $image_url, $sku );
        if ( $image_id ) {
            set_post_thumbnail( $product_id, $image_id );
            Rolmar_Logger::info( "Successfully set image for {$sku} (Image ID: {$image_id})", 'import' );
        } else {
            Rolmar_Logger::warning( "Failed to set image for {$sku}", 'import' );
        }
    }

    /**
     * Upload an image from URL to WordPress media library.
     *
     * @param string $url  Image URL.
     * @param string $sku  Product SKU (for naming).
     * @return int|false    Attachment ID or false.
     */
    private function upload_image_from_url( $url, $sku ) {
        // Validate URL before attempting download.
        if ( empty( $url ) ) {
            Rolmar_Logger::warning( "No image URL provided for {$sku}", 'import' );
            return false;
        }

        // Clean up malformed URLs from API.
        $original_url = $url;

        // Remove trailing dots and spaces.
        $url = rtrim( $url, '. ' );

        // Fix malformed query parameters (e.g., "?c=-bth.." -> remove the parameter entirely).
        $url = preg_replace( '/\?c=-[^&]*$/', '', $url );

        if ( $original_url !== $url ) {
            Rolmar_Logger::info( "Cleaned malformed URL for {$sku}: {$original_url} -> {$url}", 'import' );
        }

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            Rolmar_Logger::warning( "Invalid image URL format for {$sku}: {$url}", 'import' );
            return false;
        }

        Rolmar_Logger::info( "Attempting to download image for {$sku} from: {$url}", 'import' );

        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Download file to temp.
        $tmp = download_url( $url, 30 );

        if ( is_wp_error( $tmp ) ) {
            $error_message = $tmp->get_error_message();
            $error_code = $tmp->get_error_code();

            // Log with appropriate level based on error type.
            if ( strpos( $error_message, 'Not Found' ) !== false || strpos( $error_message, '404' ) !== false ) {
                Rolmar_Logger::warning( "Image not found (404) for {$sku}: {$url}", 'import' );
            } else {
                Rolmar_Logger::error( "Failed to download image for {$sku}: [{$error_code}] {$error_message} | URL: {$url}", 'import' );
            }
            return false;
        }

        $file_ext  = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
        $file_ext  = $file_ext ?: 'png';
        $file_name = sanitize_file_name( $sku . '.' . $file_ext );

        $file_array = array(
            'name'     => $file_name,
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            $error_msg = $attachment_id->get_error_message();
            Rolmar_Logger::error( "Failed to import image to media library for {$sku}: {$error_msg}", 'import' );
            @unlink( $tmp );
            return false;
        }

        Rolmar_Logger::info( "Successfully uploaded image for {$sku} (Attachment ID: {$attachment_id})", 'import' );
        return $attachment_id;
    }

    /**
     * Ensure a WooCommerce product attribute taxonomy exists.
     */
    private function ensure_product_attribute( $slug, $label ) {
        global $wpdb;

        // Check runtime cache first to avoid repeated DB queries and creation attempts.
        if ( isset( $this->attribute_creation_cache[ $slug ] ) ) {
            return $this->attribute_creation_cache[ $slug ];
        }

        // Check if attribute already exists using WooCommerce function.
        $attribute_id = wc_attribute_taxonomy_id_by_name( 'pa_' . $slug );
        if ( $attribute_id ) {
            $this->attribute_creation_cache[ $slug ] = $attribute_id;
            $this->ensure_taxonomy_registered( $slug, $label );
            return $attribute_id;
        }

        // Double-check in database directly in case the taxonomy isn't registered yet.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                $slug
            )
        );

        if ( $existing ) {
            // Attribute exists in DB but taxonomy not registered - register it now.
            $this->ensure_taxonomy_registered( $slug, $label );
            $this->attribute_creation_cache[ $slug ] = $existing;
            delete_transient( 'wc_attribute_taxonomies' );
            return $existing;
        }

        // Attribute doesn't exist - try to create it directly in database.
        // This bypasses WooCommerce's validation which may be causing issues.
        $inserted = $wpdb->insert(
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

        if ( $inserted ) {
            $attribute_id = $wpdb->insert_id;
            $this->ensure_taxonomy_registered( $slug, $label );
            $this->attribute_creation_cache[ $slug ] = $attribute_id;
            delete_transient( 'wc_attribute_taxonomies' );
            Rolmar_Logger::info( "Created attribute '{$slug}' with ID {$attribute_id}", 'import' );
            return $attribute_id;
        }

        // If direct insertion failed, log the error.
        Rolmar_Logger::warning(
            sprintf(
                "Failed to create attribute '%s': Database insertion failed. Error: %s",
                $slug,
                $wpdb->last_error
            ),
            'import'
        );

        // Cache the failure to prevent repeated attempts.
        $this->attribute_creation_cache[ $slug ] = false;
        return false;
    }

    /**
     * Ensure taxonomy is registered for an attribute.
     */
    private function ensure_taxonomy_registered( $slug, $label ) {
        $taxonomy = 'pa_' . $slug;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy( $taxonomy, 'product', array(
                'labels'       => array( 'name' => $label ),
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            ) );
        }
    }

    /**
     * Ensure common product attributes exist before import.
     * This runs automatically at the start of each import, requiring no manual intervention.
     */
    private function ensure_common_attributes() {
        global $wpdb;

        $common_attributes = array(
            array(
                'slug'  => 'marka',
                'label' => __( 'Marka', 'rolmar-integration' ),
            ),
        );

        foreach ( $common_attributes as $attr ) {
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
                $inserted = $wpdb->insert(
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

                if ( $inserted ) {
                    Rolmar_Logger::info( "Auto-created attribute '{$slug}' (ID: {$wpdb->insert_id})", 'import' );
                    delete_transient( 'wc_attribute_taxonomies' );
                }
            }

            // Always ensure taxonomy is registered.
            $this->ensure_taxonomy_registered( $slug, $label );
        }
    }

    /**
     * Handle inactive products (deactivated within last 5 days).
     */
    private function handle_inactive_products() {
        Rolmar_Logger::info( 'Checking for inactive products...', 'import' );

        $inactive = $this->api->get_inactive_products();

        if ( is_wp_error( $inactive ) || ! is_array( $inactive ) ) {
            Rolmar_Logger::warning( 'Could not fetch inactive products.', 'import' );
            return;
        }

        $count = 0;
        foreach ( $inactive as $item ) {
            if ( empty( $item['productIndex'] ) ) {
                continue;
            }

            $product_id = wc_get_product_id_by_sku( $item['productIndex'] );
            if ( ! $product_id ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( $product && 'draft' !== $product->get_status() ) {
                $product->set_status( 'draft' );
                $product->set_catalog_visibility( 'hidden' );
                $product->update_meta_data( '_rolmar_deactivation_date', sanitize_text_field( $item['deactivationDate'] ?? '' ) );
                $product->save();
                $count++;
            }
        }

        Rolmar_Logger::info( "Deactivated {$count} products.", 'import' );
    }

    /**
     * Synchronize stock levels.
     */
    public function sync_stock() {
        if ( ! $this->manage_stock ) {
            Rolmar_Logger::info( 'Stock management disabled in settings. Skipping.', 'stock' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        Rolmar_Logger::info( 'Starting stock sync...', 'stock' );

        $stock_data = $this->api->get_stock();

        if ( is_wp_error( $stock_data ) ) {
            Rolmar_Logger::error( 'Failed to fetch stock: ' . $stock_data->get_error_message(), 'stock' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        if ( ! is_array( $stock_data ) ) {
            Rolmar_Logger::error( 'Invalid stock response from API.', 'stock' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        $updated = 0;
        $errors  = 0;

        foreach ( $stock_data as $item ) {
            // The stock API response structure may vary - try common field names.
            $sku = '';
            $qty = 0;

            if ( isset( $item['productIndex'] ) ) {
                $sku = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $sku = $item['index'];
            }

            if ( isset( $item['quantity'] ) ) {
                $qty = intval( $item['quantity'] );
            } elseif ( isset( $item['stock'] ) ) {
                $qty = intval( $item['stock'] );
            } elseif ( isset( $item['qty'] ) ) {
                $qty = intval( $item['qty'] );
            }

            if ( empty( $sku ) ) {
                continue;
            }

            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            try {
                $product->set_manage_stock( true );
                $product->set_stock_quantity( $qty );
                $product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
                $product->save();
                $updated++;
            } catch ( Exception $e ) {
                Rolmar_Logger::error( "Stock update error for {$sku}: " . $e->getMessage(), 'stock' );
                $errors++;
            }
        }

        Rolmar_Logger::info( "Stock sync done. Updated: {$updated}, Errors: {$errors}", 'stock' );

        update_option( 'rolmar_last_stock_sync', current_time( 'mysql' ) );
        delete_transient( 'rolmar_sync_in_progress' );
    }

    /**
     * Synchronize product photos from the getPhotos endpoint.
     */
    public function sync_photos() {
        if ( ! $this->import_images ) {
            Rolmar_Logger::info( 'Image import disabled in settings. Skipping.', 'import' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        Rolmar_Logger::info( 'Starting photo sync...', 'import' );

        $photos = $this->api->get_photos();

        if ( is_wp_error( $photos ) ) {
            Rolmar_Logger::error( 'Failed to fetch photos: ' . $photos->get_error_message(), 'import' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        if ( ! is_array( $photos ) ) {
            Rolmar_Logger::error( 'Invalid photos response from API.', 'import' );
            delete_transient( 'rolmar_sync_in_progress' );
            return;
        }

        $updated = 0;

        foreach ( $photos as $item ) {
            $sku = '';
            $photo_urls = array();

            // Handle various possible response structures.
            if ( isset( $item['productIndex'] ) ) {
                $sku = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $sku = $item['index'];
            }

            if ( isset( $item['photos'] ) && is_array( $item['photos'] ) ) {
                $photo_urls = $item['photos'];
            } elseif ( isset( $item['images'] ) && is_array( $item['images'] ) ) {
                $photo_urls = $item['images'];
            } elseif ( isset( $item['photo'] ) ) {
                $photo_urls = array( $item['photo'] );
            }

            if ( empty( $sku ) || empty( $photo_urls ) ) {
                continue;
            }

            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            // Set featured image from the first photo if not set.
            if ( ! get_post_thumbnail_id( $product_id ) && ! empty( $photo_urls[0] ) ) {
                $image_id = $this->upload_image_from_url( $photo_urls[0], $sku . '_main' );
                if ( $image_id ) {
                    $product->set_image_id( $image_id );
                }
            }

            // Set gallery images from remaining photos.
            if ( count( $photo_urls ) > 1 ) {
                $existing_gallery = $product->get_gallery_image_ids();
                if ( empty( $existing_gallery ) ) {
                    $gallery_ids = array();
                    for ( $i = 1; $i < count( $photo_urls ); $i++ ) {
                        $gallery_id = $this->upload_image_from_url( $photo_urls[ $i ], $sku . '_' . $i );
                        if ( $gallery_id ) {
                            $gallery_ids[] = $gallery_id;
                        }
                    }
                    if ( ! empty( $gallery_ids ) ) {
                        $product->set_gallery_image_ids( $gallery_ids );
                    }
                }
            }

            $product->save();
            $updated++;
        }

        Rolmar_Logger::info( "Photo sync done. Updated: {$updated} products.", 'import' );

        update_option( 'rolmar_last_photo_sync', current_time( 'mysql' ) );
        delete_transient( 'rolmar_sync_in_progress' );
    }

    /**
     * Update sync progress option.
     */
    private function update_progress( $status, $message, $total = 0, $processed = 0, $created = 0, $updated = 0, $errors = 0 ) {
        update_option( 'rolmar_sync_progress', array(
            'total'     => $total,
            'processed' => $processed,
            'created'   => $created,
            'updated'   => $updated,
            'errors'    => $errors,
            'status'    => $status,
            'message'   => $message,
        ) );
    }
}
