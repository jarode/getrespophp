<?php
// bitrix_debug.php
$webhook = 'https://b24-5xjk9p.bitrix24.com/rest/1/g1l47he2wqigay60/';
$method = $_GET['method'] ?? 'crm.contact.list';
$params = $_GET['params'] ?? [
    'filter' => ['HAS_EMAIL' => 'Y'],
    'select' => ['ID', 'NAME', 'EMAIL'],
    'order' => ['ID' => 'ASC'],
    'start' => 0
];

if (is_string($params)) {
    $params = json_decode($params, true);
}

$url = $webhook . $method . '.json';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode([
    'request' => [
        'url' => $url,
        'params' => $params
    ],
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response' => json_decode($response, true)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 