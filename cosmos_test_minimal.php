<?php
// Minimalny test zapisu dokumentu do Azure Cosmos DB REST API
// Ustaw poniższe dane zgodnie z Twoją konfiguracją:
$endpoint = 'https://bitrixusersdb.documents.azure.com:443/';
$key = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
$database = 'bitrixapp';
$container = 'subscriptions';
$partitionKey = 'test-domain-' . uniqid();

// Funkcja do generowania tokena autoryzacji (z dokumentacji MS)
function build_auth_token($verb, $resourceType, $resourceLink, $date, $key, $keyType = 'master', $tokenVersion = '1.0') {
    $stringToSign = strtolower($verb) . "\n" .
                    strtolower($resourceType) . "\n" .
                    $resourceLink . "\n" .
                    strtolower($date) . "\n\n";
    $decodedKey = base64_decode($key);
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));
    return urlencode("type={$keyType}&ver={$tokenVersion}&sig={$signature}");
}

$resourceLink = "dbs/$database/colls/$container";
$date = gmdate('D, d M Y H:i:s T');
$token = build_auth_token('POST', 'docs', $resourceLink, $date, $key);

$document = [
    'id' => uniqid(),
    'domain' => $partitionKey,
    'test_field' => 'test_value',
    'created_at' => date('c')
];

$headers = [
    'Authorization: ' . $token,
    'x-ms-date: ' . $date,
    'x-ms-version: 2018-12-31',
    'Content-Type: application/json',
    'x-ms-documentdb-partitionkey: ["' . $partitionKey . '"]'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint . $resourceLink . '/docs');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "== Cosmos DB Minimal Test ==\n";
echo "ResourceLink: $resourceLink\n";
echo "Date: $date\n";
echo "Token: $token\n";
echo "PartitionKey: $partitionKey\n";
echo "Headers:\n" . print_r($headers, true) . "\n";
echo "Request body:\n" . json_encode($document, JSON_PRETTY_PRINT) . "\n";
echo "[DEBUG][WRITE] HTTP code: $code\n";
echo "[DEBUG][WRITE] Response: $response\n";
if ($curlError) echo "[DEBUG][WRITE] CURL error: $curlError\n";

echo "== Test zakończony ==\n"; 