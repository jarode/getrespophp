<?php
require_once 'cosmos.php';
header('Content-Type: application/json');

// Sprawdź czy to jest żądanie POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$apiKey = $data['api_key'] ?? '';
$domain = $data['domain'] ?? 'unknown';
if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'API Key is required']);
    // Loguj próbę bez klucza
    CosmosDB::insert($domain, [
        'log_type' => 'getresponse_lists',
        'log_time' => date('c'),
        'source' => 'get_gr_lists.php',
        'request' => ['api_key' => 'empty'],
        'error' => 'API Key is required'
    ]);
    exit;
}

// Pobierz listy (kampanie) z GetResponse
$ch = curl_init('https://api.getresponse.com/v3/campaigns');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Auth-Token: api-key ' . $apiKey
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Loguj każde wywołanie
CosmosDB::insert($domain, [
    'log_type' => 'getresponse_lists',
    'log_time' => date('c'),
    'source' => 'get_gr_lists.php',
    'request' => ['api_key' => substr($apiKey, 0, 6) . '...'],
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response' => $response
]);

if ($httpCode !== 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'GetResponse API error: ' . $curlError . ' (HTTP ' . $httpCode . ')', 'response' => $response]);
    exit;
}

$lists = json_decode($response, true);
if (!is_array($lists)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid response from GetResponse API']);
    exit;
}

// Zwróć tylko id i name
$result = array_map(function($item) {
    return [
        'id' => $item['campaignId'] ?? '',
        'name' => $item['name'] ?? ''
    ];
}, $lists);

echo json_encode(['success' => true, 'lists' => $result]); 