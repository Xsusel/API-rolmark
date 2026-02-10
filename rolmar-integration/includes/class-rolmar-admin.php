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

        // Category Filter section.
        add_settings_section(
            'rolmar_category_section',
            __( 'Filtr kategorii', 'rolmar-integration' ),
            function () {
                echo '<p>' . esc_html__( 'Wybierz kategorie produktów do importu. Jeśli żadna kategoria nie jest zaznaczona, importowane będą wszystkie produkty.', 'rolmar-integration' ) . '</p>';
            },
            'rolmar-integration'
        );

        register_setting( 'rolmar_settings', 'rolmar_allowed_categories', array(
            'sanitize_callback' => array( $this, 'sanitize_allowed_categories' ),
        ) );

        add_settings_field(
            'rolmar_allowed_categories',
            __( 'Dozwolone kategorie', 'rolmar-integration' ),
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

    public function render_category_tree_field() {
        $allowed    = get_option( 'rolmar_allowed_categories', array() );
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
                <?php esc_html_e( 'Zaznacz kategorie wyższego poziomu, aby automatycznie zaznaczyć wszystkie podkategorie. Możesz następnie odznaczyć poszczególne podkategorie.', 'rolmar-integration' ); ?>
                <br />
                <strong><?php esc_html_e( 'Wybrano:', 'rolmar-integration' ); ?></strong>
                <span id="rolmar-category-count"></span>
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

        wp_localize_script( 'rolmar-admin', 'rolmarAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rolmar_admin_nonce' ),
            'i18n'    => array(
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
        foreach ( $products as $product ) {
            if ( empty( $product['categories'] ) || ! is_array( $product['categories'] ) ) {
                continue;
            }
            foreach ( $product['categories'] as $path ) {
                $parts = array_map( 'trim', explode( '>', $path ) );
                $parts = array_filter( $parts );
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
            $current_path = $parent_path ? $parent_path . '>' . $name : $name;
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

            if ( $has_children ) {
                $html .= $this->render_category_tree_html( $children, $current_path );
            }

            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
