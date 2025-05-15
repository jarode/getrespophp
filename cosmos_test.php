<?php
require_once __DIR__ . '/cosmos.php';

header('Content-Type: text/plain; charset=utf-8');
echo "== Cosmos DB Test (debug) ==\n";

$testDomain = 'test-domain-' . uniqid();
$testData = [
    'domain' => $testDomain,
    'test_field' => 'test_value',
    'created_at' => date('c'),
    'source' => 'cosmos_test.php'
];

function debug_curl_update($domain, $fields) {
    $resourceLink = "dbs/" . CosmosDB::DATABASE . "/colls/" . CosmosDB::CONTAINER_SUBSCRIPTIONS;
    $existing = CosmosDB::get_by_domain($domain);
    $document = [
        'id' => $existing['id'] ?? uniqid(),
        'domain' => $domain,
        'updated_at' => date('c')
    ];
    $document = array_merge($document, $fields);
    $utcDate = gmdate('D, d M Y H:i:s T');
    $token = CosmosDB::build_auth_token($existing ? 'PUT' : 'POST', 'docs', $resourceLink, $utcDate, CosmosDB::KEY);
    $headers = [
        'Content-Type: application/json',
        'x-ms-date: ' . $utcDate,
        'x-ms-version: 2023-11-15',
        'x-ms-documentdb-partitionkey' => '["' . $domain . '"]',
        'Authorization: ' . $token
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, CosmosDB::ENDPOINT . $resourceLink . '/docs' . ($existing ? '/' . $existing['id'] : ''));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $existing ? 'PUT' : 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    echo "[DEBUG][WRITE] HTTP code: $code\n";
    echo "[DEBUG][WRITE] Response: $response\n";
    if ($curlError) echo "[DEBUG][WRITE] CURL error: $curlError\n";
    return $code >= 200 && $code < 300;
}

function debug_curl_get($domain) {
    $resourceLink = "dbs/" . CosmosDB::DATABASE . "/colls/" . CosmosDB::CONTAINER_SUBSCRIPTIONS;
    $query = "SELECT * FROM c WHERE c.domain = @domain";
    $params = [['name' => '@domain', 'value' => $domain]];
    $utcDate = gmdate('D, d M Y H:i:s T');
    $token = CosmosDB::build_auth_token('POST', 'docs', $resourceLink, $utcDate, CosmosDB::KEY);
    $headers = [
        'Content-Type: application/query+json',
        'x-ms-documentdb-isquery: true',
        'x-ms-date: ' . $utcDate,
        'x-ms-version: 2023-11-15',
        'Authorization: ' . $token
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, CosmosDB::ENDPOINT . $resourceLink . '/docs');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'query' => $query,
        'parameters' => $params
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    echo "[DEBUG][READ] HTTP code: $code\n";
    echo "[DEBUG][READ] Response: $response\n";
    if ($curlError) echo "[DEBUG][READ] CURL error: $curlError\n";
    if ($code >= 200 && $code < 300) {
        $result = json_decode($response, true);
        return $result['Documents'][0] ?? null;
    }
    return null;
}

try {
    // Test zapis
    $writeResult = debug_curl_update($testDomain, $testData);
    if ($writeResult) {
        echo "[OK] Zapis testowego dokumentu powiódł się.\n";
    } else {
        echo "[ERROR] Zapis testowego dokumentu NIE powiódł się!\n";
    }

    // Test odczyt
    $readResult = debug_curl_get($testDomain);
    if ($readResult && isset($readResult['test_field']) && $readResult['test_field'] === 'test_value') {
        echo "[OK] Odczyt testowego dokumentu powiódł się.\n";
        echo "Odczytane dane: " . print_r($readResult, true) . "\n";
    } else {
        echo "[ERROR] Odczyt testowego dokumentu NIE powiódł się lub dane nie są zgodne!\n";
        echo "Odczytane dane: " . print_r($readResult, true) . "\n";
    }
} catch (Exception $e) {
    echo "[EXCEPTION] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "== Test zakończony ==\n"; 