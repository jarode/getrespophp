<?php
require_once(__DIR__.'/crest.php');

$input = file_get_contents('php://input');
file_put_contents('log.txt', date('c') . "\n" . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);
$fields = $data['data']['FIELDS'] ?? null;

if (!$fields || !isset($fields['EMAIL'][0]['VALUE'])) {
    file_put_contents('log.txt', "Brak danych e-mail\n", FILE_APPEND);
    http_response_code(400);
    echo 'Brak e-maila';
    exit;
}

$name = $fields['NAME'] ?? '';
$email = $fields['EMAIL'][0]['VALUE'];

file_put_contents('log.txt', "Got email: $email\n", FILE_APPEND);

$apiKey = '62a96f1wzus8pp7s6o83s233j2to908k';
$campaignId = 'id0Rg';

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
curl_close($ch);

file_put_contents('log.txt', "Response [$httpCode]: $response\n", FILE_APPEND);

echo 'OK';
