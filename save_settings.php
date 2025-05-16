<?php
require_once 'settings.php';
require_once 'cosmos.php';

header('Content-Type: application/json');

// Sprawdź czy to jest żądanie POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Pobierz dane z żądania
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Sprawdź wymagane pola
if (empty($data['DOMAIN']) || empty($data['getresponse_api_key']) || empty($data['getresponse_list_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Zapisz ustawienia w CosmosDB
    $cosmos = new CosmosDB();
    $result = $cosmos->saveSettings($data['DOMAIN'], [
        'getresponse_api_key' => $data['getresponse_api_key'],
        'getresponse_list_id' => $data['getresponse_list_id']
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 