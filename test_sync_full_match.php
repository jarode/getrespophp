<?php
require_once 'crest.php';

header('Content-Type: application/json');
$domain = $_REQUEST['DOMAIN'] ?? null;
if (!$domain) {
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

$result = CRest::call('crm.contact.list', [
    'select' => ['ID', 'EMAIL', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION', 'NAME'],
    'order' => ['ID' => 'ASC'],
    'start' => 0
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

echo json_encode([
    'success' => true,
    'count' => count($map),
    'map' => $map
]); 