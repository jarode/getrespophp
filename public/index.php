<?php
file_put_contents('log.txt', "START " . date('c') . "\n", FILE_APPEND);

$input = file_get_contents('php://input');
file_put_contents('log.txt', $input . "\n", FILE_APPEND);

$data = json_decode($input, true);
$fields = $data['data']['FIELDS'] ?? null;

if (!$fields || !isset($fields['EMAIL'][0]['VALUE'])) {
    file_put_contents('log.txt', "Brak email\n", FILE_APPEND);
    http_response_code(400);
    echo 'Brak email';
    exit;
}

$name = $fields['NAME'] ?? '';
$email = $fields['EMAIL'][0]['VALUE'];

file_put_contents('log.txt', "EMAIL = $email\n", FILE_APPEND);

$apiKey = '62a96f1wzus8pp7s6o83s233j2to908k'; // upewnij się, że działa
$campaignId = 'id0Rg'; // poprawne?

$payload = json_encode([
    'name' => $name,
    'email' => $email,
    'campaign' => [ 'campaignId' => $campaignId ]
]);

$ch = curl_init('https://api.getresponse.com/v3/contacts');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "X-Auth-Token: api-key $apiKey"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

file_put_contents('log.txt', "HTTP $httpCode\n$response\n", FILE_APPEND);
curl_close($ch);

echo "Wysłano: HTTP $httpCode";
