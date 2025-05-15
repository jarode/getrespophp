<?php
function cosmos_add_user($userData) {
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    $databaseId = 'bitrixapp';
    $containerId = 'subscriptions';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $utcDate = gmdate('D, d M Y H:i:s T');
    $token = build_auth_token('POST', 'docs', $resourceLink, $utcDate, $key);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-ms-date: ' . $utcDate,
        'x-ms-version: ' . '2023-11-15',
        'Authorization: ' . $token
    ];

    $userData['id'] = uniqid();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents(__DIR__ . '/logs/cosmos_adduser_' . time() . '.json', json_encode([
        'payload' => $userData,
        'code' => $code,
        'response' => $response
    ], JSON_PRETTY_PRINT));
}

function build_auth_token($verb, $resourceType, $resourceLink, $utcDate, $key, $keyType = 'master', $tokenVersion = '1.0') {
    $stringToSign = strtolower($verb) . "\n" .
                    strtolower($resourceType) . "\n" .
                    $resourceLink . "\n" .
                    strtolower($utcDate) . "\n\n";

    $decodedKey = base64_decode($key);
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

    return urlencode("type={$keyType}&ver={$tokenVersion}&sig={$signature}");
}

// analogicznie zaktualizuj w pozostałych funkcjach: cosmos_get_by_domain, cosmos_update, cosmos_insert
// zamieniając containerId z 'subscriptions' na 'subscriptions'

function cosmos_get_by_domain($domain) {
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw=='; // Zmienna środowiskowa lub wpisz na sztywno
    $databaseId = 'bitrixapp';
    $containerId = 'subscriptions';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $query = [
        'query' => 'SELECT * FROM c WHERE c.domain = @domain',
        'parameters' => [
            ['name' => '@domain', 'value' => $domain]
        ]
    ];

    $headers = [
        'Content-Type: application/query+json',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T'),
        'x-ms-version: 2023-11-15',
        'x-ms-documentdb-isquery' => 'true',
        'x-ms-documentdb-query-enablecrosspartition' => 'true',
        'Authorization: ' . build_auth_token('POST', 'docs', $resourceLink, gmdate('D, d M Y H:i:s T'), $key)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['Documents'][0] ?? [];
}

function cosmos_update($domain, $fields) {
    if (!$domain) {
        throw new Exception("Domain is required for Cosmos update.");
    }

    $existing = cosmos_get_by_domain($domain);
    $id = $existing['id'] ?? uniqid();

    $docLink = "dbs/bitrixapp/colls/subscriptions/docs/{$id}";
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';

    $timestamp = gmdate('D, d M Y H:i:s T');
    $authToken = build_auth_token('PUT', 'docs', $docLink, $timestamp, $key);

    $headers = [
        'Content-Type: application/json',
        'x-ms-date: ' . $timestamp,
        'x-ms-version: ' . '2023-11-15',
        'x-ms-documentdb-partitionkey' => '["' . $domain . '"]',
        'x-ms-documentdb-is-upsert: true',
        'Authorization: ' . $authToken
    ];

    // Merging previous + current fields
    $merged = array_merge($existing ?? [], $fields);
    $merged['id'] = $id;
    $merged['domain'] = $domain;
    $merged['updated_at'] = date('c');

    // Jeśli to nowa instalacja, dodaj timestamp
    if (!isset($existing['app_installed']) && isset($fields['app_installed'])) {
        $merged['app_installed'] = $fields['app_installed'];
    }

    $url = $endpoint . $docLink;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($merged));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log do pliku (opcjonalnie — można zastąpić cosmos_log())
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    file_put_contents($logDir . '/cosmos_update_' . time() . '.json', json_encode([
        'domain' => $domain,
        'payload' => $merged,
        'response' => $response,
        'status' => $httpCode
    ], JSON_PRETTY_PRINT));

    return $httpCode >= 200 && $httpCode < 300;
}

function cosmos_insert($data) {
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    $databaseId = 'bitrixapp';
    $containerId = 'subscriptions';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $headers = [
        'Content-Type: application/json',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T'),
        'x-ms-version' => '2023-11-15',
        'x-ms-documentdb-is-upsert' => 'true',
        'x-ms-documentdb-partitionkey' => '["' . $data['domain'] . '"]',
        'Authorization: ' . build_auth_token('POST', 'docs', $resourceLink, gmdate('D, d M Y H:i:s T'), $key)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . '/logs/cosmos_insert_' . time() . '.json', json_encode([
        'inserted' => $data,
        'response' => $response
    ], JSON_PRETTY_PRINT));
}

function cosmos_add_log($logData) {
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    $databaseId = 'bitrixapp';
    $containerId = 'logs';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $utcDate = gmdate('D, d M Y H:i:s T');
    $token = build_auth_token('POST', 'docs', $resourceLink, $utcDate, $key);

    // Przygotuj dane logu
    $logDocument = [
        'id' => uniqid(),
        'timestamp' => date('c'),
        'domain' => $logData['domain'] ?? 'unknown',
        'type' => $logData['type'] ?? 'general',
        'data' => $logData['data'] ?? [],
        'status' => $logData['status'] ?? 'success',
        'metadata' => [
            'source' => $logData['metadata']['source'] ?? 'system',
            'action' => $logData['metadata']['action'] ?? 'log'
        ]
    ];

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-ms-date: ' . $utcDate,
        'x-ms-version: ' . '2023-11-15',
        'x-ms-documentdb-partitionkey' => '["' . $logDocument['domain'] . '"]',
        'Authorization: ' . $token
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($logDocument));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $code >= 200 && $code < 300,
        'code' => $code,
        'response' => $response
    ];
}

function cosmos_get_logs($domain, $options = []) {
    $endpoint = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    $databaseId = 'bitrixapp';
    $containerId = 'logs';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    // Przygotuj zapytanie
    $query = [
        'query' => 'SELECT * FROM c WHERE c.domain = @domain',
        'parameters' => [
            ['name' => '@domain', 'value' => $domain]
        ]
    ];

    // Dodaj opcjonalne filtry
    if (!empty($options['type'])) {
        $query['query'] .= ' AND c.type = @type';
        $query['parameters'][] = ['name' => '@type', 'value' => $options['type']];
    }

    if (!empty($options['start_date'])) {
        $query['query'] .= ' AND c.timestamp >= @start_date';
        $query['parameters'][] = ['name' => '@start_date', 'value' => $options['start_date']];
    }

    if (!empty($options['end_date'])) {
        $query['query'] .= ' AND c.timestamp <= @end_date';
        $query['parameters'][] = ['name' => '@end_date', 'value' => $options['end_date']];
    }

    // Sortowanie
    $query['query'] .= ' ORDER BY c.timestamp DESC';

    // Limit wyników
    if (!empty($options['limit'])) {
        $query['query'] .= ' OFFSET 0 LIMIT @limit';
        $query['parameters'][] = ['name' => '@limit', 'value' => (int)$options['limit']];
    }

    $headers = [
        'Content-Type: application/query+json',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T'),
        'x-ms-version: 2023-11-15',
        'x-ms-documentdb-isquery' => 'true',
        'x-ms-documentdb-query-enablecrosspartition' => 'true',
        'Authorization: ' . build_auth_token('POST', 'docs', $resourceLink, gmdate('D, d M Y H:i:s T'), $key)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['Documents'] ?? [];
}

?>
