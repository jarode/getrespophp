<?php
require_once 'crest.php';
require_once 'cosmos.php';

header('Content-Type: application/json');
$domain = $_REQUEST['DOMAIN'] ?? null;
if (!$domain) {
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

$cosmos = new CosmosDB();

$result = CRest::call('crm.contact.list', [
    'select' => ['ID', 'EMAIL', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION', 'NAME'],
    'order' => ['ID' => 'ASC'],
    'start' => 0
]);

// Add logging for Bitrix24 API response
$cosmos->insert('api_logs', [
    'id' => uniqid('b24_'),
    'domain' => $domain,
    'endpoint' => 'bitrix_contacts',
    'request' => json_encode(['select' => ['ID', 'EMAIL', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION', 'NAME']]),
    'response' => json_encode($result),
    'timestamp' => date('Y-m-d H:i:s')
]);

function getPrimaryEmail($emails) {
    foreach ($emails as $em) {
        if (
            isset($em['TYPE_ID'], $em['VALUE_TYPE'], $em['VALUE']) &&
            $em['TYPE_ID'] === 'EMAIL' &&
            $em['VALUE_TYPE'] === 'WORK' &&
            !empty($em['VALUE'])
        ) {
            return strtolower($em['VALUE']);
        }
    }
    foreach ($emails as $em) {
        if (!empty($em['VALUE'])) {
            return strtolower($em['VALUE']);
        }
    }
    return null;
}

$map = [];
foreach ($result['result'] as $c) {
    $emails = $c['EMAIL'] ?? [];
    $email = getPrimaryEmail($emails);
    if ($email) {
        $map[$email] = [
            'ID' => $c['ID'],
            'NAME' => $c['NAME'],
            'ORIGIN_ID' => $c['ORIGIN_ID'],
            'ORIGINATOR_ID' => $c['ORIGINATOR_ID'],
            'ORIGIN_VERSION' => $c['ORIGIN_VERSION']
        ];
    }
}

// Add logging for mapping results
$cosmos->insert('api_logs', [
    'id' => uniqid('map_'),
    'domain' => $domain,
    'endpoint' => 'email_mapping',
    'request' => json_encode(['count' => count($result['result'])]),
    'response' => json_encode($map),
    'timestamp' => date('Y-m-d H:i:s')
]);

echo json_encode([
    'success' => true,
    'count' => count($map),
    'map' => $map
]); 