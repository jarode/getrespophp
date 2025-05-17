<?php
// test_sync.php
// Testuje endpoint sync.php z przykładowymi danymi

$domain = 'b24-5xjk9p.bitrix24.com'; // domena testowa Bitrix24
$apiKey = '62a96f1wzus8pp7s6o83s233j2to908k';
$listId = 'id0RG';

// Opcje synchronizacji (możesz zmienić na potrzeby testów)
$options = [
    'DOMAIN' => $domain,
    'syncNewOnly' => false,      // synchronizuj tylko nowe kontakty
    'updateExisting' => true,    // aktualizuj istniejące kontakty
    'filterTag' => false,        // filtruj po tagu
    'syncLimit' => 5,            // limit synchronizacji (0 = bez limitu)
    'conflictRule' => 'newer'    // reguła rozwiązywania konfliktów: 'bitrix', 'getresponse', 'newer'
];

// URL endpointu synchronizacji
$url = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/sync.php';

// Przygotuj request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Wykonaj request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Przygotuj wynik
$result = [
    'request' => [
        'url' => $url,
        'options' => $options
    ],
    'response' => [
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'data' => json_decode($response, true) ?? $response
    ]
];

// Wyświetl wynik
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Dodatkowe informacje w konsoli
if (php_sapi_name() === 'cli') {
    echo "\n\nStatus synchronizacji:\n";
    if ($httpCode === 200 && isset($result['response']['data']['success']) && $result['response']['data']['success']) {
        $res = $result['response']['data']['results'];
        echo "✓ Synchronizacja zakończona sukcesem\n";
        echo "  Bitrix24: {$res['bitrix_before']} kontaktów przed synchronizacją\n";
        echo "  GetResponse: {$res['getresponse_before']} kontaktów przed synchronizacją\n";
        echo "  Dodano do GetResponse: {$res['added_to_gr']}\n";
        echo "  Zaktualizowano w GetResponse: {$res['updated_gr']}\n";
        echo "  Pominięto w GetResponse: {$res['skipped_gr']}\n";
        echo "  Dodano do Bitrix24: {$res['added_to_b24']}\n";
        echo "  Zaktualizowano w Bitrix24: {$res['updated_b24']}\n";
        echo "  Pominięto w Bitrix24: {$res['skipped_b24']}\n";
        echo "  Czas synchronizacji: {$res['sync_time']}\n";
    } else {
        echo "✗ Błąd synchronizacji\n";
        echo "  Kod HTTP: $httpCode\n";
        if ($curlError) {
            echo "  Błąd cURL: $curlError\n";
        }
        if (isset($result['response']['data']['error'])) {
            echo "  Błąd API: {$result['response']['data']['error']}\n";
        }
    }
} 