<?php
/**
 * Rolmar Admin Settings & UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolmar_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_rolmar_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_rolmar_manual_sync', array( $this, 'ajax_manual_sync' ) );
        add_action( 'wp_ajax_rolmar_sync_stock', array( $this, 'ajax_sync_stock' ) );
        add_action( 'wp_ajax_rolmar_sync_photos', array( $this, 'ajax_sync_photos' ) );
        add_action( 'wp_ajax_rolmar_get_sync_status', array( $this, 'ajax_get_sync_status' ) );
        add_action( 'wp_ajax_rolmar_load_category_tree', array( $this, 'ajax_load_category_tree' ) );
        add_action( 'wp_ajax_rolmar_debug_images', array( $this, 'ajax_debug_images' ) );
        add_action( 'wp_ajax_rolmar_debug_photos_api', array( $this, 'ajax_debug_photos_api' ) );
        add_action( 'wp_ajax_rolmar_debug_existing_products', array( $this, 'ajax_debug_existing_products' ) );
        add_action( 'wp_ajax_rolmar_run_diagnostics', array( $this, 'ajax_run_diagnostics' ) );
        add_action( 'wp_ajax_rolmar_test_download_image', array( $this, 'ajax_test_download_image' ) );
        add_action( 'wp_ajax_rolmar_proxy_photo', array( $this, 'ajax_proxy_photo' ) );
        add_action( 'wp_ajax_rolmar_debug_category_structure', array( $this, 'ajax_debug_category_structure' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Rolmar Integration', 'rolmar-integration' ),
            __( 'Rolmar', 'rolmar-integration' ),
            'manage_woocommerce',
            'rolmar-integration',
            array( $this, 'render_settings_page' ),
            'dashicons-update',
            56
        );

        add_submenu_page(
            'rolmar-integration',
            __( 'Ustawienia', 'rolmar-integration' ),
            __( 'Ustawienia', 'rolmar-integration' ),
            'manage_woocommerce',
            'rolmar-integration',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'rolmar-integration',
            __( 'Synchronizacja', 'rolmar-integration' ),
            __( 'Synchronizacja', 'rolmar-integration' ),
            'manage_woocommerce',
            'rolmar-sync',
            array( $this, 'render_sync_page' )
        );

        add_submenu_page(
            'rolmar-integration',
            __( 'Logi', 'rolmar-integration' ),
            __( 'Logi', 'rolmar-integration' ),
            'manage_woocommerce',
            'rolmar-logs',
            array( $this, 'render_logs_page' )
        );
    }

    public function register_settings() {
        // API Settings section.
        add_settings_section(
            'rolmar_api_section',
            __( 'Ustawienia API', 'rolmar-integration' ),
            function () {
                echo '<p>' . esc_html__( 'Konfiguracja połączenia z API Rolmar.', 'rolmar-integration' ) . '</p>';
            },
            'rolmar-integration'
        );

        $this->add_field( 'api_key', __( 'Klucz API', 'rolmar-integration' ), 'password', 'rolmar_api_section' );
        $this->add_field( 'api_environment', __( 'Środowisko', 'rolmar-integration' ), 'select', 'rolmar_api_section', array(
            'options' => array(
                'production' => __( 'Produkcja (v1)', 'rolmar-integration' ),
                'test'       => __( 'Test (v1_test)', 'rolmar-integration' ),
            ),
        ) );

        // Price Settings section.
        add_settings_section(
            'rolmar_price_section',
            __( 'Ustawienia cen', 'rolmar-integration' ),
            function () {
                echo '<p>' . esc_html__( 'Cena w sklepie = cena detaliczna z API * (1 - rabat/100).', 'rolmar-integration' ) . '</p>';
            },
            'rolmar-integration'
        );

        $this->add_field( 'discount_percent', __( 'Rabat (%)', 'rolmar-integration' ), 'number', 'rolmar_price_section', array(
            'min'  => 0,
            'max'  => 100,
            'step' => '0.01',
            'description' => __( 'Procent rabatu od ceny detalicznej. Np. 20 oznacza cenę sklepową = 80% ceny detalicznej.', 'rolmar-integration' ),
        ) );

        // Sync Settings section.
        add_settings_section(
            'rolmar_sync_section',
            __( 'Ustawienia synchronizacji', 'rolmar-integration' ),
            function () {
                echo '<p>' . esc_html__( 'Konfiguracja automatycznej synchronizacji produktów.', 'rolmar-integration' ) . '</p>';
            },
            'rolmar-integration'
        );

        $this->add_field( 'sync_frequency', __( 'Częstotliwość synchronizacji', 'rolmar-integration' ), 'select', 'rolmar_sync_section', array(
            'options' => array(
                'hourly'     => __( 'Co godzinę', 'rolmar-integration' ),
                'twicedaily' => __( 'Dwa razy dziennie', 'rolmar-integration' ),
                'daily'      => __( 'Raz dziennie', 'rolmar-integration' ),
            ),
        ) );

        $this->add_field( 'batch_size', __( 'Rozmiar paczki', 'rolmar-integration' ), 'number', 'rolmar_sync_section', array(
            'min'         => 10,
            'max'         => 200,
            'step'        => 10,
            'description' => __( 'Ile produktów przetwarzać na raz podczas importu.', 'rolmar-integration' ),
        ) );

        $this->add_field( 'import_images', __( 'Importuj zdjęcia', 'rolmar-integration' ), 'select', 'rolmar_sync_section', array(
            'options' => array(
                'yes' => __( 'Tak', 'rolmar-integration' ),
                'no'  => __( 'Nie', 'rolmar-integration' ),
            ),
        ) );

        $this->add_field( 'manage_stock', __( 'Zarządzaj stanami magazynowymi', 'rolmar-integration' ), 'select', 'rolmar_sync_section', array(
            'options' => array(
                'yes' => __( 'Tak', 'rolmar-integration' ),
                'no'  => __( 'Nie', 'rolmar-integration' ),
            ),
        ) );

        $this->add_field( 'default_language', __( 'Język API', 'rolmar-integration' ), 'select', 'rolmar_sync_section', array(
            'options' => array(
                'pl' => 'Polski',
                'en' => 'English',
            ),
        ) );

        // Category Mapping section.
        add_settings_section(
            'rolmar_category_section',
            __( 'Mapowanie kategorii', 'rolmar-integration' ),
            function () {
                echo '<p>' . esc_html__( 'Zaznacz kategorie API do importu i przypisz je do istniejących kategorii WooCommerce. Wtyczka NIE tworzy nowych kategorii — używa tylko tych, które już istnieją w sklepie.', 'rolmar-integration' ) . '</p>';
            },
            'rolmar-integration'
        );

        register_setting( 'rolmar_settings', 'rolmar_allowed_categories', array(
            'sanitize_callback' => array( $this, 'sanitize_allowed_categories' ),
        ) );

        register_setting( 'rolmar_settings', 'rolmar_category_mapping', array(
            'sanitize_callback' => array( $this, 'sanitize_category_mapping' ),
        ) );

        add_settings_field(
            'rolmar_allowed_categories',
            __( 'Kategorie do importu', 'rolmar-integration' ),
            array( $this, 'render_category_tree_field' ),
            'rolmar-integration',
            'rolmar_category_section'
        );
    }

    private function add_field( $id, $title, $type, $section, $extra = array() ) {
        $option_name = 'rolmar_' . $id;
        register_setting( 'rolmar_settings', $option_name, array(
            'sanitize_callback' => array( $this, 'sanitize_' . $type ),
        ) );

        add_settings_field(
            $option_name,
            $title,
            array( $this, 'render_field_' . $type ),
            'rolmar-integration',
            $section,
            array_merge( array( 'id' => $option_name ), $extra )
        );
    }

    // -- Sanitize callbacks --

    public function sanitize_password( $value ) {
        return sanitize_text_field( $value );
    }

    public function sanitize_text( $value ) {
        return sanitize_text_field( $value );
    }

    public function sanitize_number( $value ) {
        return floatval( $value );
    }

    public function sanitize_select( $value ) {
        return sanitize_text_field( $value );
    }

    // -- Field renderers --

    public function render_field_password( $args ) {
        $value = get_option( $args['id'], '' );
        printf(
            '<input type="password" id="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( $args['id'] ),
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        $this->render_description( $args );
    }

    public function render_field_text( $args ) {
        $value = get_option( $args['id'], '' );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $args['id'] ),
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        $this->render_description( $args );
    }

    public function render_field_number( $args ) {
        $value = get_option( $args['id'], 0 );
        $min   = isset( $args['min'] ) ? $args['min'] : 0;
        $max   = isset( $args['max'] ) ? $args['max'] : 999999;
        $step  = isset( $args['step'] ) ? $args['step'] : 1;
        printf(
            '<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
            esc_attr( $args['id'] ),
            esc_attr( $args['id'] ),
            esc_attr( $value ),
            esc_attr( $min ),
            esc_attr( $max ),
            esc_attr( $step )
        );
        $this->render_description( $args );
    }

    public function render_field_select( $args ) {
        $value   = get_option( $args['id'], '' );
        $options = isset( $args['options'] ) ? $args['options'] : array();
        printf( '<select id="%s" name="%s">', esc_attr( $args['id'] ), esc_attr( $args['id'] ) );
        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $value, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        $this->render_description( $args );
    }

    private function render_description( $args ) {
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public function sanitize_allowed_categories( $value ) {
        if ( empty( $value ) ) {
            return array();
        }
        if ( is_string( $value ) ) {
            $value = json_decode( stripslashes( $value ), true );
        }
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_map( 'sanitize_text_field', array_values( array_unique( $value ) ) );
    }

    public function sanitize_category_mapping( $value ) {
        if ( empty( $value ) ) {
            return array();
        }
        if ( is_string( $value ) ) {
            $value = json_decode( stripslashes( $value ), true );
        }
        if ( ! is_array( $value ) ) {
            return array();
        }
        // Sanitize: { "api/path": [wc_term_id, ...], ... }
        $clean = array();
        foreach ( $value as $api_path => $wc_ids ) {
            $api_path = sanitize_text_field( $api_path );
            if ( empty( $api_path ) ) {
                continue;
            }
            if ( ! is_array( $wc_ids ) ) {
                $wc_ids = array( $wc_ids );
            }
            $wc_ids = array_map( 'absint', $wc_ids );
            $wc_ids = array_filter( $wc_ids );
            if ( ! empty( $wc_ids ) ) {
                $clean[ $api_path ] = array_values( $wc_ids );
            }
        }
        return $clean;
    }

    public function render_category_tree_field() {
        $allowed    = get_option( 'rolmar_allowed_categories', array() );
        $mapping    = get_option( 'rolmar_category_mapping', array() );
        $cached_html = get_option( 'rolmar_category_tree_html', '' );
        ?>
        <div id="rolmar-category-tree-wrap">
            <p>
                <button type="button" id="rolmar-refresh-tree" class="button button-secondary">
                    <?php esc_html_e( 'Odśwież strukturę kategorii z API', 'rolmar-integration' ); ?>
                </button>
                <span class="spinner" id="rolmar-tree-spinner" style="float:none;"></span>
                <span id="rolmar-tree-status" class="rolmar-status-message"></span>
            </p>
            <p class="description">
                <?php esc_html_e( '1. Zaznacz kategorie API, które chcesz importować.', 'rolmar-integration' ); ?>
                <br />
                <?php esc_html_e( '2. Dla każdej zaznaczonej kategorii wybierz kategorię WooCommerce, do której mają trafić produkty.', 'rolmar-integration' ); ?>
                <br />
                <?php esc_html_e( 'Produkty z niezaznaczonych kategorii NIE będą importowane.', 'rolmar-integration' ); ?>
                <br />
                <strong><?php esc_html_e( 'Wybrano:', 'rolmar-integration' ); ?></strong>
                <span id="rolmar-category-count"></span>
            </p>
            <p>
                <button type="button" id="rolmar-expand-all" class="button button-small">
                    <?php esc_html_e( 'Rozwiń wszystkie', 'rolmar-integration' ); ?>
                </button>
                <button type="button" id="rolmar-collapse-all" class="button button-small">
                    <?php esc_html_e( 'Zwiń wszystkie', 'rolmar-integration' ); ?>
                </button>
            </p>
            <div id="rolmar-category-tree" class="rolmar-category-tree">
                <?php
                if ( ! empty( $cached_html ) ) {
                    echo $cached_html; // Already escaped during generation.
                } else {
                    echo '<p class="description">' . esc_html__( 'Kliknij "Odśwież strukturę kategorii z API", aby pobrać drzewo kategorii.', 'rolmar-integration' ) . '</p>';
                }
                ?>
            </div>
            <input type="hidden" id="rolmar_allowed_categories" name="rolmar_allowed_categories" value="<?php echo esc_attr( wp_json_encode( $allowed ) ); ?>" />
            <input type="hidden" id="rolmar_category_mapping" name="rolmar_category_mapping" value="<?php echo esc_attr( wp_json_encode( $mapping ) ); ?>" />
        </div>
        <?php
    }

    // -- Page renderers --

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap rolmar-admin">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'rolmar_settings' );
                do_settings_sections( 'rolmar-integration' );
                submit_button( __( 'Zapisz ustawienia', 'rolmar-integration' ) );
                ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Test połączenia', 'rolmar-integration' ); ?></h2>
            <p>
                <button type="button" id="rolmar-test-connection" class="button button-secondary">
                    <?php esc_html_e( 'Testuj połączenie z API', 'rolmar-integration' ); ?>
                </button>
                <span id="rolmar-test-result" class="rolmar-status-message"></span>
            </p>

            <hr />
            <h2><?php esc_html_e( 'Diagnostyka', 'rolmar-integration' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Kompleksowa diagnostyka: IP serwera, dostęp do API, serwer zdjęć, konfiguracja pluginu, WooCommerce.', 'rolmar-integration' ); ?>
            </p>
            <p>
                <button type="button" id="rolmar-run-diagnostics" class="button button-primary" style="font-size: 14px; padding: 4px 20px; height: auto;">
                    <?php esc_html_e( 'Uruchom diagnostyke', 'rolmar-integration' ); ?>
                </button>
            </p>
            <div id="rolmar-diagnostics-result" style="margin-top: 15px;"></div>

            <hr />
            <h2><?php esc_html_e( 'Debug obrazków', 'rolmar-integration' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Sprawdź jak wyglądają URLe obrazków z API i czy są dostępne.', 'rolmar-integration' ); ?>
            </p>
            <p>
                <button type="button" id="rolmar-debug-images" class="button button-secondary">
                    <?php esc_html_e( 'Testuj mainPhoto (getProducts)', 'rolmar-integration' ); ?>
                </button>
                <button type="button" id="rolmar-debug-photos-api" class="button button-primary">
                    <?php esc_html_e( 'Testuj getPhotos API ⭐', 'rolmar-integration' ); ?>
                </button>
                <button type="button" id="rolmar-debug-existing-products" class="button button-primary" style="background: #00a32a; border-color: #00a32a;">
                    <?php esc_html_e( '🎯 Testuj TWOJE produkty', 'rolmar-integration' ); ?>
                </button>
            </p>
            <div id="rolmar-debug-result" style="margin-top: 15px;"></div>

            <hr />
            <h2><?php esc_html_e( 'Test pobrania zdjęcia', 'rolmar-integration' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Pobierz jedno zdjęcie z API i sprawdź czy się pobiera. Wynik (link) wyślij technikowi Rolmar.', 'rolmar-integration' ); ?>
            </p>
            <p>
                <button type="button" id="rolmar-test-download-image" class="button button-primary" style="background: #d63638; border-color: #d63638; font-size: 14px; padding: 4px 20px; height: auto;">
                    <?php esc_html_e( 'Pobierz testowe zdjęcie', 'rolmar-integration' ); ?>
                </button>
            </p>
            <div id="rolmar-download-test-result" style="margin-top: 15px;"></div>
        </div>
        <?php
    }

    public function render_sync_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $last_product_sync = get_option( 'rolmar_last_product_sync', '' );
        $last_stock_sync   = get_option( 'rolmar_last_stock_sync', '' );
        $last_photo_sync   = get_option( 'rolmar_last_photo_sync', '' );
        $sync_in_progress  = get_transient( 'rolmar_sync_in_progress' );
        ?>
        <div class="wrap rolmar-admin">
            <h1><?php esc_html_e( 'Synchronizacja Rolmar', 'rolmar-integration' ); ?></h1>

            <div class="rolmar-sync-status-box">
                <h2><?php esc_html_e( 'Status synchronizacji', 'rolmar-integration' ); ?></h2>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Ostatni import produktów', 'rolmar-integration' ); ?></strong></td>
                            <td id="rolmar-last-product-sync"><?php echo $last_product_sync ? esc_html( $last_product_sync ) : esc_html__( 'Nigdy', 'rolmar-integration' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Ostatnia synchronizacja stanów', 'rolmar-integration' ); ?></strong></td>
                            <td id="rolmar-last-stock-sync"><?php echo $last_stock_sync ? esc_html( $last_stock_sync ) : esc_html__( 'Nigdy', 'rolmar-integration' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Ostatnia synchronizacja zdjęć', 'rolmar-integration' ); ?></strong></td>
                            <td id="rolmar-last-photo-sync"><?php echo $last_photo_sync ? esc_html( $last_photo_sync ) : esc_html__( 'Nigdy', 'rolmar-integration' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rolmar-sync-actions">
                <h2><?php esc_html_e( 'Ręczna synchronizacja', 'rolmar-integration' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Import produktów może potrwać dłużej ze względu na dużą liczbę produktów w katalogu Rolmar (~14 000).', 'rolmar-integration' ); ?></p>

                <p>
                    <button type="button" id="rolmar-sync-products" class="button button-primary" <?php echo $sync_in_progress ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Importuj produkty', 'rolmar-integration' ); ?>
                    </button>
                    <button type="button" id="rolmar-sync-stock" class="button button-secondary" <?php echo $sync_in_progress ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Synchronizuj stany magazynowe', 'rolmar-integration' ); ?>
                    </button>
                    <button type="button" id="rolmar-sync-photos" class="button button-secondary" <?php echo $sync_in_progress ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Synchronizuj zdjęcia', 'rolmar-integration' ); ?>
                    </button>
                </p>

                <hr />
                <h3><?php esc_html_e( 'Debug kategorii', 'rolmar-integration' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Pokaż strukturę marek i kategorii z API Rolmar (do debugowania).', 'rolmar-integration' ); ?></p>
                <p>
                    <button type="button" id="rolmar-debug-categories" class="button button-secondary">
                        <?php esc_html_e( 'Pokaż strukturę kategorii z API', 'rolmar-integration' ); ?>
                    </button>
                </p>
                <div id="rolmar-debug-categories-result"></div>

                <div id="rolmar-sync-progress" style="<?php echo $sync_in_progress ? '' : 'display:none;'; ?>">
                    <div class="rolmar-progress-bar">
                        <div class="rolmar-progress-fill" id="rolmar-progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="rolmar-sync-message" class="rolmar-status-message">
                        <?php echo $sync_in_progress ? esc_html__( 'Synchronizacja w toku...', 'rolmar-integration' ) : ''; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_logs_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $context = isset( $_GET['log_context'] ) ? sanitize_text_field( $_GET['log_context'] ) : 'import';
        $logs    = Rolmar_Logger::get_recent_logs( $context, 200 );
        ?>
        <div class="wrap rolmar-admin">
            <h1><?php esc_html_e( 'Logi Rolmar', 'rolmar-integration' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="rolmar-logs" />
                <label for="log_context"><?php esc_html_e( 'Kontekst:', 'rolmar-integration' ); ?></label>
                <select name="log_context" id="log_context" onchange="this.form.submit()">
                    <?php
                    $contexts = array( 'import', 'stock', 'api', 'cron', 'general' );
                    foreach ( $contexts as $ctx ) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr( $ctx ),
                            selected( $context, $ctx, false ),
                            esc_html( ucfirst( $ctx ) )
                        );
                    }
                    ?>
                </select>
            </form>

            <pre class="rolmar-log-viewer"><?php echo esc_html( $logs ); ?></pre>
        </div>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'rolmar' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'rolmar-admin',
            ROLMAR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ROLMAR_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'rolmar-admin',
            ROLMAR_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            ROLMAR_PLUGIN_VERSION,
            true
        );

        // Get existing WooCommerce product categories for mapping UI.
        $wc_categories = array();
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
        if ( ! is_wp_error( $terms ) ) {
            // Build flat list with indented names for hierarchy.
            $cat_hierarchy = array();
            foreach ( $terms as $term ) {
                $cat_hierarchy[ $term->term_id ] = array(
                    'id'     => $term->term_id,
                    'name'   => $term->name,
                    'parent' => $term->parent,
                    'slug'   => $term->slug,
                );
            }
            // Build display names with parent path.
            foreach ( $cat_hierarchy as $id => &$cat ) {
                $parts   = array( $cat['name'] );
                $current = $cat;
                while ( $current['parent'] && isset( $cat_hierarchy[ $current['parent'] ] ) ) {
                    $current = $cat_hierarchy[ $current['parent'] ];
                    array_unshift( $parts, $current['name'] );
                }
                $cat['display'] = implode( ' > ', $parts );
            }
            unset( $cat );
            // Sort by display name.
            usort( $cat_hierarchy, function ( $a, $b ) {
                return strcasecmp( $a['display'], $b['display'] );
            } );
            $wc_categories = array_values( $cat_hierarchy );
        }

        wp_localize_script( 'rolmar-admin', 'rolmarAdmin', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'rolmar_admin_nonce' ),
            'wcCategories'  => $wc_categories,
            'i18n'          => array(
                'testing'            => __( 'Testowanie...', 'rolmar-integration' ),
                'success'            => __( 'Połączenie udane!', 'rolmar-integration' ),
                'error'              => __( 'Błąd połączenia', 'rolmar-integration' ),
                'syncing'            => __( 'Synchronizacja w toku...', 'rolmar-integration' ),
                'syncDone'           => __( 'Synchronizacja zakończona!', 'rolmar-integration' ),
                'syncError'          => __( 'Błąd synchronizacji', 'rolmar-integration' ),
                'confirmSync'        => __( 'Czy na pewno chcesz rozpocząć import produktów? Może to potrwać dłuższy czas.', 'rolmar-integration' ),
                'loadingTree'        => __( 'Pobieranie struktury kategorii z API...', 'rolmar-integration' ),
                'treeLoaded'         => __( 'Struktura kategorii została załadowana.', 'rolmar-integration' ),
                'treeError'          => __( 'Błąd pobierania kategorii', 'rolmar-integration' ),
                'allCategories'      => __( 'Wszystkie kategorie (brak filtra)', 'rolmar-integration' ),
                'categorySelected'   => __( 'wybrana kategoria', 'rolmar-integration' ),
                'categoriesSelected' => __( 'wybranych kategorii', 'rolmar-integration' ),
            ),
        ) );
    }

    // -- AJAX handlers --

    public function ajax_test_connection() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'rolmar-integration' ) );
        }

        $client = new Rolmar_API_Client();
        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Połączenie z API Rolmar działa poprawnie.', 'rolmar-integration' ) );
    }

    public function ajax_manual_sync() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'rolmar-integration' ) );
        }

        if ( get_transient( 'rolmar_sync_in_progress' ) ) {
            wp_send_json_error( __( 'Synchronizacja już trwa. Poczekaj na jej zakończenie.', 'rolmar-integration' ) );
        }

        // Trigger background processing via cron.
        set_transient( 'rolmar_sync_in_progress', 'products', HOUR_IN_SECONDS );
        update_option( 'rolmar_sync_progress', array(
            'total'     => 0,
            'processed' => 0,
            'created'   => 0,
            'updated'   => 0,
            'errors'    => 0,
            'status'    => 'fetching',
            'message'   => __( 'Pobieranie produktów z API...', 'rolmar-integration' ),
        ) );

        // Schedule immediate processing.
        wp_schedule_single_event( time(), 'rolmar_run_product_import' );
        spawn_cron();

        wp_send_json_success( array(
            'message' => __( 'Import produktów został uruchomiony w tle.', 'rolmar-integration' ),
        ) );
    }

    public function ajax_sync_stock() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'rolmar-integration' ) );
        }

        if ( get_transient( 'rolmar_sync_in_progress' ) ) {
            wp_send_json_error( __( 'Synchronizacja już trwa.', 'rolmar-integration' ) );
        }

        set_transient( 'rolmar_sync_in_progress', 'stock', HOUR_IN_SECONDS );

        wp_schedule_single_event( time(), 'rolmar_run_stock_sync' );
        spawn_cron();

        wp_send_json_success( array(
            'message' => __( 'Synchronizacja stanów magazynowych uruchomiona w tle.', 'rolmar-integration' ),
        ) );
    }

    public function ajax_sync_photos() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'rolmar-integration' ) );
        }

        if ( get_transient( 'rolmar_sync_in_progress' ) ) {
            wp_send_json_error( __( 'Synchronizacja już trwa.', 'rolmar-integration' ) );
        }

        set_transient( 'rolmar_sync_in_progress', 'photos', HOUR_IN_SECONDS );

        wp_schedule_single_event( time(), 'rolmar_run_photo_sync' );
        spawn_cron();

        wp_send_json_success( array(
            'message' => __( 'Synchronizacja zdjęć uruchomiona w tle.', 'rolmar-integration' ),
        ) );
    }

    public function ajax_get_sync_status() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        $progress      = get_option( 'rolmar_sync_progress', array() );
        $in_progress   = get_transient( 'rolmar_sync_in_progress' );

        wp_send_json_success( array(
            'in_progress' => ! empty( $in_progress ),
            'progress'    => $progress,
        ) );
    }

    public function ajax_load_category_tree() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'rolmar-integration' ) );
        }

        // Clear old cached HTML to force regeneration.
        delete_option( 'rolmar_category_tree_html' );

        // Increase limits for large product catalog.
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $client   = new Rolmar_API_Client();
        $products = $client->get_products();

        if ( is_wp_error( $products ) ) {
            wp_send_json_error( $products->get_error_message() );
        }

        if ( ! is_array( $products ) ) {
            wp_send_json_error( __( 'Nieprawidłowa odpowiedź z API.', 'rolmar-integration' ) );
        }

        // Extract unique category paths and build tree structure.
        $tree = array();
        $sample_paths = array(); // For debugging
        $path_count = 0;

        foreach ( $products as $product ) {
            if ( empty( $product['categories'] ) || ! is_array( $product['categories'] ) ) {
                continue;
            }
            foreach ( $product['categories'] as $path ) {
                // Save first 5 paths for debugging
                if ( $path_count < 5 ) {
                    $sample_paths[] = $path;
                    $path_count++;
                }

                $parts = array_map( 'trim', explode( '/', $path ) );
                $parts = array_filter( $parts );

                // Debug first path
                if ( $path_count === 1 ) {
                    Rolmar_Logger::info( 'DEBUG Path: ' . $path, 'api' );
                    Rolmar_Logger::info( 'DEBUG Parts count: ' . count( $parts ), 'api' );
                    Rolmar_Logger::info( 'DEBUG Parts: ' . wp_json_encode( $parts ), 'api' );
                }

                $ref   = &$tree;
                foreach ( $parts as $part ) {
                    if ( ! isset( $ref[ $part ] ) ) {
                        $ref[ $part ] = array();
                    }
                    $ref = &$ref[ $part ];
                }
                unset( $ref );
            }
        }

        // Log sample paths and tree structure for debugging
        Rolmar_Logger::info( 'Sample category paths from API: ' . wp_json_encode( $sample_paths ), 'api' );
        Rolmar_Logger::info( 'Tree root level count: ' . count( $tree ), 'api' );

        $root_keys = array_keys( $tree );
        Rolmar_Logger::info( 'First 3 root categories: ' . wp_json_encode( array_slice( $root_keys, 0, 3 ) ), 'api' );

        // Debug: check if first root has children
        if ( ! empty( $root_keys[0] ) && isset( $tree[ $root_keys[0] ] ) ) {
            $first_root_children_count = count( $tree[ $root_keys[0] ] );
            Rolmar_Logger::info( 'First root "' . $root_keys[0] . '" has ' . $first_root_children_count . ' children', 'api' );
            if ( $first_root_children_count > 0 ) {
                Rolmar_Logger::info( 'First root children: ' . wp_json_encode( array_slice( array_keys( $tree[ $root_keys[0] ] ), 0, 3 ) ), 'api' );
            }
        }

        // Sort tree alphabetically at each level.
        $this->sort_tree_recursive( $tree );

        // Generate HTML.
        $html = $this->render_category_tree_html( $tree );

        // Cache the HTML.
        update_option( 'rolmar_category_tree_html', $html, false );

        wp_send_json_success( array(
            'html' => $html,
        ) );
    }

    /**
     * Debug AJAX: show raw brand + categories structure from API.
     */
    public function ajax_debug_category_structure() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Brak uprawnień.' );
        }

        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $client   = new Rolmar_API_Client();
        $products = $client->get_products();

        if ( is_wp_error( $products ) ) {
            wp_send_json_error( $products->get_error_message() );
        }

        if ( ! is_array( $products ) ) {
            wp_send_json_error( 'Nieprawidłowa odpowiedź z API.' );
        }

        // Collect brands, categories, and sample products.
        $brands = array();
        $category_paths = array();
        $samples = array();
        $sample_count = 0;

        foreach ( $products as $p ) {
            $brand = isset( $p['brand'] ) ? $p['brand'] : '(brak)';
            if ( ! isset( $brands[ $brand ] ) ) {
                $brands[ $brand ] = 0;
            }
            $brands[ $brand ]++;

            if ( ! empty( $p['categories'] ) && is_array( $p['categories'] ) ) {
                foreach ( $p['categories'] as $cat ) {
                    if ( ! isset( $category_paths[ $cat ] ) ) {
                        $category_paths[ $cat ] = 0;
                    }
                    $category_paths[ $cat ]++;
                }
            }

            // Collect first 10 products as samples.
            if ( $sample_count < 10 ) {
                $samples[] = array(
                    'sku'        => isset( $p['productIndex'] ) ? $p['productIndex'] : '?',
                    'name'       => isset( $p['name'] ) ? mb_substr( $p['name'], 0, 60 ) : '?',
                    'brand'      => $brand,
                    'categories' => isset( $p['categories'] ) ? $p['categories'] : array(),
                );
                $sample_count++;
            }
        }

        arsort( $brands );
        arsort( $category_paths );

        // Build brand -> categories tree.
        $brand_tree = array();
        foreach ( $products as $p ) {
            $brand = isset( $p['brand'] ) ? $p['brand'] : '(brak)';
            if ( ! isset( $brand_tree[ $brand ] ) ) {
                $brand_tree[ $brand ] = array();
            }
            if ( ! empty( $p['categories'] ) && is_array( $p['categories'] ) ) {
                foreach ( $p['categories'] as $cat ) {
                    if ( ! isset( $brand_tree[ $brand ][ $cat ] ) ) {
                        $brand_tree[ $brand ][ $cat ] = 0;
                    }
                    $brand_tree[ $brand ][ $cat ]++;
                }
            }
        }

        wp_send_json_success( array(
            'total_products'   => count( $products ),
            'brands'           => $brands,
            'category_paths'   => array_slice( $category_paths, 0, 50, true ),
            'brand_tree'       => $brand_tree,
            'samples'          => $samples,
        ) );
    }

    private function sort_tree_recursive( &$tree ) {
        ksort( $tree, SORT_LOCALE_STRING );
        foreach ( $tree as &$children ) {
            if ( ! empty( $children ) ) {
                $this->sort_tree_recursive( $children );
            }
        }
    }

    private function render_category_tree_html( $tree, $parent_path = '' ) {
        if ( empty( $tree ) ) {
            return '';
        }

        $html = '<ul class="rolmar-tree-list">';
        foreach ( $tree as $name => $children ) {
            $current_path = $parent_path ? $parent_path . '/' . $name : $name;
            $escaped_path = esc_attr( $current_path );
            $escaped_name = esc_html( $name );
            $has_children = ! empty( $children );

            $html .= '<li class="rolmar-tree-node">';
            if ( $has_children ) {
                $html .= '<span class="rolmar-tree-toggle dashicons dashicons-arrow-right-alt2"></span>';
            } else {
                $html .= '<span class="rolmar-tree-toggle-spacer"></span>';
            }
            $html .= '<label>';
            $html .= '<input type="checkbox" class="rolmar-cat-checkbox" data-path="' . $escaped_path . '" /> ';
            $html .= $escaped_name;
            $html .= '</label>';
            // Mapping container — JS builds the tag-picker widget inside.
            $html .= '<div class="rolmar-cat-mapping" data-path="' . $escaped_path . '" style="display:none;"></div>';

            if ( $has_children ) {
                $html .= $this->render_category_tree_html( $children, $current_path );
            }

            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * AJAX handler to debug image URLs from API.
     */
    public function ajax_debug_images() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Brak uprawnień.' ) );
        }

        $api = new Rolmar_API_Client();
        $products = $api->get_products(); // Get all products.

        if ( is_wp_error( $products ) ) {
            wp_send_json_error( array(
                'message' => 'Błąd API: ' . $products->get_error_message()
            ) );
        }

        if ( ! is_array( $products ) ) {
            wp_send_json_error( array( 'message' => 'API zwróciło nieprawidłowe dane.' ) );
        }

        // Take only first 5 products.
        $products = array_slice( $products, 0, 5 );
        $results = array();

        foreach ( $products as $product ) {
            $sku = isset( $product['sku'] ) ? $product['sku'] : 'N/A';
            $name = isset( $product['name'] ) ? $product['name'] : 'N/A';
            $index = isset( $product['productIndex'] ) ? $product['productIndex'] : 'N/A';
            $main_photo = isset( $product['mainPhoto'] ) ? $product['mainPhoto'] : '';

            // Check for alternative image fields.
            $alt_photos = array();
            if ( isset( $product['photos'] ) && is_array( $product['photos'] ) ) {
                $alt_photos = $product['photos'];
            }
            if ( isset( $product['photo'] ) ) {
                $alt_photos[] = $product['photo'];
            }
            if ( isset( $product['image'] ) ) {
                $alt_photos[] = $product['image'];
            }

            // Prepare URL variants to test. Keep c= param intact (e.g. "c=-bth..").
            $original_url = trim( $main_photo );
            $cleaned_url = preg_replace( '/[?&]c=[^&]*/', '', $original_url );
            $cleaned_url = rtrim( $cleaned_url, '?&' );
            $cleaned_url = preg_replace( '/\?&/', '?', $cleaned_url );

            $api_key = get_option( 'rolmar_api_key', '' );

            // Test URL accessibility - try original first, then cleaned, with auth headers.
            $status = 'unknown';
            $http_code = 0;
            $error_msg = '';
            $working_url = '';

            $test_urls = array( $original_url );
            if ( $cleaned_url !== $original_url ) {
                $test_urls[] = $cleaned_url;
            }

            if ( ! empty( $original_url ) ) {
                foreach ( $test_urls as $test_url ) {
                    if ( empty( $test_url ) || ! filter_var( $test_url, FILTER_VALIDATE_URL ) ) {
                        continue;
                    }
                    $test_response = wp_remote_head( $test_url, array(
                        'timeout' => 10,
                        'headers' => array(
                            'wsKey'   => $api_key,
                            'Referer' => 'https://www.rol-mar.com.pl/',
                        ),
                    ) );
                    if ( ! is_wp_error( $test_response ) ) {
                        $http_code = wp_remote_retrieve_response_code( $test_response );
                        if ( 200 === $http_code ) {
                            $status = 'OK';
                            $working_url = $test_url;
                            break;
                        }
                        $status = 'FAIL';
                        $error_msg = "HTTP {$http_code} for: {$test_url}";
                    } else {
                        $status = 'ERROR';
                        $error_msg = $test_response->get_error_message() . " for: {$test_url}";
                    }
                }
            } else {
                $status = 'EMPTY';
                $error_msg = 'Brak URL w API';
            }

            $results[] = array(
                'index' => $index,
                'name' => $name,
                'sku' => $sku,
                'original_url' => $original_url,
                'cleaned_url' => $cleaned_url,
                'http_code' => $http_code,
                'status' => $status,
                'error' => $error_msg,
                'alt_photos' => $alt_photos,
                'all_fields' => array_keys( $product ), // Show all available fields for debugging.
            );
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * AJAX handler to test getPhotos API endpoint.
     */
    public function ajax_debug_photos_api() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Brak uprawnień.' ) );
        }

        $api = new Rolmar_API_Client();
        $photos = $api->get_photos();

        if ( is_wp_error( $photos ) ) {
            wp_send_json_error( array(
                'message' => 'Błąd API getPhotos: ' . $photos->get_error_message()
            ) );
        }

        if ( ! is_array( $photos ) ) {
            wp_send_json_error( array( 'message' => 'getPhotos zwróciło nieprawidłowe dane (nie array).' ) );
        }

        // Take first 5 entries.
        $total_count = count( $photos );
        $photos_sample = array_slice( $photos, 0, 5 );
        $results = array();

        foreach ( $photos_sample as $item ) {
            // Extract product identifier.
            $identifier = 'N/A';
            if ( isset( $item['Index'] ) ) {
                $identifier = $item['Index'];
            } elseif ( isset( $item['productIndex'] ) ) {
                $identifier = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $identifier = $item['index'];
            } elseif ( isset( $item['sku'] ) ) {
                $identifier = $item['sku'];
            }

            // Extract photo URLs - API returns 'Photo' array field.
            $photo_urls = array();
            if ( isset( $item['Photo'] ) && is_array( $item['Photo'] ) ) {
                $photo_urls = $item['Photo'];
            } elseif ( isset( $item['Photo'] ) && ! empty( $item['Photo'] ) ) {
                $photo_urls = array( $item['Photo'] );
            } elseif ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                $photo_urls = array( $item['url'] );
            } elseif ( isset( $item['photos'] ) && is_array( $item['photos'] ) ) {
                $photo_urls = $item['photos'];
            } elseif ( isset( $item['images'] ) && is_array( $item['images'] ) ) {
                $photo_urls = $item['images'];
            } elseif ( isset( $item['photo'] ) ) {
                $photo_urls = array( $item['photo'] );
            } elseif ( isset( $item['image'] ) ) {
                $photo_urls = array( $item['image'] );
            }

            // Check if product exists in WooCommerce.
            $product_id = wc_get_product_id_by_sku( $identifier );
            $wc_status = $product_id ? 'Znaleziony (ID: ' . $product_id . ')' : 'NIE ZNALEZIONY';

            // Test first photo URL - try original (with all params) and cleaned, with auth headers.
            $first_photo_status = 'N/A';
            $first_photo_http = 0;
            $tested_urls_info = array();

            if ( ! empty( $photo_urls[0] ) ) {
                $raw_url = trim( $photo_urls[0] );
                $clean_url = $this->clean_photo_url( $photo_urls[0] );
                $api_key = get_option( 'rolmar_api_key', '' );

                $test_variants = array( $raw_url );
                if ( $clean_url !== $raw_url ) {
                    $test_variants[] = $clean_url;
                }

                foreach ( $test_variants as $variant_url ) {
                    if ( empty( $variant_url ) || ! filter_var( $variant_url, FILTER_VALIDATE_URL ) ) {
                        continue;
                    }
                    // Test with auth headers (no Referer — photo server returns 404 when Referer is present).
                    $test_response = wp_remote_head( $variant_url, array(
                        'timeout'   => 10,
                        'sslverify' => false,
                        'headers'   => array(
                            'wsKey' => $api_key,
                        ),
                    ) );
                    $variant_http = 0;
                    $variant_status = 'ERROR';
                    if ( ! is_wp_error( $test_response ) ) {
                        $variant_http = wp_remote_retrieve_response_code( $test_response );
                        $variant_status = ( 200 === $variant_http ) ? 'OK' : "HTTP {$variant_http}";
                    } else {
                        $variant_status = 'ERROR: ' . $test_response->get_error_message();
                    }
                    $tested_urls_info[] = array(
                        'url' => $variant_url,
                        'status' => $variant_status,
                        'http' => $variant_http,
                    );
                    if ( 200 === $variant_http ) {
                        $first_photo_http = $variant_http;
                        $first_photo_status = 'OK';
                        break;
                    }
                    $first_photo_http = $variant_http;
                    $first_photo_status = $variant_status;
                }
            } else {
                $first_photo_status = 'BRAK URL';
            }

            $results[] = array(
                'identifier' => $identifier,
                'photo_count' => count( $photo_urls ),
                'photo_urls' => $photo_urls,
                'wc_status' => $wc_status,
                'wc_product_id' => $product_id,
                'first_photo_status' => $first_photo_status,
                'first_photo_http' => $first_photo_http,
                'tested_urls' => $tested_urls_info,
                'raw_data' => $item, // Full item for debugging.
            );
        }

        wp_send_json_success( array(
            'total_entries' => $total_count,
            'results' => $results
        ) );
    }

    /**
     * AJAX handler to test getPhotos for existing WooCommerce products.
     */
    public function ajax_debug_existing_products() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Brak uprawnień.' ) );
        }

        // Get first 15 WooCommerce products with SKUs.
        $args = array(
            'limit' => 15,
            'status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $wc_products = wc_get_products( $args );

        if ( empty( $wc_products ) ) {
            wp_send_json_error( array( 'message' => 'Brak produktów w WooCommerce.' ) );
        }

        // Get ALL photos from getPhotos API.
        $api = new Rolmar_API_Client();
        $all_photos = $api->get_photos();

        if ( is_wp_error( $all_photos ) ) {
            wp_send_json_error( array(
                'message' => 'Błąd API getPhotos: ' . $all_photos->get_error_message()
            ) );
        }

        if ( ! is_array( $all_photos ) ) {
            wp_send_json_error( array( 'message' => 'getPhotos zwróciło nieprawidłowe dane.' ) );
        }

        // Build index: SKU/index => photos.
        $photo_index = array();
        foreach ( $all_photos as $item ) {
            $identifier = null;
            if ( isset( $item['Index'] ) ) {
                $identifier = $item['Index'];
            } elseif ( isset( $item['productIndex'] ) ) {
                $identifier = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $identifier = $item['index'];
            } elseif ( isset( $item['sku'] ) ) {
                $identifier = $item['sku'];
            }

            if ( $identifier ) {
                // Extract photos - API returns 'Photo' array field.
                $photo_urls = array();
                if ( isset( $item['Photo'] ) && is_array( $item['Photo'] ) ) {
                    $photo_urls = $item['Photo'];
                } elseif ( isset( $item['Photo'] ) && ! empty( $item['Photo'] ) ) {
                    $photo_urls = array( $item['Photo'] );
                } elseif ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                    $photo_urls = array( $item['url'] );
                } elseif ( isset( $item['photos'] ) && is_array( $item['photos'] ) ) {
                    $photo_urls = $item['photos'];
                } elseif ( isset( $item['images'] ) && is_array( $item['images'] ) ) {
                    $photo_urls = $item['images'];
                } elseif ( isset( $item['photo'] ) ) {
                    $photo_urls = array( $item['photo'] );
                } elseif ( isset( $item['image'] ) ) {
                    $photo_urls = array( $item['image'] );
                }

                $photo_index[ $identifier ] = array(
                    'urls' => $photo_urls,
                    'raw' => $item,
                );
            }
        }

        // Check each WooCommerce product.
        $results = array();
        foreach ( $wc_products as $product ) {
            $sku = $product->get_sku();
            $product_id = $product->get_id();
            $product_name = $product->get_name();

            if ( empty( $sku ) ) {
                $results[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'sku' => 'BRAK SKU',
                    'api_status' => '⚠️ Brak SKU',
                    'photo_count' => 0,
                    'photo_urls' => array(),
                    'first_photo_status' => 'N/A',
                    'first_photo_http' => 0,
                    'has_wc_image' => $product->get_image_id() ? 'TAK ✅' : 'NIE ❌',
                );
                continue;
            }

            // Check if getPhotos has this SKU.
            $api_status = 'NIE ZNALEZIONO ❌';
            $photo_urls = array();
            $first_photo_status = 'N/A';
            $first_photo_http = 0;

            if ( isset( $photo_index[ $sku ] ) ) {
                $photo_data = $photo_index[ $sku ];
                $photo_urls = $photo_data['urls'];

                // Clean URLs before testing.
                $photo_urls = array_map( array( $this, 'clean_photo_url' ), $photo_urls );

                if ( empty( $photo_urls ) ) {
                    $api_status = 'Znaleziono, ale BRAK ZDJĘĆ 🟠';
                } else {
                    $api_status = 'Znaleziono ✅';

                    // Test first photo URL.
                    $test_response = wp_remote_head( $photo_urls[0], array(
                        'timeout' => 10,
                        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ) );

                    if ( ! is_wp_error( $test_response ) ) {
                        $first_photo_http = wp_remote_retrieve_response_code( $test_response );
                        $first_photo_status = ( $first_photo_http === 200 ) ? 'OK ✅' : 'FAIL ❌';
                    } else {
                        $first_photo_status = 'ERROR: ' . $test_response->get_error_message();
                    }
                }
            }

            $results[] = array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'sku' => $sku,
                'api_status' => $api_status,
                'photo_count' => count( $photo_urls ),
                'photo_urls' => $photo_urls,
                'first_photo_status' => $first_photo_status,
                'first_photo_http' => $first_photo_http,
                'has_wc_image' => $product->get_image_id() ? 'TAK ✅' : 'NIE ❌',
                'raw_api_data' => isset( $photo_index[ $sku ] ) ? $photo_index[ $sku ]['raw'] : null,
            );
        }

        wp_send_json_success( array(
            'total_api_photos' => count( $all_photos ),
            'total_wc_products' => count( $wc_products ),
            'results' => $results
        ) );
    }

    /**
     * AJAX handler for comprehensive diagnostics.
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Brak uprawnien.' ) );
        }

        @set_time_limit( 120 );

        // Ensure file.php is loaded for download_url().
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        try {
            $this->run_diagnostics_checks();
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => 'Blad diagnostyki: ' . $e->getMessage() ) );
        } catch ( \Error $e ) {
            wp_send_json_error( array( 'message' => 'Blad krytyczny: ' . $e->getMessage() . ' w ' . $e->getFile() . ':' . $e->getLine() ) );
        }
    }

    /**
     * Run all diagnostic checks.
     */
    /**
     * AJAX handler: test download of a single image from getPhotos API.
     * Runs from the shop server so the IP matches Cloudflare whitelist.
     */
    public function ajax_test_download_image() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Brak uprawnien.' ) );
        }

        @set_time_limit( 90 );

        // Try to use cached photo URL from last test/diagnostics to avoid slow getPhotos call.
        $test_url   = get_transient( 'rolmar_test_photo_url' );
        $test_sku   = get_transient( 'rolmar_test_photo_sku' );
        $total_entries = '(z cache)';
        $api_time_ms = 0;

        if ( empty( $test_url ) ) {
            // No cached URL — call API with shorter timeout for better UX.
            $api_start = microtime( true );
            $api = new Rolmar_API_Client();
            $photos = $api->get_photos();
            $api_time_ms = round( ( microtime( true ) - $api_start ) * 1000 );

            if ( is_wp_error( $photos ) ) {
                wp_send_json_error( array( 'message' => 'Blad API getPhotos (' . $api_time_ms . 'ms): ' . $photos->get_error_message() ) );
            }

            if ( ! is_array( $photos ) || empty( $photos ) ) {
                wp_send_json_error( array( 'message' => 'API zwrocilo pusta odpowiedz (' . $api_time_ms . 'ms).' ) );
            }

            $total_entries = count( $photos );
            $test_url = '';
            $test_sku = '';

            foreach ( $photos as $item ) {
                $sku = '';
                if ( isset( $item['Index'] ) ) {
                    $sku = $item['Index'];
                } elseif ( isset( $item['productIndex'] ) ) {
                    $sku = $item['productIndex'];
                } elseif ( isset( $item['index'] ) ) {
                    $sku = $item['index'];
                }

                $url = '';
                if ( isset( $item['Photo'] ) && is_array( $item['Photo'] ) && ! empty( $item['Photo'] ) ) {
                    $url = $item['Photo'][0];
                } elseif ( isset( $item['Photo'] ) && is_string( $item['Photo'] ) && ! empty( $item['Photo'] ) ) {
                    $url = $item['Photo'];
                } elseif ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                    $url = $item['url'];
                } elseif ( isset( $item['photo'] ) && ! empty( $item['photo'] ) ) {
                    $url = $item['photo'];
                }

                if ( ! empty( $url ) && ! empty( $sku ) ) {
                    $test_url = $url;
                    $test_sku = $sku;
                    break;
                }
            }

            if ( empty( $test_url ) ) {
                wp_send_json_error( array(
                    'message'       => 'Zaden produkt w API nie ma URL-a zdjecia!',
                    'total_entries' => $total_entries,
                    'api_time_ms'   => $api_time_ms,
                ) );
            }

            // Cache for next test (1 hour) to avoid slow API call.
            set_transient( 'rolmar_test_photo_url', $test_url, HOUR_IN_SECONDS );
            set_transient( 'rolmar_test_photo_sku', $test_sku, HOUR_IN_SECONDS );
        }

        $original_url = trim( $test_url );
        $api_key      = get_option( 'rolmar_api_key', '' );
        $site_url     = get_site_url();

        // ---- Build URL variants ----
        $base_url = strtok( $original_url, '?' );
        $d_param  = '';
        if ( preg_match( '/[?&](d=[^&]+)/', $original_url, $d_match ) ) {
            $d_param = $d_match[1];
        }
        $d_prefix = $d_param ? '?' . $d_param . '&' : '?';

        $url_variants = array();
        $url_variants['Oryginalny (c=-bth..)'] = $original_url;
        $url_variants['Bez parametru c=']      = $base_url . ( $d_param ? '?' . $d_param : '' );
        $url_variants['Sam plik']              = $base_url;

        // Different c= dimension values — test which gives full-size image.
        $c_sizes = array(
            'c=-bth800.800',
            'c=-bth1200.1200',
            'c=-bth1920.1920',
            'c=-bth200.200',
            'c=-bth',
            'c=-bth0.0',
            'c=bth800.800',
            'c=-bth800x800',
        );
        foreach ( $c_sizes as $c_val ) {
            $url_variants[ $c_val ] = $base_url . $d_prefix . $c_val;
        }

        // ---- Headers: only working combo (wsKey, NO Referer) ----
        // Matrix test showed Referer causes 404 on photo server.
        $header_combos = array(
            'wsKey (bez Referer)' => array( 'wsKey' => $api_key ),
        );

        // ---- Run full matrix: URL × Headers ----
        $attempts    = array();
        $success     = false;
        $success_url = '';

        foreach ( $url_variants as $url_label => $url ) {
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $attempts[] = array(
                    'url_label'  => $url_label,
                    'hdr_label'  => '-',
                    'url'        => $url,
                    'error'      => 'Nieprawidlowy URL',
                );
                continue;
            }

            foreach ( $header_combos as $h_label => $headers ) {
                $attempt = array(
                    'url_label'       => $url_label,
                    'hdr_label'       => $h_label,
                    'url'             => $url,
                    'http_code'       => 0,
                    'size_bytes'      => 0,
                    'size_kb'         => 0,
                    'is_image'        => false,
                    'content_type'    => '',
                    'error'           => '',
                    'time_ms'         => 0,
                    'dimensions'      => '',
                    'cf_cache_status' => '',
                    'cf_ray'          => '',
                );

                $start    = microtime( true );
                $response = wp_remote_get( $url, array(
                    'timeout'   => 10,
                    'sslverify' => false,
                    'headers'   => $headers,
                ) );
                $attempt['time_ms'] = round( ( microtime( true ) - $start ) * 1000 );

                if ( is_wp_error( $response ) ) {
                    $attempt['error'] = $response->get_error_message();
                    $attempts[]       = $attempt;
                    continue;
                }

                $attempt['http_code']       = wp_remote_retrieve_response_code( $response );
                $attempt['content_type']    = wp_remote_retrieve_header( $response, 'content-type' );
                $attempt['cf_cache_status'] = wp_remote_retrieve_header( $response, 'cf-cache-status' );
                $attempt['cf_ray']          = wp_remote_retrieve_header( $response, 'cf-ray' );
                $body                       = wp_remote_retrieve_body( $response );
                $attempt['size_bytes']      = strlen( $body );
                $attempt['size_kb']         = round( strlen( $body ) / 1024, 1 );

                if ( 200 === (int) $attempt['http_code'] && ! empty( $body ) ) {
                    if ( strpos( $attempt['content_type'], 'image/' ) !== false ) {
                        $attempt['is_image'] = true;
                    } elseif ( function_exists( 'imagecreatefromstring' ) ) {
                        $img = @imagecreatefromstring( $body );
                        if ( false !== $img ) {
                            $attempt['is_image']   = true;
                            $attempt['dimensions'] = imagesx( $img ) . 'x' . imagesy( $img ) . ' px';
                            imagedestroy( $img );
                        }
                    }

                    if ( $attempt['is_image'] && ! $success ) {
                        $success     = true;
                        $success_url = $url_label . ' + ' . $h_label;
                    }
                } else {
                    if ( $attempt['size_bytes'] > 0 && $attempt['size_bytes'] < 1000 ) {
                        $preview = substr( $body, 0, 200 );
                        if ( ! preg_match( '/[\x00-\x08\x0E-\x1F]/', $preview ) ) {
                            $attempt['error'] = 'Tresc: ' . $preview;
                        }
                    }
                }

                $attempts[] = $attempt;
            }
        }

        $timestamp = current_time( 'Y-m-d H:i:s' );

        Rolmar_Logger::info( "Test download matrix: SKU={$test_sku}, URL={$original_url}, success=" . ( $success ? 'YES' : 'NO' ) . ', tests=' . count( $attempts ), 'import' );

        wp_send_json_success( array(
            'sku'           => $test_sku,
            'original_url'  => $original_url,
            'success'       => $success,
            'success_combo' => $success_url,
            'attempts'      => $attempts,
            'timestamp'     => $timestamp,
            'total_entries' => $total_entries,
            'api_time_ms'   => $api_time_ms,
            'server_ip'     => isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : 'nieznane',
        ) );
    }

    /**
     * Proxy a photo URL through the server so the browser can download it.
     * The photo server requires wsKey header which the browser cannot send.
     */
    public function ajax_proxy_photo() {
        check_ajax_referer( 'rolmar_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Brak uprawnien.' );
        }

        $url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';
        if ( empty( $url ) || strpos( $url, 'photo2.rol-mar.com.pl' ) === false ) {
            wp_die( 'Nieprawidlowy URL.' );
        }

        $api_key  = get_option( 'rolmar_api_key', '' );
        $response = wp_remote_get( $url, array(
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => array( 'wsKey' => $api_key ),
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            wp_die( 'Blad pobierania: HTTP ' . wp_remote_retrieve_response_code( $response ) );
        }

        $body         = wp_remote_retrieve_body( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $filename     = basename( wp_parse_url( $url, PHP_URL_PATH ) );

        header( 'Content-Type: ' . ( $content_type ?: 'application/octet-stream' ) );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $body ) );
        echo $body;
        exit;
    }

    private function run_diagnostics_checks() {
        $checks = array();
        $photos_data = null;

        // =====================================================================
        // SEKCJA 1: SERWER I SRODOWISKO
        // =====================================================================

        // 1. Server outgoing IP (try multiple services for reliability).
        $server_ip = '';
        $ip_services = array(
            'https://api.ipify.org',
            'https://ifconfig.me/ip',
            'https://icanhazip.com',
        );
        $ip_service_used = '';
        foreach ( $ip_services as $ip_service ) {
            $ip_response = wp_remote_get( $ip_service, array( 'timeout' => 5 ) );
            if ( ! is_wp_error( $ip_response ) && 200 === wp_remote_retrieve_response_code( $ip_response ) ) {
                $server_ip = trim( wp_remote_retrieve_body( $ip_response ) );
                $ip_service_used = $ip_service;
                break;
            }
        }
        if ( ! empty( $server_ip ) ) {
            $checks[] = array(
                'name'   => 'IP wychodzace serwera',
                'status' => 'info',
                'value'  => $server_ip,
                'hint'   => 'To IP musi byc na whiteliscie u Rolmar. Zrodlo: ' . $ip_service_used,
            );
        } else {
            $checks[] = array(
                'name'   => 'IP wychodzace serwera',
                'status' => 'error',
                'value'  => 'Nie udalo sie pobrac z zadnego serwisu',
                'hint'   => 'Hosting blokuje polaczenia wychodzace?',
            );
        }

        // 2. PHP version & extensions (including image processing).
        $php_version = phpversion();
        $all_extensions = array(
            'curl'      => 'Pobieranie plikow z URL',
            'json'      => 'Parsowanie odpowiedzi API',
            'mbstring'  => 'Obsluga polskich znakow',
            'gd'        => 'Przetwarzanie obrazkow (skalowanie)',
            'imagick'   => 'Przetwarzanie obrazkow (alternatywa)',
            'openssl'   => 'Polaczenia HTTPS/SSL',
            'fileinfo'  => 'Rozpoznawanie typu MIME plikow',
        );
        $loaded = array();
        $missing = array();
        foreach ( $all_extensions as $ext => $desc ) {
            if ( extension_loaded( $ext ) ) {
                $loaded[] = $ext;
            } else {
                $missing[] = $ext . ' (' . $desc . ')';
            }
        }
        $has_image_lib = extension_loaded( 'gd' ) || extension_loaded( 'imagick' );
        $checks[] = array(
            'name'   => 'PHP i rozszerzenia',
            'status' => ( empty( $missing ) || $has_image_lib ) ? 'ok' : 'warning',
            'value'  => 'PHP ' . $php_version . ' | Zaladowane: ' . implode( ', ', $loaded ),
            'hint'   => ! empty( $missing ) ? 'Brak: ' . implode( ', ', $missing ) : 'Wszystko OK.',
        );

        // 3. Image processing library.
        $gd_info_str = '';
        if ( extension_loaded( 'gd' ) ) {
            $gd = gd_info();
            $gd_info_str = 'GD ' . ( isset( $gd['GD Version'] ) ? $gd['GD Version'] : '?' );
            $gd_info_str .= ' | JPEG: ' . ( ! empty( $gd['JPEG Support'] ) ? 'tak' : 'nie' );
            $gd_info_str .= ' | PNG: ' . ( ! empty( $gd['PNG Support'] ) ? 'tak' : 'nie' );
            $gd_info_str .= ' | WebP: ' . ( ! empty( $gd['WebP Support'] ) ? 'tak' : 'nie' );
        }
        if ( extension_loaded( 'imagick' ) ) {
            $gd_info_str .= ( $gd_info_str ? ' + ' : '' ) . 'ImageMagick';
        }
        $checks[] = array(
            'name'   => 'Biblioteki obrazkow',
            'status' => $has_image_lib ? 'ok' : 'error',
            'value'  => $has_image_lib ? $gd_info_str : 'BRAK GD i ImageMagick!',
            'hint'   => $has_image_lib ? 'OK — WordPress moze przetwarzac obrazki.' : 'WordPress nie bedzie mogl tworzyc miniaturek zdjec!',
        );

        // 4. Memory, execution time, upload limits.
        $memory_limit = ini_get( 'memory_limit' );
        $memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
        $max_exec = ini_get( 'max_execution_time' );
        $upload_max = ini_get( 'upload_max_filesize' );
        $post_max = ini_get( 'post_max_size' );
        $allow_fopen = ini_get( 'allow_url_fopen' );

        $limits_ok = true;
        $limits_hints = array();
        if ( $memory_bytes < 256 * 1024 * 1024 ) {
            $limits_ok = false;
            $limits_hints[] = 'memory_limit=' . $memory_limit . ' (zalecane 256M+)';
        }
        if ( intval( $max_exec ) > 0 && intval( $max_exec ) < 120 ) {
            $limits_ok = false;
            $limits_hints[] = 'max_execution_time=' . $max_exec . 's (zalecane 120s+)';
        }

        $checks[] = array(
            'name'   => 'Limity PHP',
            'status' => $limits_ok ? 'ok' : 'warning',
            'value'  => 'memory=' . $memory_limit . ' | max_exec=' . $max_exec . 's | upload=' . $upload_max . ' | post=' . $post_max . ' | allow_url_fopen=' . ( $allow_fopen ? 'on' : 'off' ),
            'hint'   => $limits_ok ? 'OK.' : 'Za niskie: ' . implode( ', ', $limits_hints ),
        );

        // 5. WordPress upload dir — writable test.
        $upload_dir = wp_upload_dir();
        $test_file = $upload_dir['basedir'] . '/rolmar-diag-test-' . time() . '.tmp';
        $write_ok = @file_put_contents( $test_file, 'test' );
        if ( $write_ok ) {
            @unlink( $test_file );
        }
        $free_space = @disk_free_space( $upload_dir['basedir'] );
        $free_mb = $free_space !== false ? round( $free_space / 1024 / 1024 ) : '?';

        $checks[] = array(
            'name'   => 'Katalog uploads',
            'status' => $write_ok ? 'ok' : 'error',
            'value'  => ( $write_ok ? 'Zapisywalny' : 'BRAK ZAPISU!' ) . ' | Wolne: ' . $free_mb . ' MB',
            'hint'   => $write_ok ? 'Sciezka: ' . $upload_dir['basedir'] : 'WordPress nie moze zapisac plikow! Sprawdz uprawnienia katalogu.',
        );

        // =====================================================================
        // SEKCJA 2: API ROLMAR
        // =====================================================================

        // 6. API key.
        $api_key = get_option( 'rolmar_api_key', '' );
        $checks[] = array(
            'name'   => 'Klucz API',
            'status' => ! empty( $api_key ) ? 'ok' : 'error',
            'value'  => ! empty( $api_key ) ? 'Skonfigurowany (' . strlen( $api_key ) . ' znakow)' : 'BRAK',
            'hint'   => ! empty( $api_key ) ? '' : 'Ustaw klucz API w ustawieniach powyzej.',
        );

        // 7. DNS resolution for API and photo servers.
        $dns_hosts = array(
            'datalink.rol-mar.com.pl' => 'Serwer API',
            'photo2.rol-mar.com.pl'   => 'Serwer zdjec',
        );
        foreach ( $dns_hosts as $hostname => $label ) {
            $ip = @gethostbyname( $hostname );
            $dns_ok = ( $ip !== $hostname ); // gethostbyname returns hostname on failure.
            $checks[] = array(
                'name'   => 'DNS: ' . $label,
                'status' => $dns_ok ? 'ok' : 'error',
                'value'  => $dns_ok ? $hostname . ' -> ' . $ip : 'Nie mozna rozwiazac ' . $hostname,
                'hint'   => $dns_ok ? '' : 'Serwer DNS nie rozpoznaje tej domeny. Problem z DNS hostingu.',
            );
        }

        // 8. API connection test.
        $api = new Rolmar_API_Client();
        if ( $api->is_configured() ) {
            $api_start = microtime( true );
            $api_result = $api->test_connection();
            $api_time = round( ( microtime( true ) - $api_start ) * 1000 );

            if ( is_wp_error( $api_result ) ) {
                $checks[] = array(
                    'name'   => 'Polaczenie z API (getProducts)',
                    'status' => 'error',
                    'value'  => 'BLAD (' . $api_time . 'ms)',
                    'hint'   => $api_result->get_error_message(),
                );
            } else {
                $product_count = is_array( $api_result ) ? count( $api_result ) : 0;
                $checks[] = array(
                    'name'   => 'Polaczenie z API (getProducts)',
                    'status' => 'ok',
                    'value'  => 'OK — ' . $product_count . ' produktow (' . $api_time . 'ms)',
                    'hint'   => '',
                );
            }

            // 9. getPhotos API test.
            $photos_start = microtime( true );
            $photos_result = $api->get_photos();
            $photos_time = round( ( microtime( true ) - $photos_start ) * 1000 );

            if ( is_wp_error( $photos_result ) ) {
                $checks[] = array(
                    'name'   => 'API getPhotos',
                    'status' => 'error',
                    'value'  => 'BLAD (' . $photos_time . 'ms)',
                    'hint'   => $photos_result->get_error_message(),
                );
            } else {
                $photo_count = is_array( $photos_result ) ? count( $photos_result ) : 0;

                // Analyze photo data structure.
                $with_photos = 0;
                $without_photos = 0;
                $sample_keys = array();
                if ( is_array( $photos_result ) && ! empty( $photos_result ) ) {
                    $sample_keys = array_keys( $photos_result[0] );
                    foreach ( $photos_result as $p ) {
                        $has_photo = false;
                        if ( isset( $p['Photo'] ) && is_array( $p['Photo'] ) && ! empty( $p['Photo'] ) ) {
                            $has_photo = true;
                        } elseif ( isset( $p['Photo'] ) && ! empty( $p['Photo'] ) ) {
                            $has_photo = true;
                        } elseif ( isset( $p['url'] ) && ! empty( $p['url'] ) ) {
                            $has_photo = true;
                        } elseif ( isset( $p['photo'] ) && ! empty( $p['photo'] ) ) {
                            $has_photo = true;
                        }
                        if ( $has_photo ) {
                            $with_photos++;
                        } else {
                            $without_photos++;
                        }
                    }
                }

                $checks[] = array(
                    'name'   => 'API getPhotos',
                    'status' => $photo_count > 0 ? 'ok' : 'warning',
                    'value'  => $photo_count . ' wpisow (' . $photos_time . 'ms) | Ze zdjeciami: ' . $with_photos . ' | Bez: ' . $without_photos,
                    'hint'   => ! empty( $sample_keys ) ? 'Struktura: ' . implode( ', ', $sample_keys ) : 'API nie zwraca zadnych zdjec.',
                );
                $photos_data = $photos_result;
            }
        } else {
            $checks[] = array(
                'name'   => 'Polaczenie z API',
                'status' => 'warning',
                'value'  => 'Pominieto — brak klucza API',
                'hint'   => '',
            );
        }

        // =====================================================================
        // SEKCJA 3: SERWER ZDJEC — TESTY ROZNYCH WARIANTOW
        // =====================================================================

        // 10. Photo server — multiple access methods (short timeouts to avoid AJAX timeout).
        $photo_server_tests = array(
            array(
                'label'  => 'HTTPS (bez SSL verify)',
                'url'    => 'https://photo2.rol-mar.com.pl/',
                'args'   => array( 'timeout' => 5, 'sslverify' => false ),
            ),
            array(
                'label'  => 'HTTPS (z SSL verify)',
                'url'    => 'https://photo2.rol-mar.com.pl/',
                'args'   => array( 'timeout' => 5, 'sslverify' => true ),
            ),
            array(
                'label'  => 'HTTP',
                'url'    => 'http://photo2.rol-mar.com.pl/',
                'args'   => array( 'timeout' => 5, 'sslverify' => false ),
            ),
            array(
                'label'  => 'HTTPS + wsKey',
                'url'    => 'https://photo2.rol-mar.com.pl/',
                'args'   => array(
                    'timeout'   => 5,
                    'sslverify' => false,
                    'headers'   => array( 'wsKey' => $api_key ),
                ),
            ),
        );

        $server_results = array();
        $any_server_ok = false;
        $ssl_ok = null;
        foreach ( $photo_server_tests as $test ) {
            $resp = wp_remote_get( $test['url'], $test['args'] );

            if ( is_wp_error( $resp ) ) {
                $err_msg = $resp->get_error_message();
                $server_results[] = $test['label'] . ': BLAD — ' . $err_msg;
                // Detect SSL issue from the SSL-verify test.
                if ( strpos( $test['label'], 'z SSL verify' ) !== false ) {
                    $ssl_ok = false;
                    $ssl_error_msg = $err_msg;
                }
            } else {
                $code = wp_remote_retrieve_response_code( $resp );
                $server_results[] = $test['label'] . ': HTTP ' . $code;
                if ( $code >= 200 && $code < 500 ) {
                    $any_server_ok = true;
                }
                if ( strpos( $test['label'], 'z SSL verify' ) !== false ) {
                    $ssl_ok = true;
                }
            }
        }

        $checks[] = array(
            'name'   => 'Serwer zdjec — test dostepu',
            'status' => $any_server_ok ? 'ok' : 'error',
            'value'  => $any_server_ok ? 'Serwer odpowiada' : 'BRAK DOSTEPU do photo2.rol-mar.com.pl',
            'hint'   => implode( ' | ', $server_results ),
        );

        // 11. SSL certificate result (from the test above, no extra request needed).
        if ( null === $ssl_ok ) {
            $checks[] = array(
                'name'   => 'SSL certyfikat photo2',
                'status' => 'info',
                'value'  => 'Nie sprawdzono',
                'hint'   => '',
            );
        } elseif ( $ssl_ok ) {
            $checks[] = array(
                'name'   => 'SSL certyfikat photo2',
                'status' => 'ok',
                'value'  => 'Certyfikat poprawny',
                'hint'   => '',
            );
        } else {
            $checks[] = array(
                'name'   => 'SSL certyfikat photo2',
                'status' => 'warning',
                'value'  => 'Problem z certyfikatem SSL',
                'hint'   => 'Uzywamy sslverify=false jako obejscie. ' . ( isset( $ssl_error_msg ) ? 'Blad: ' . $ssl_error_msg : '' ),
            );
        }

        // 12–13. Test real photo URLs — try MULTIPLE variants of a single photo.
        // Prefer testing a photo URL for a SKU that actually exists in WooCommerce.
        $photo_test_url_raw = '';
        $photo_test_sku = '';
        if ( ! empty( $photos_data ) && is_array( $photos_data ) ) {
            // Build a lookup: SKU => photo URL (first occurrence).
            $photo_url_by_sku = array();
            $first_url = '';
            $first_sku = '';
            foreach ( $photos_data as $item ) {
                $item_url = '';
                if ( isset( $item['Photo'] ) && is_array( $item['Photo'] ) && ! empty( $item['Photo'][0] ) ) {
                    $item_url = $item['Photo'][0];
                } elseif ( isset( $item['Photo'] ) && is_string( $item['Photo'] ) && ! empty( $item['Photo'] ) ) {
                    $item_url = $item['Photo'];
                } elseif ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                    $item_url = $item['url'];
                } elseif ( isset( $item['photo'] ) && ! empty( $item['photo'] ) ) {
                    $item_url = $item['photo'];
                }
                if ( empty( $item_url ) ) {
                    continue;
                }

                $item_sku = '';
                if ( isset( $item['Index'] ) ) {
                    $item_sku = $item['Index'];
                } elseif ( isset( $item['index'] ) ) {
                    $item_sku = $item['index'];
                } elseif ( isset( $item['productIndex'] ) ) {
                    $item_sku = $item['productIndex'];
                }

                // Remember the very first entry as fallback.
                if ( empty( $first_url ) ) {
                    $first_url = $item_url;
                    $first_sku = $item_sku ?: 'N/A';
                }

                if ( ! empty( $item_sku ) && ! isset( $photo_url_by_sku[ $item_sku ] ) ) {
                    $photo_url_by_sku[ $item_sku ] = $item_url;
                }

                // Stop building index after 5000 entries to save time.
                if ( count( $photo_url_by_sku ) >= 5000 ) {
                    break;
                }
            }

            // Try to find a matching WooCommerce product SKU for more realistic test.
            if ( class_exists( 'WooCommerce' ) && ! empty( $photo_url_by_sku ) ) {
                $test_products = wc_get_products( array(
                    'limit'  => 10,
                    'status' => 'publish',
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ) );
                foreach ( $test_products as $tp ) {
                    $tp_sku = $tp->get_sku();
                    if ( ! empty( $tp_sku ) && isset( $photo_url_by_sku[ $tp_sku ] ) ) {
                        $photo_test_url_raw = $photo_url_by_sku[ $tp_sku ];
                        $photo_test_sku = $tp_sku;
                        break;
                    }
                }
            }

            // Fallback to first available photo entry.
            if ( empty( $photo_test_url_raw ) && ! empty( $first_url ) ) {
                $photo_test_url_raw = $first_url;
                $photo_test_sku = $first_sku;
            }
        }

        if ( ! empty( $photo_test_url_raw ) ) {
            $clean_url = $this->clean_photo_url( $photo_test_url_raw );

            // Build all possible URL variants to test.
            $url_variants = array();
            $url_variants['Oryginalny URL z API'] = trim( $photo_test_url_raw );
            if ( $clean_url !== $url_variants['Oryginalny URL z API'] ) {
                $url_variants['Oczyszczony URL (bez c=)'] = $clean_url;
            }
            // HTTP variant.
            if ( strpos( $clean_url, 'https://' ) === 0 ) {
                $url_variants['HTTP zamiast HTTPS'] = str_replace( 'https://', 'http://', $clean_url );
            }
            // Without query string entirely.
            $no_qs = strtok( $clean_url, '?' );
            if ( $no_qs !== $clean_url ) {
                $url_variants['Bez query string'] = $no_qs;
            }

            $variant_results = array();
            $any_photo_ok = false;
            $working_variant = '';

            foreach ( $url_variants as $label => $url ) {
                if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    $variant_results[] = $label . ': niepoprawny URL';
                    continue;
                }

                // Test each URL with different header combinations (short timeouts).
                $header_combos = array(
                    'bez naglowkow' => array(),
                    'z wsKey'       => array( 'wsKey' => $api_key ),
                    'z Referer'     => array( 'Referer' => 'https://www.rol-mar.com.pl/' ),
                    'wsKey+Referer' => array( 'wsKey' => $api_key, 'Referer' => 'https://www.rol-mar.com.pl/' ),
                );

                foreach ( $header_combos as $h_label => $headers ) {
                    $test_resp = wp_remote_get( $url, array(
                        'timeout'   => 5,
                        'sslverify' => false,
                        'headers'   => $headers,
                    ) );

                    if ( is_wp_error( $test_resp ) ) {
                        $variant_results[] = $label . ' (' . $h_label . '): BLAD — ' . $test_resp->get_error_message();
                    } else {
                        $v_code = wp_remote_retrieve_response_code( $test_resp );
                        $v_type = wp_remote_retrieve_header( $test_resp, 'content-type' );
                        $v_len  = wp_remote_retrieve_header( $test_resp, 'content-length' );
                        $v_cf   = wp_remote_retrieve_header( $test_resp, 'cf-cache-status' );
                        $v_body_len = strlen( wp_remote_retrieve_body( $test_resp ) );

                        $v_detail = 'HTTP ' . $v_code;
                        if ( $v_type ) {
                            $v_detail .= ', ' . $v_type;
                        }
                        if ( $v_cf ) {
                            $v_detail .= ', CF:' . $v_cf;
                        }
                        if ( $v_len ) {
                            $v_detail .= ', ' . round( intval( $v_len ) / 1024 ) . 'KB';
                        } elseif ( $v_body_len > 0 ) {
                            $v_detail .= ', body=' . round( $v_body_len / 1024 ) . 'KB';
                        }

                        $variant_results[] = $label . ' (' . $h_label . '): ' . $v_detail;

                        if ( 200 === $v_code && $v_body_len > 100 ) {
                            $any_photo_ok = true;
                            if ( empty( $working_variant ) ) {
                                $working_variant = $label . ' (' . $h_label . ')';
                            }
                            break 2; // Found working combo, stop testing.
                        }
                    }
                }
            }

            $checks[] = array(
                'name'   => 'Test zdjecia SKU: ' . $photo_test_sku,
                'status' => $any_photo_ok ? 'ok' : 'error',
                'value'  => $any_photo_ok
                    ? 'DZIALA! Wariant: ' . $working_variant
                    : 'ZADEN wariant nie dziala',
                'hint'   => $any_photo_ok
                    ? 'Oryginalny URL: ' . $photo_test_url_raw
                    : 'Wszystkie warianty URL zwracaja 404. Sprawdz u Rolmar: (1) czy IP ' . ( ! empty( $server_ip ) ? $server_ip : '' ) . ' jest na whiteliscie takze dla photo2.rol-mar.com.pl, (2) czy format URL zdjec sie nie zmienil. URL: ' . $photo_test_url_raw,
            );

            $checks[] = array(
                'name'   => 'Szczegoly testow URL',
                'status' => $any_photo_ok ? 'ok' : 'error',
                'value'  => count( $variant_results ) . ' testow wykonanych',
                'hint'   => implode( ' || ', $variant_results ),
            );

            // 13b. If all fail — try "c=" param with different dimension values.
            // The API returns "c=-bth.." where ".." may be placeholders for dimensions.
            if ( ! $any_photo_ok ) {
                $base_photo_url = strtok( trim( $photo_test_url_raw ), '?' );
                // Extract "d" param value if present.
                $d_param = '';
                if ( preg_match( '/[?&]d=([^&]+)/', $photo_test_url_raw, $d_match ) ) {
                    $d_param = $d_match[1];
                }

                // Try different "c=" dimension patterns.
                $c_variants = array(
                    'bez parametrow'       => $base_photo_url,
                    'd= tylko'             => $base_photo_url . ( $d_param ? '?d=' . $d_param : '' ),
                    'c=-bth800.800'        => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth800.800',
                    'c=-bth800x800'        => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth800x800',
                    'c=-bth.800.800'       => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth.800.800',
                    'c=-bth200.200'        => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth200.200',
                    'c=-bth'               => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth',
                    'c=bth800.800'         => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=bth800.800',
                    'oryginalny c=-bth..'  => $base_photo_url . ( $d_param ? '?d=' . $d_param . '&' : '?' ) . 'c=-bth..',
                );

                $c_results = array();
                $c_working = '';
                foreach ( $c_variants as $c_label => $c_url ) {
                    $c_resp = wp_remote_get( $c_url, array(
                        'timeout'   => 5,
                        'sslverify' => false,
                        'headers'   => array(
                            'wsKey'   => $api_key,
                            'Referer' => 'https://www.rol-mar.com.pl/',
                        ),
                    ) );

                    if ( is_wp_error( $c_resp ) ) {
                        $c_results[] = $c_label . ': BLAD ' . $c_resp->get_error_message();
                        continue;
                    }

                    $c_code = wp_remote_retrieve_response_code( $c_resp );
                    $c_type = wp_remote_retrieve_header( $c_resp, 'content-type' );
                    $c_size = strlen( wp_remote_retrieve_body( $c_resp ) );
                    $c_results[] = $c_label . ': HTTP ' . $c_code . ' (' . $c_type . ', ' . round( $c_size / 1024, 1 ) . 'KB)';

                    if ( 200 === $c_code && $c_size > 100 && strpos( $c_type, 'image' ) !== false ) {
                        $c_working = $c_label . ' -> ' . $c_url;
                        $any_photo_ok = true;
                        break;
                    }
                }

                if ( ! empty( $c_working ) ) {
                    $checks[] = array(
                        'name'   => 'Test wymiarow w parametrze c=',
                        'status' => 'ok',
                        'value'  => 'DZIALA! ' . $c_working,
                        'hint'   => implode( ' || ', $c_results ),
                    );
                } else {
                    $checks[] = array(
                        'name'   => 'Test wymiarow w parametrze c=',
                        'status' => 'error',
                        'value'  => 'Zadna kombinacja nie dziala',
                        'hint'   => implode( ' || ', $c_results ),
                    );

                    // Show actual response body to help diagnose further.
                    $diag_url = $base_photo_url;
                    $diag_resp = wp_remote_get( $diag_url, array(
                        'timeout'   => 5,
                        'sslverify' => false,
                        'headers'   => array( 'wsKey' => $api_key ),
                    ) );
                    $diag_body = '';
                    $diag_code = 0;
                    if ( ! is_wp_error( $diag_resp ) ) {
                        $diag_code = wp_remote_retrieve_response_code( $diag_resp );
                        $diag_body = wp_remote_retrieve_body( $diag_resp );
                    }
                    $diag_text = '';
                    if ( ! empty( $diag_body ) ) {
                        $diag_text = trim( wp_strip_all_tags( $diag_body ) );
                        $diag_text = preg_replace( '/\s+/', ' ', $diag_text );
                        if ( strlen( $diag_text ) > 300 ) {
                            $diag_text = substr( $diag_text, 0, 300 ) . '...';
                        }
                    }
                    $checks[] = array(
                        'name'   => 'Tresc odpowiedzi serwera zdjec',
                        'status' => 'info',
                        'value'  => 'HTTP ' . $diag_code . ' dla: ' . $diag_url,
                        'hint'   => ! empty( $diag_text ) ? $diag_text : '(pusta odpowiedz)',
                    );
                }
            }

            // 14. If photo works — try downloading and saving to uploads.
            if ( $any_photo_ok ) {
                $download_url = $clean_url;
                $tmp = download_url( $download_url, 10 );
                if ( is_wp_error( $tmp ) ) {
                    $checks[] = array(
                        'name'   => 'Zapis zdjecia do uploads',
                        'status' => 'error',
                        'value'  => 'download_url() BLAD',
                        'hint'   => $tmp->get_error_message(),
                    );
                } else {
                    $tmp_size = @filesize( $tmp );
                    @unlink( $tmp );
                    $checks[] = array(
                        'name'   => 'Zapis zdjecia do uploads',
                        'status' => 'ok',
                        'value'  => 'OK — pobrano ' . round( $tmp_size / 1024 ) . ' KB do pliku tymczasowego',
                        'hint'   => 'download_url() dziala poprawnie. Sync zdjec powinien dzialac.',
                    );
                }
            }
        } else {
            $checks[] = array(
                'name'   => 'Test zdjecia',
                'status' => 'warning',
                'value'  => 'Brak URL do testu',
                'hint'   => 'getPhotos nie zwrocilo zadnych URL-i zdjec do przetestowania.',
            );
        }

        // =====================================================================
        // SEKCJA 4: WOOCOMMERCE
        // =====================================================================

        // 15. WooCommerce products count + image stats.
        $wc_total = 0;
        $wc_with_images = 0;
        $wc_without_images = 0;
        $wc_with_gallery = 0;
        if ( function_exists( 'wc_get_products' ) ) {
            $wc_ids = wc_get_products( array(
                'limit'  => -1,
                'status' => 'publish',
                'return' => 'ids',
            ) );
            $wc_total = count( $wc_ids );

            $check_ids = array_slice( $wc_ids, 0, 200 );
            foreach ( $check_ids as $pid ) {
                if ( get_post_thumbnail_id( $pid ) ) {
                    $wc_with_images++;
                    $gallery = get_post_meta( $pid, '_product_image_gallery', true );
                    if ( ! empty( $gallery ) ) {
                        $wc_with_gallery++;
                    }
                } else {
                    $wc_without_images++;
                }
            }

            $sample_note = $wc_total > 200 ? ' (sprawdzono pierwsze 200)' : '';
            $checks[] = array(
                'name'   => 'Produkty WooCommerce',
                'status' => $wc_total > 0 ? 'ok' : 'warning',
                'value'  => $wc_total . ' opublikowanych',
                'hint'   => 'Ze zdjeciem glownym: ' . $wc_with_images . ' | Z galeria: ' . $wc_with_gallery . ' | Bez zdjec: ' . $wc_without_images . $sample_note,
            );

            // 16. Match WooCommerce products with getPhotos data.
            if ( ! empty( $photos_data ) && is_array( $photos_data ) ) {
                $photo_index_keys = array();
                foreach ( $photos_data as $p_item ) {
                    $p_id = '';
                    if ( isset( $p_item['Index'] ) ) {
                        $p_id = $p_item['Index'];
                    } elseif ( isset( $p_item['productIndex'] ) ) {
                        $p_id = $p_item['productIndex'];
                    } elseif ( isset( $p_item['index'] ) ) {
                        $p_id = $p_item['index'];
                    }
                    if ( $p_id ) {
                        $photo_index_keys[ $p_id ] = true;
                    }
                }

                $matched = 0;
                $unmatched = 0;
                $sample_unmatched = array();
                $sample_products = wc_get_products( array(
                    'limit'  => 50,
                    'status' => 'publish',
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ) );

                foreach ( $sample_products as $wc_p ) {
                    $sku = $wc_p->get_sku();
                    if ( empty( $sku ) ) {
                        continue;
                    }
                    if ( isset( $photo_index_keys[ $sku ] ) ) {
                        $matched++;
                    } else {
                        $unmatched++;
                        if ( count( $sample_unmatched ) < 5 ) {
                            $sample_unmatched[] = $sku;
                        }
                    }
                }

                $checks[] = array(
                    'name'   => 'Dopasowanie SKU do getPhotos',
                    'status' => $matched > 0 ? 'ok' : 'error',
                    'value'  => 'Dopasowane: ' . $matched . '/' . ( $matched + $unmatched ) . ' (z 50 sprawdzonych)',
                    'hint'   => $unmatched > 0 && ! empty( $sample_unmatched ) ? 'Nie znalezione SKU: ' . implode( ', ', $sample_unmatched ) : 'Wszystkie sprawdzone SKU maja zdjecia w API.',
                );
            }
        } else {
            $checks[] = array(
                'name'   => 'WooCommerce',
                'status' => 'error',
                'value'  => 'Nieaktywne',
                'hint'   => 'WooCommerce nie jest aktywne.',
            );
        }

        // =====================================================================
        // SEKCJA 5: KONFIGURACJA I STATUS
        // =====================================================================

        // 17. Plugin configuration summary.
        $env = get_option( 'rolmar_api_environment', 'production' );
        $freq = get_option( 'rolmar_sync_frequency', 'daily' );
        $import_images = get_option( 'rolmar_import_images', 'yes' );
        $batch_size = get_option( 'rolmar_batch_size', 50 );
        $discount = get_option( 'rolmar_discount_percent', 0 );
        $allowed_cats = get_option( 'rolmar_allowed_categories', array() );
        $cats_count = is_array( $allowed_cats ) ? count( $allowed_cats ) : 0;
        $checks[] = array(
            'name'   => 'Konfiguracja pluginu',
            'status' => 'info',
            'value'  => 'Srodowisko: ' . $env . ' | Czestotliwosc: ' . $freq . ' | Batch: ' . $batch_size,
            'hint'   => 'Import zdjec: ' . $import_images . ' | Rabat: ' . $discount . '% | Filtry kategorii: ' . ( $cats_count > 0 ? $cats_count . ' wybranych' : 'brak (wszystkie)' ),
        );

        // 18. Last sync timestamps.
        $last_product = get_option( 'rolmar_last_product_sync', '' );
        $last_stock   = get_option( 'rolmar_last_stock_sync', '' );
        $last_photo   = get_option( 'rolmar_last_photo_sync', '' );
        $checks[] = array(
            'name'   => 'Ostatnie synchronizacje',
            'status' => 'info',
            'value'  => 'Produkty: ' . ( $last_product ?: 'nigdy' ),
            'hint'   => 'Stany: ' . ( $last_stock ?: 'nigdy' ) . ' | Zdjecia: ' . ( $last_photo ?: 'nigdy' ),
        );

        // 19. WP Cron status.
        $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $next_product_sync = wp_next_scheduled( 'rolmar_cron_products' );
        $next_stock_sync   = wp_next_scheduled( 'rolmar_cron_stock' );

        $cron_detail = 'WP_CRON: ' . ( $cron_disabled ? 'WYLACZONY' : 'aktywny' );
        if ( $next_product_sync ) {
            $cron_detail .= ' | Nast. produkty: ' . date_i18n( 'Y-m-d H:i:s', $next_product_sync );
        }
        if ( $next_stock_sync ) {
            $cron_detail .= ' | Nast. stany: ' . date_i18n( 'Y-m-d H:i:s', $next_stock_sync );
        }
        $checks[] = array(
            'name'   => 'WP Cron (auto-sync)',
            'status' => $cron_disabled ? 'warning' : 'ok',
            'value'  => $cron_detail,
            'hint'   => $cron_disabled ? 'DISABLE_WP_CRON jest wlaczony. Automatyczna synchronizacja nie bedzie dzialac bez zewnetrznego crona.' : '',
        );

        // 20. Sync lock check.
        $sync_lock = get_transient( 'rolmar_sync_in_progress' );
        if ( $sync_lock ) {
            $checks[] = array(
                'name'   => 'Blokada synchronizacji',
                'status' => 'warning',
                'value'  => 'Aktywna blokada: ' . $sync_lock,
                'hint'   => 'Jesli synchronizacja sie zawiesi, blokada wygasnie automatycznie po godzinie.',
            );
        }

        wp_send_json_success( array( 'checks' => $checks ) );
    }

    /**
     * Clean malformed photo URL from the API.
     *
     * @param string $url Raw URL from API.
     * @return string Cleaned URL.
     */
    private function clean_photo_url( $url ) {
        // Only trim spaces — dots are part of the c= value (e.g. "c=-bth..").
        $url = trim( $url );

        // Remove 'c' query parameter as fallback variant.
        $url = preg_replace( '/[?&]c=[^&]*/', '', $url );

        // Clean up leftover '?' or '&'.
        $url = rtrim( $url, '?&' );
        $url = preg_replace( '/\?&/', '?', $url );

        return $url;
    }
}
