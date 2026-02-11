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

            // Clean URL the same way as in the importer.
            $original_url = $main_photo;
            $cleaned_url = rtrim( $main_photo, '. ' );
            $cleaned_url = preg_replace( '/\?c=-[^&]*$/', '', $cleaned_url );

            // Test if URL is accessible.
            $status = 'unknown';
            $http_code = 0;
            $error_msg = '';
            if ( ! empty( $cleaned_url ) ) {
                $test_response = wp_remote_head( $cleaned_url, array(
                    'timeout' => 10,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ) );
                if ( ! is_wp_error( $test_response ) ) {
                    $http_code = wp_remote_retrieve_response_code( $test_response );
                    $status = ( $http_code === 200 ) ? 'OK' : 'FAIL';
                } else {
                    $status = 'ERROR';
                    $error_msg = $test_response->get_error_message();
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
            if ( isset( $item['productIndex'] ) ) {
                $identifier = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $identifier = $item['index'];
            } elseif ( isset( $item['sku'] ) ) {
                $identifier = $item['sku'];
            }

            // Extract photo URLs - API returns 'url' field with single URL.
            $photo_urls = array();
            if ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                // getPhotos returns single 'url' field
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

            // Test first photo URL if available.
            $first_photo_status = 'N/A';
            $first_photo_http = 0;
            if ( ! empty( $photo_urls[0] ) ) {
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
            if ( isset( $item['productIndex'] ) ) {
                $identifier = $item['productIndex'];
            } elseif ( isset( $item['index'] ) ) {
                $identifier = $item['index'];
            } elseif ( isset( $item['sku'] ) ) {
                $identifier = $item['sku'];
            }

            if ( $identifier ) {
                // Extract photos - API returns 'url' field with single URL.
                $photo_urls = array();
                if ( isset( $item['url'] ) && ! empty( $item['url'] ) ) {
                    // getPhotos returns single 'url' field
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
}
