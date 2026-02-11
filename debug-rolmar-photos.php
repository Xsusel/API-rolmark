<?php
/**
 * DEBUG: Rolmar getPhotos Raw Response
 *
 * Ten skrypt pokazuje DOKŁADNĄ odpowiedź z API Rolmar dla getPhotos.
 * Użyj tego do rozmowy z supportem Rolmar!
 */

// Load WordPress
require_once( dirname(__FILE__) . '/wp-load.php' );

// HTML Header
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>DEBUG: Rolmar getPhotos API</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        .box { background: #252526; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007acc; }
        .success { border-left-color: #4ec9b0; }
        .error { border-left-color: #f48771; }
        .warning { border-left-color: #dcdcaa; }
        pre { background: #1e1e1e; padding: 10px; overflow: auto; border: 1px solid #3e3e42; }
        .sku { color: #ce9178; font-weight: bold; }
        .count { color: #b5cea8; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #3e3e42; }
        th { color: #4ec9b0; }
    </style>
</head>
<body>

<h1>🔍 DEBUG: Rolmar getPhotos API</h1>

<?php

// Test SKUs from your products
$test_skus = array(
    'V-6-MBRV-02PK220',
    'V-POM-BK',
    'V-4WE6-CG1-24V',
);

echo '<div class="box warning">';
echo '<strong>🎯 Testowane SKU:</strong><br>';
foreach ($test_skus as $sku) {
    echo "→ <span class='sku'>{$sku}</span><br>";
}
echo '</div>';

// Initialize API Client
$api_client = new Rolmar_API_Client();

if (!$api_client->is_configured()) {
    echo '<div class="box error">';
    echo '<strong>❌ BŁĄD:</strong> Klucz API Rolmar nie jest skonfigurowany!';
    echo '</div>';
    exit;
}

echo '<div class="box success">';
echo '<strong>✅ Klucz API:</strong> Skonfigurowany<br>';
echo '<strong>📡 Endpoint:</strong> photo/photo.php → getPhotos';
echo '</div>';

// Call getPhotos API
echo '<h2>📥 Wywołanie API...</h2>';
$start_time = microtime(true);
$photos = $api_client->get_photos();
$duration = round((microtime(true) - $start_time) * 1000);

if (is_wp_error($photos)) {
    echo '<div class="box error">';
    echo "<strong>❌ BŁĄD API:</strong><br>";
    echo "Kod: " . $photos->get_error_code() . "<br>";
    echo "Wiadomość: " . $photos->get_error_message();
    echo '</div>';
    exit;
}

echo '<div class="box success">';
echo "<strong>✅ Odpowiedź otrzymana:</strong> {$duration}ms<br>";
echo "<strong>📊 Rekordów:</strong> <span class='count'>" . count($photos) . "</span>";
echo '</div>';

// Analyze responses for test SKUs
echo '<h2>🔎 Analiza testowych SKU:</h2>';

echo '<table>';
echo '<tr><th>SKU</th><th>Status</th><th>Ilość foto</th><th>Struktura</th></tr>';

foreach ($test_skus as $test_sku) {
    // Find matching record
    $found = false;
    $photo_record = null;

    foreach ($photos as $photo) {
        if (isset($photo['Index']) && $photo['Index'] === $test_sku) {
            $found = true;
            $photo_record = $photo;
            break;
        }
    }

    echo '<tr>';
    echo "<td><span class='sku'>{$test_sku}</span></td>";

    if (!$found) {
        echo '<td>❌ NIE znaleziono</td>';
        echo '<td>-</td>';
        echo '<td>-</td>';
    } else {
        echo '<td>✅ Znaleziono</td>';

        // Count photos
        $photo_count = 0;
        if (isset($photo_record['Photo']) && is_array($photo_record['Photo'])) {
            $photo_count = count($photo_record['Photo']);
        }

        echo "<td><span class='count'>{$photo_count}</span></td>";
        echo '<td>';

        // Show structure
        if ($photo_count > 0) {
            echo '✅ Array z ' . $photo_count . ' elementami';
        } else if (isset($photo_record['Photo'])) {
            echo '⚠️ Pole "Photo" istnieje, ale puste: ' . gettype($photo_record['Photo']);
        } else {
            echo '❌ Brak pola "Photo"';
        }

        echo '</td>';
    }

    echo '</tr>';
}

echo '</table>';

// Show full structure for first test SKU
echo '<h2>📋 PEŁNA STRUKTURA dla SKU: <span class="sku">' . $test_skus[0] . '</span></h2>';

$first_found = false;
foreach ($photos as $photo) {
    if (isset($photo['Index']) && $photo['Index'] === $test_skus[0]) {
        echo '<div class="box">';
        echo '<strong>🔍 RAW JSON Response:</strong>';
        echo '<pre>' . json_encode($photo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
        echo '</div>';
        $first_found = true;
        break;
    }
}

if (!$first_found) {
    echo '<div class="box error">';
    echo '❌ NIE znaleziono tego SKU w odpowiedzi API!';
    echo '</div>';
}

// Show statistics
echo '<h2>📊 Statystyki całego getPhotos:</h2>';

$total_records = count($photos);
$records_with_photos = 0;
$records_without_photos = 0;
$total_photos = 0;

foreach ($photos as $photo) {
    $photo_count = 0;
    if (isset($photo['Photo']) && is_array($photo['Photo'])) {
        $photo_count = count($photo['Photo']);
    }

    if ($photo_count > 0) {
        $records_with_photos++;
        $total_photos += $photo_count;
    } else {
        $records_without_photos++;
    }
}

echo '<div class="box">';
echo "<strong>Wszystkich wpisów:</strong> <span class='count'>{$total_records}</span><br>";
echo "<strong>Z zdjęciami:</strong> <span class='count'>{$records_with_photos}</span><br>";
echo "<strong>BEZ zdjęć:</strong> <span class='count'>{$records_without_photos}</span><br>";
echo "<strong>Suma zdjęć:</strong> <span class='count'>{$total_photos}</span><br>";
echo '</div>';

// Show sample records WITH photos (first 3)
echo '<h2>✅ Przykłady produktów Z zdjęciami:</h2>';

$samples_shown = 0;
foreach ($photos as $photo) {
    if ($samples_shown >= 3) break;

    if (isset($photo['Photo']) && is_array($photo['Photo']) && count($photo['Photo']) > 0) {
        $sku = isset($photo['Index']) ? $photo['Index'] : 'BRAK SKU';
        $photo_count = count($photo['Photo']);

        echo '<div class="box success">';
        echo "<strong>SKU:</strong> <span class='sku'>{$sku}</span><br>";
        echo "<strong>Ilość foto:</strong> <span class='count'>{$photo_count}</span><br>";
        echo '<strong>Przykładowy URL:</strong> ' . $photo['Photo'][0] . '<br>';
        echo '</div>';

        $samples_shown++;
    }
}

if ($samples_shown === 0) {
    echo '<div class="box error">';
    echo '❌ BRAK produktów ze zdjęciami w całym API!';
    echo '</div>';
}

?>

<h2>💡 CO TO ZNACZY:</h2>

<div class="box warning">
    <strong>Jeśli Twoje SKU mają "0" zdjęć:</strong><br>
    → API Rolmar NIE MA zdjęć dla tych produktów w bazie<br>
    → Skontaktuj się z Rolmar support i pokaż im ten raport<br>
    → Podaj przykładowe SKU które powinny mieć zdjęcia<br><br>

    <strong>Jeśli inne produkty MAJĄ zdjęcia:</strong><br>
    → To potwierdza że API działa poprawnie<br>
    → Problem jest tylko z konkretnymi produktami/markami<br>
    → Rolmar musi dodać zdjęcia dla Twoich produktów
</div>

<div class="box">
    <strong>📧 Do supportu Rolmar napisz:</strong><br>
    <pre style="background: #2d2d30; color: #ce9178;">
Witam,

Endpoint getPhotos nie zwraca URLi zdjęć dla produktów marki VOIMA.
Przykładowe SKU: V-6-MBRV-02PK220, V-POM-BK, V-4WE6-CG1-24V

API zwraca wpisy dla tych SKU, ale pole "Photo" jest puste lub nie zawiera URLi.
Czy te produkty mają zdjęcia w waszym systemie?

Proszę o sprawdzenie.
    </pre>
</div>

</body>
</html>
