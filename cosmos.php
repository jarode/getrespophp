<?php
function cosmos_add_user($userData) {
    $endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw=='; // PRIMARY KEY
    $databaseId = 'bitrixapp';
    $containerId = 'users';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $verb = 'POST';
    $resourceType = 'docs';
    $utcDate = gmdate('D, d M Y H:i:s T');
    $token = build_auth_token($verb, $resourceType, $resourceLink, $utcDate, $key);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-ms-date: ' . $utcDate,
        'x-ms-version: 2018-12-31',
        'Authorization: ' . $token
    ];

    $userData['id'] = uniqid(); // Cosmos DB requires a unique 'id' for each document

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

function cosmos_get_by_domain($domain) {
    $endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw=='; // Zmienna środowiskowa lub wpisz na sztywno
    $databaseId = 'bitrixapp';
    $containerId = 'users';
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
        'x-ms-version: 2018-12-31',
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
    $existing = cosmos_get_by_domain($domain);
    if (!$existing) return false;

    $id = $existing['id'];
    $docLink = "dbs/bitrixapp/colls/users/docs/{$id}";
    $endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
    $url = $endpoint . $docLink;
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';

    $headers = [
        'Content-Type: application/json',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T'),
        'x-ms-version: 2018-12-31',
        'x-ms-documentdb-partitionkey' => '["' . $domain . '"]',
        'Authorization: ' . build_auth_token('PUT', 'docs', $docLink, gmdate('D, d M Y H:i:s T'), $key)
    ];

    $merged = array_merge($existing, $fields);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($merged));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . '/logs/cosmos_update_' . time() . '.json', json_encode([
        'updated' => $merged,
        'response' => $response
    ], JSON_PRETTY_PRINT));

    return true;
}

function cosmos_insert($data) {
    $endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
    $key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    $databaseId = 'bitrixapp';
    $containerId = 'users';
    $resourceLink = "dbs/{$databaseId}/colls/{$containerId}";
    $url = $endpoint . $resourceLink . '/docs';

    $headers = [
        'Content-Type: application/json',
        'x-ms-date: ' . gmdate('D, d M Y H:i:s T'),
        'x-ms-version' => '2018-12-31',
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

?>
