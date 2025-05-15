<?php
require_once __DIR__ . '/crest.php';

// Ręczne ustawienie zmiennych środowiskowych (jeśli nie używasz .env lub Azure App Settings)
putenv('COSMOS_DB_ENDPOINT=https://bitrixsubscriptionsdb.documents.azure.com:443/');
putenv('odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw=='); // 🚨 Zmienna tymczasowa tylko do testu



// 🔧 Testowe dane domeny i użytkownika
$testDomain = 'test.bitrix24.pl';

try {
    echo "🔍 Sprawdzanie czy rekord już istnieje...\n";
    $existing = cosmos_get_by_domain($testDomain);

    if ($existing) {
        echo "✅ Istniejący rekord:\n";
        print_r($existing);
    } else {
        echo "ℹ️ Brak istniejącego rekordu, tworzymy nowy...\n";
    }

    echo "📤 Wysyłanie danych testowych do Cosmos DB...\n";

    $success = cosmos_update($testDomain, [
        'member_id' => 'testmember123',
        'access_token' => 'ACCESS_TOKEN_SAMPLE',
        'refresh_token' => 'REFRESH_TOKEN_SAMPLE',
        'client_endpoint' => 'https://' . $testDomain . '/rest/',
        'application_token' => 'APP_TOKEN_SAMPLE',
        'custom_field' => 'Test data inserted at ' . date('c'),
        'manual_test' => true
    ]);

    if ($success) {
        echo "✅ Aktualizacja Cosmos DB zakończona sukcesem.\n";
    } else {
        echo "❌ Aktualizacja nie powiodła się.\n";
    }

    echo "📥 Odczyt zaktualizowanego rekordu:\n";
    $fetched = cosmos_get_by_domain($testDomain);
    print_r($fetched);

} catch (Exception $e) {
    echo "❌ Błąd podczas testu: " . $e->getMessage() . "\n";
}
