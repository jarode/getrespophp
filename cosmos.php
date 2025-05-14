<?php
function cosmos_add_user($userData) {
    $endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
    $key = getenv('COSMOS_PRIMARY_KEY'); // PRIMARY KEY
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
?>
