#!/usr/bin/env php
<?php
/**
 * DEBUG CLI: Rolmar getPhotos Raw Response
 *
 * Uruchom: php debug-photos-cli.php
 */

// Load WordPress
require_once( dirname(__FILE__) . '/wp-load.php' );

echo "\n";
echo "🔍 DEBUG: Rolmar getPhotos API\n";
echo str_repeat("=", 60) . "\n\n";

// Test SKUs
$test_skus = array(
    'V-6-MBRV-02PK220',
    'V-POM-BK',
    'V-4WE6-CG1-24V',
);

echo "🎯 Testowane SKU:\n";
foreach ($test_skus as $sku) {
    echo "   → {$sku}\n";
}
echo "\n";

// Initialize API
$api_client = new Rolmar_API_Client();

if (!$api_client->is_configured()) {
    echo "❌ BŁĄD: Klucz API nie skonfigurowany!\n";
    exit(1);
}

echo "✅ API skonfigurowane\n";
echo "📡 Wywołuję getPhotos...\n\n";

// Call API
$start = microtime(true);
$photos = $api_client->get_photos();
$duration = round((microtime(true) - $start) * 1000);

if (is_wp_error($photos)) {
    echo "❌ BŁĄD API:\n";
    echo "   Kod: " . $photos->get_error_code() . "\n";
    echo "   Msg: " . $photos->get_error_message() . "\n";
    exit(1);
}

echo "✅ Odpowiedź: {$duration}ms\n";
echo "📊 Rekordów: " . count($photos) . "\n\n";

// Analyze test SKUs
echo str_repeat("-", 60) . "\n";
echo "🔎 Analiza testowych SKU:\n";
echo str_repeat("-", 60) . "\n\n";

foreach ($test_skus as $test_sku) {
    echo "SKU: {$test_sku}\n";

    // Find record
    $found = false;
    foreach ($photos as $photo) {
        if (isset($photo['Index']) && $photo['Index'] === $test_sku) {
            $found = true;

            $photo_count = 0;
            if (isset($photo['Photo']) && is_array($photo['Photo'])) {
                $photo_count = count($photo['Photo']);
            }

            if ($photo_count > 0) {
                echo "   ✅ Znaleziono: {$photo_count} zdjęć\n";
                echo "   🔗 Pierwsze: {$photo['Photo'][0]}\n";
            } else {
                echo "   🟠 Znaleziono, ale BRAK zdjęć\n";
                if (isset($photo['Photo'])) {
                    echo "   ⚠️  Pole 'Photo' istnieje, typ: " . gettype($photo['Photo']) . "\n";
                } else {
                    echo "   ❌ Brak pola 'Photo'\n";
                }
            }

            break;
        }
    }

    if (!$found) {
        echo "   ❌ NIE znaleziono w API\n";
    }

    echo "\n";
}

// Show full JSON for first SKU
echo str_repeat("-", 60) . "\n";
echo "📋 PEŁNA STRUKTURA JSON dla: {$test_skus[0]}\n";
echo str_repeat("-", 60) . "\n\n";

foreach ($photos as $photo) {
    if (isset($photo['Index']) && $photo['Index'] === $test_skus[0]) {
        echo json_encode($photo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "\n\n";
        break;
    }
}

// Statistics
echo str_repeat("=", 60) . "\n";
echo "📊 Statystyki całego API:\n";
echo str_repeat("=", 60) . "\n\n";

$total = count($photos);
$with_photos = 0;
$without_photos = 0;
$total_photos = 0;

foreach ($photos as $photo) {
    $count = 0;
    if (isset($photo['Photo']) && is_array($photo['Photo'])) {
        $count = count($photo['Photo']);
    }

    if ($count > 0) {
        $with_photos++;
        $total_photos += $count;
    } else {
        $without_photos++;
    }
}

echo "Wszystkich wpisów:  {$total}\n";
echo "Z zdjęciami:        {$with_photos}\n";
echo "BEZ zdjęć:          {$without_photos}\n";
echo "Suma zdjęć:         {$total_photos}\n\n";

// Sample products WITH photos
if ($with_photos > 0) {
    echo str_repeat("-", 60) . "\n";
    echo "✅ Przykłady produktów Z zdjęciami (3 pierwsze):\n";
    echo str_repeat("-", 60) . "\n\n";

    $shown = 0;
    foreach ($photos as $photo) {
        if ($shown >= 3) break;

        if (isset($photo['Photo']) && is_array($photo['Photo']) && count($photo['Photo']) > 0) {
            $sku = isset($photo['Index']) ? $photo['Index'] : 'BRAK';
            $count = count($photo['Photo']);

            echo "SKU: {$sku}\n";
            echo "   Ilość: {$count} zdjęć\n";
            echo "   URL:   {$photo['Photo'][0]}\n\n";

            $shown++;
        }
    }
} else {
    echo "❌ BRAK produktów ze zdjęciami w całym API!\n\n";
}

// Conclusion
echo str_repeat("=", 60) . "\n";
echo "💡 WNIOSEK:\n";
echo str_repeat("=", 60) . "\n\n";

if ($without_photos > ($total / 2)) {
    echo "⚠️  Ponad połowa produktów BEZ zdjęć!\n";
    echo "   Skontaktuj się z Rolmar support.\n\n";
}

echo "📧 Jeśli Twoje SKU nie mają zdjęć, napisz do Rolmar:\n\n";
echo "   'Endpoint getPhotos nie zwraca URLi dla SKU:\n";
echo "    V-6-MBRV-02PK220, V-POM-BK (marka VOIMA).\n";
echo "    Czy te produkty mają zdjęcia w waszym systemie?'\n\n";
