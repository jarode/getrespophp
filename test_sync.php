<?php
// test_sync.php
// Testuje endpoint sync.php z nową logiką importu kontaktów z GetResponse do Bitrix24

// Funkcja do wywoływania crest.php przez HTTP
function callCrest($method, $params = []) {
    $url = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/crest.php';
    $payload = [
        'method' => $method,
        'params' => $params
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$domain = 'b24-5xjk9p.bitrix24.com'; // domena testowa Bitrix24

// Payload tylko z domeną
$options = [
    'DOMAIN' => $domain
];

// URL endpointu importu
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

// TEST: Uzupełnianie pola UF_CRM_EMAIL_SYNC_AUTOMATION w kontaktach Bitrix24 (poprawna wersja)
$updated = 0;
$checked = 0;
$start = 0;
do {
    $batch = callCrest('crm.contact.list', [
        'order' => ['ID' => 'ASC'],
        'select' => ['ID', 'UF_CRM_EMAIL_SYNC_AUTOMATION'],
        'filter' => [],
        'start' => $start
    ]);
    if (!empty($batch['result'])) {
        foreach ($batch['result'] as $contact) {
            $checked++;
            $emailField = $contact['UF_CRM_EMAIL_SYNC_AUTOMATION'] ?? '';
            if (empty($emailField)) {
                // Pobierz szczegóły kontaktu
                $details = callCrest('crm.contact.get', ['ID' => $contact['ID']]);
                $c = $details['result'] ?? [];
                $emails = $c['EMAIL'] ?? [];
                $mainEmail = '';
                if (!empty($emails) && isset($emails[0]['VALUE'])) {
                    $mainEmail = $emails[0]['VALUE'];
                }
                if ($mainEmail) {
                    $res = callCrest('crm.contact.update', [
                        'id' => $contact['ID'],
                        'fields' => [
                            'UF_CRM_EMAIL_SYNC_AUTOMATION' => $mainEmail
                        ]
                    ]);
                    $updated++;
                }
            }
        }
    }
    $start = $batch['next'] ?? 0;
    sleep(1);
} while (!empty($batch['result']) && $start > 0);

$result['email_sync_update'] = [
    'checked' => $checked,
    'updated' => $updated
];

if (php_sapi_name() === 'cli') {
    echo "\n\nUzupełnianie pola UF_CRM_EMAIL_SYNC_AUTOMATION (poprawna wersja):\n";
    echo "  Sprawdzono kontaktów: $checked\n";
    echo "  Uzupełniono pole w: $updated\n";
}

// Wyświetl wynik
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Dodatkowe informacje w konsoli
if (php_sapi_name() === 'cli') {
    echo "\n\nStatus importu:\n";
    if ($httpCode === 200 && isset($result['response']['data']['success']) && $result['response']['data']['success']) {
        $res = $result['response']['data']['results'];
        echo "✓ Import zakończony sukcesem\n";
        echo "  Zaimportowano: {$res['imported']}\n";
        echo "  Zaktualizowano: {$res['updated']}\n";
        echo "  Pominięto: {$res['skipped']}\n";
        echo "  Łącznie w GetResponse: {$res['total_getresponse']}\n";
        echo "  Łącznie w Bitrix24: {$res['total_bitrix']}\n";
    } else {
        echo "✗ Błąd importu\n";
        echo "  Kod HTTP: $httpCode\n";
        if ($curlError) {
            echo "  Błąd cURL: $curlError\n";
        }
        if (isset($result['response']['data']['error'])) {
            echo "  Błąd API: {$result['response']['data']['error']}\n";
        }
    }
} 