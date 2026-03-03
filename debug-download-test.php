#!/usr/bin/env php
<?php
/**
 * DEBUG: Test pobrania zdjęcia z API Rolmar
 *
 * Ten skrypt:
 * 1. Pobiera listę zdjęć z getPhotos
 * 2. Wybiera pierwsze zdjęcie z URL-em
 * 3. Próbuje je pobrać (tak jak robi to wtyczka)
 * 4. Raportuje URL i wynik - DO WYSŁANIA TECHNIKOWI
 *
 * Uruchom: php debug-download-test.php
 * Lub przez przeglądarkę: https://twoja-domena.pl/debug-download-test.php
 */

// Detect if running in CLI or browser.
$is_cli = ( php_sapi_name() === 'cli' );

// Load WordPress.
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// ─── Output helpers ──────────────────────────────────────────────
function out( $text ) {
    global $is_cli;
    if ( $is_cli ) {
        echo $text . "\n";
    } else {
        echo nl2br( htmlspecialchars( $text ) ) . '<br>';
    }
}

function out_header( $text ) {
    global $is_cli;
    if ( $is_cli ) {
        echo "\n" . str_repeat( '=', 60 ) . "\n";
        echo $text . "\n";
        echo str_repeat( '=', 60 ) . "\n";
    } else {
        echo '<h2 style="color:#4ec9b0;font-family:monospace;">' . htmlspecialchars( $text ) . '</h2>';
    }
}

function out_box( $text, $type = 'info' ) {
    global $is_cli;
    $icons = array( 'ok' => '✅', 'err' => '❌', 'warn' => '⚠️', 'info' => '📋' );
    $icon  = isset( $icons[ $type ] ) ? $icons[ $type ] : '';

    if ( $is_cli ) {
        echo "{$icon} {$text}\n";
    } else {
        $colors = array( 'ok' => '#4ec9b0', 'err' => '#f48771', 'warn' => '#dcdcaa', 'info' => '#007acc' );
        $color  = isset( $colors[ $type ] ) ? $colors[ $type ] : '#007acc';
        echo '<div style="font-family:monospace;background:#252526;color:#d4d4d4;padding:10px 15px;margin:8px 0;border-left:4px solid ' . $color . ';border-radius:4px;">'
             . htmlspecialchars( "{$icon} {$text}" ) . '</div>';
    }
}

// ─── HTML wrapper for browser ────────────────────────────────────
if ( ! $is_cli ) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Test pobrania zdjęcia - Rolmar</title>';
    echo '<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;max-width:900px;}</style>';
    echo '</head><body>';
}

// ─── 1. Initialize API ──────────────────────────────────────────
out_header( 'TEST POBRANIA ZDJECIA Z API ROLMAR' );
out( 'Data testu: ' . date( 'Y-m-d H:i:s' ) );
out( '' );

$api_client = new Rolmar_API_Client();

if ( ! $api_client->is_configured() ) {
    out_box( 'Klucz API nie jest skonfigurowany!', 'err' );
    exit( 1 );
}
out_box( 'API skonfigurowane - klucz OK', 'ok' );

// ─── 2. Fetch photo list ────────────────────────────────────────
out_header( 'KROK 1: Pobieram liste zdjec z getPhotos...' );

$start   = microtime( true );
$photos  = $api_client->get_photos();
$api_ms  = round( ( microtime( true ) - $start ) * 1000 );

if ( is_wp_error( $photos ) ) {
    out_box( 'Blad API: ' . $photos->get_error_message(), 'err' );
    exit( 1 );
}

if ( ! is_array( $photos ) || empty( $photos ) ) {
    out_box( 'API zwrocilo pusta odpowiedz lub nie-tablice', 'err' );
    exit( 1 );
}

out_box( "Otrzymano " . count( $photos ) . " wpisow z API ({$api_ms}ms)", 'ok' );

// ─── 3. Find a product with photo URLs ──────────────────────────
out_header( 'KROK 2: Szukam produktu z URL-em zdjecia...' );

$test_url = '';
$test_sku = '';
$test_entry = null;

foreach ( $photos as $item ) {
    $sku = '';
    if ( isset( $item['Index'] ) ) {
        $sku = $item['Index'];
    } elseif ( isset( $item['productIndex'] ) ) {
        $sku = $item['productIndex'];
    } elseif ( isset( $item['index'] ) ) {
        $sku = $item['index'];
    }

    // Try to extract URL.
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
        $test_url   = $url;
        $test_sku   = $sku;
        $test_entry = $item;
        break;
    }
}

if ( empty( $test_url ) ) {
    out_box( 'Nie znaleziono zadnego produktu z URL-em zdjecia w calym API!', 'err' );
    out_box( 'API zwraca ' . count( $photos ) . ' wpisow, ale zaden nie zawiera URLa.', 'warn' );
    out( '' );
    out( 'Przyklad pierwszego wpisu z API:' );
    if ( ! empty( $photos[0] ) ) {
        out( json_encode( $photos[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }
    exit( 1 );
}

out_box( "Znaleziono zdjecie dla SKU: {$test_sku}", 'ok' );
out( '' );

// ─── 4. Show the URL clearly ────────────────────────────────────
out_header( 'KROK 3: URL DO WYSLANIA TECHNIKOWI' );

// Prepare URL variants (same logic as plugin).
$original_url = rtrim( $test_url, '. ' );
$cleaned_url  = preg_replace( '/[?&]c=[^&]*/', '', $original_url );
$cleaned_url  = rtrim( $cleaned_url, '?&' );
$cleaned_url  = preg_replace( '/\?&/', '?', $cleaned_url );
$base_url     = strtok( $original_url, '?' );

out( 'SKU:              ' . $test_sku );
out( 'URL oryginalny:   ' . $original_url );
if ( $cleaned_url !== $original_url ) {
    out( 'URL wyczyszczony: ' . $cleaned_url );
}
if ( $base_url !== $original_url && $base_url !== $cleaned_url ) {
    out( 'URL bazowy:       ' . $base_url );
}
out( '' );

// Full JSON entry for reference.
out( 'Pelny wpis JSON z API:' );
out( json_encode( $test_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
out( '' );

// ─── 5. Attempt to download the image ───────────────────────────
out_header( 'KROK 4: Probuje pobrac zdjecie...' );

$api_key = get_option( 'rolmar_api_key', '' );

$urls_to_try = array( $original_url );
if ( $cleaned_url !== $original_url ) {
    $urls_to_try[] = $cleaned_url;
}
if ( $base_url !== $original_url && $base_url !== $cleaned_url ) {
    $urls_to_try[] = $base_url;
}

$download_success = false;
$attempt_num = 0;

foreach ( $urls_to_try as $try_url ) {
    $attempt_num++;
    out( "--- Proba {$attempt_num}: {$try_url}" );

    if ( ! filter_var( $try_url, FILTER_VALIDATE_URL ) ) {
        out_box( "  Nieprawidlowy format URL - pomijam", 'warn' );
        continue;
    }

    $dl_start = microtime( true );
    $response = wp_remote_get( $try_url, array(
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => array(
            'wsKey'   => $api_key,
            'Referer' => 'https://www.rol-mar.com.pl/',
        ),
    ) );
    $dl_ms = round( ( microtime( true ) - $dl_start ) * 1000 );

    if ( is_wp_error( $response ) ) {
        out_box( "  Blad polaczenia: " . $response->get_error_message() . " ({$dl_ms}ms)", 'err' );
        continue;
    }

    $http_code    = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $body         = wp_remote_retrieve_body( $response );
    $body_size    = strlen( $body );

    out( "  HTTP status:    {$http_code}" );
    out( "  Content-Type:   {$content_type}" );
    out( "  Rozmiar:        {$body_size} bajtow (" . round( $body_size / 1024, 1 ) . " KB)" );
    out( "  Czas pobrania:  {$dl_ms}ms" );

    if ( 200 === (int) $http_code && $body_size > 0 ) {
        // Verify it's an actual image.
        $is_image = false;
        if ( strpos( $content_type, 'image/' ) !== false ) {
            $is_image = true;
        } elseif ( function_exists( 'imagecreatefromstring' ) ) {
            $test_img = @imagecreatefromstring( $body );
            if ( false !== $test_img ) {
                $is_image = true;
                $width  = imagesx( $test_img );
                $height = imagesy( $test_img );
                out( "  Wymiary:        {$width}x{$height} px" );
                imagedestroy( $test_img );
            }
        }

        if ( $is_image ) {
            out_box( "SUKCES! Zdjecie pobrane poprawnie z: {$try_url}", 'ok' );
            $download_success = true;
            break;
        } else {
            out_box( "  Otrzymano dane, ale to nie jest obraz (Content-Type: {$content_type})", 'warn' );
            // Show first 200 chars of body for debugging.
            $preview = substr( $body, 0, 200 );
            if ( ! preg_match( '/[\x00-\x08\x0E-\x1F]/', $preview ) ) {
                out( "  Poczatek odpowiedzi: " . $preview );
            }
        }
    } else {
        out_box( "  HTTP {$http_code} - pobranie nie powiodlo sie", 'err' );
        // Show response body for non-200 errors (might contain error message).
        if ( $body_size > 0 && $body_size < 1000 ) {
            $preview = substr( $body, 0, 500 );
            if ( ! preg_match( '/[\x00-\x08\x0E-\x1F]/', $preview ) ) {
                out( "  Tresc odpowiedzi: " . $preview );
            }
        }
    }
    out( '' );
}

// ─── 6. Summary for technician ──────────────────────────────────
out_header( 'PODSUMOWANIE - WYSLIJ TO TECHNIKOWI' );

out( 'Data/czas testu:  ' . date( 'Y-m-d H:i:s' ) );
out( 'SKU testowe:      ' . $test_sku );
out( 'URL testowy:      ' . $original_url );
out( '' );

if ( $download_success ) {
    out_box( 'Pobranie zdjecia: UDANE', 'ok' );
    out( '' );
    out( 'Wiadomosc dla technika:' );
    out( '---' );
    out( "Probowalem pobrac zdjecie i pobralo sie poprawnie." );
    out( "Link do zdjecia: {$original_url}" );
    out( "SKU: {$test_sku}" );
    out( "Data/czas proby: " . date( 'Y-m-d H:i:s' ) );
    out( '---' );
} else {
    out_box( 'Pobranie zdjecia: NIEUDANE', 'err' );
    out( '' );
    out( 'Wiadomosc dla technika:' );
    out( '---' );
    out( "Probowalem pobrac zdjecie, ale nie udalo sie." );
    out( "Link do zdjecia: {$original_url}" );
    out( "SKU: {$test_sku}" );
    out( "Data/czas proby: " . date( 'Y-m-d H:i:s' ) );
    out( "Probowalem {$attempt_num} wariantow URL, zaden nie zwrocil obrazu." );
    out( '---' );
}

out( '' );
out( 'Logi wtyczki sa w: /wp-content/uploads/rolmar-logs/' );

if ( ! $is_cli ) {
    echo '</body></html>';
}
