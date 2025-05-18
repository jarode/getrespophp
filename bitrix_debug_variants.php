<?php
// bitrix_debug_variants.php
// Testuje różne warianty zapytań crm.contact.list przez lokalną klasę CRest

require_once 'crest.php';

$variants = [
    'ID only' => [
        'select' => ['ID'],
        'filter' => [],
        'order' => ['ID' => 'ASC'],
        'start' => 0
    ],
    'ID + automation' => [
        'select' => ['ID', 'UF_CRM_EMAIL_SYNC_AUTOMATION'],
        'filter' => [],
        'order' => ['ID' => 'ASC'],
        'start' => 0
    ],
    'ID + automation, filter empty' => [
        'select' => ['ID', 'UF_CRM_EMAIL_SYNC_AUTOMATION'],
        'filter' => ['UF_CRM_EMAIL_SYNC_AUTOMATION' => ''],
        'order' => ['ID' => 'ASC'],
        'start' => 0
    ],
    'ID + NAME' => [
        'select' => ['ID', 'NAME'],
        'filter' => [],
        'order' => ['ID' => 'ASC'],
        'start' => 0
    ],
    'ID + automation, filter not empty' => [
        'select' => ['ID', 'UF_CRM_EMAIL_SYNC_AUTOMATION'],
        'filter' => ['!UF_CRM_EMAIL_SYNC_AUTOMATION' => ''],
        'order' => ['ID' => 'ASC'],
        'start' => 0
    ]
];

$results = [];
foreach ($variants as $label => $params) {
    $response = CRest::call('crm.contact.list', $params);
    $results[$label] = $response;
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Bitrix24 Debug Variants</title>
    <style>body{font-family:monospace;background:#f8f8f8;}pre{background:#fff;padding:1em;border:1px solid #ccc;}</style>
</head>
<body>
<h2>Bitrix24 Debug Variants (crm.contact.list)</h2>
<?php foreach ($results as $label => $response): ?>
    <h3><?= htmlspecialchars($label) ?></h3>
    <pre><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
<?php endforeach; ?>
</body>
</html> 