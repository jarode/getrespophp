<?php
require_once 'crest.php';

header('Content-Type: application/json');
$domain = $_REQUEST['DOMAIN'] ?? null;
$action = $_REQUEST['action'] ?? '';

if (!$domain) {
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

if ($action === 'get_first') {
    $result = CRest::call('crm.contact.list', [
        'select' => ['ID', 'NAME', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION'],
        'order' => ['ID' => 'ASC'],
        'start' => 0,
        'limit' => 1
    ]);
    if (!empty($result['result'][0])) {
        echo json_encode(['success' => true, 'contact' => $result['result'][0]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No contact found']);
    }
    exit;
}

if ($action === 'update') {
    $id = $_REQUEST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Contact ID required']);
        exit;
    }
    $fields = [
        'ORIGIN_ID' => 'TEST_' . rand(1000,9999),
        'ORIGINATOR_ID' => 'test_originator_' . rand(100,999),
        'ORIGIN_VERSION' => date('Y-m-d H:i:s')
    ];
    $update = CRest::call('crm.contact.update', [
        'id' => $id,
        'fields' => $fields
    ]);
    // Pobierz ponownie kontakt
    $after = CRest::call('crm.contact.get', ['ID' => $id]);
    echo json_encode([
        'success' => true,
        'update_response' => $update,
        'contact_after' => $after['result'] ?? null
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']); 