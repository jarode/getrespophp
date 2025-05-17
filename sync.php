<?php
require_once 'cosmos.php';
require_once 'crest.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$domain = $data['DOMAIN'] ?? ($_REQUEST['DOMAIN'] ?? null);

if (!$domain) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

$cosmos = new CosmosDB();
$settings = $cosmos->getSettings($domain);
$license = $cosmos->getLicenseStatus($domain);
$status = strtolower($license['license_status'] ?? 'trial');
$expiry = $license['license_expiry'] ?? null;
$today = date('Y-m-d');
if (!in_array($status, ['trial', 'active']) || ($expiry && $expiry < $today)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Synchronization is available only with an active license or during the trial period, and only if the license is valid.']);
    exit;
}

$apiKey = $settings['getresponse_api_key'] ?? '';
$listId = $settings['getresponse_list_id'] ?? '';
if (!$apiKey || !$listId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'GetResponse API Key and List ID are required.']);
    exit;
}

// --- Synchronizacja Bitrix24 -> GetResponse ---
$bitrixContacts = [];
$start = 0;
do {
    $batch = CRest::call('crm.contact.list', [
        'order' => ['ID' => 'ASC'],
        'filter' => ['!EMAIL' => false],
        'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL'],
        'start' => $start
    ]);
    if (!empty($batch['result'])) {
        $bitrixContacts = array_merge($bitrixContacts, $batch['result']);
    }
    $start = $batch['next'] ?? 0;
    sleep(1); // Bitrix24 API limit
} while (!empty($batch['result']) && $start > 0);

// Pobierz istniejące kontakty z GetResponse (wszystkie z danej listy)
$getResponseContacts = [];
$page = 1;
do {
    $ch = curl_init('https://api.getresponse.com/v3/contacts?query[campaignId]=' . urlencode($listId) . '&perPage=100&page=' . $page);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Auth-Token: api-key ' . $apiKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $contacts = json_decode($response, true);
        if (is_array($contacts) && count($contacts) > 0) {
            $getResponseContacts = array_merge($getResponseContacts, $contacts);
            $page++;
            usleep(500000); // 0.5s dla limitu GetResponse
        } else {
            break;
        }
    } else {
        break;
    }
} while (true);

$getResponseEmails = array_map(function($c) {
    return strtolower($c['email'] ?? '');
}, $getResponseContacts);

// Dodaj do GetResponse tylko te kontakty, których nie ma (po emailu)
$addedToGR = 0;
$skippedGR = 0;
foreach ($bitrixContacts as $contact) {
    $email = strtolower($contact['EMAIL'][0]['VALUE'] ?? '');
    if (!$email) continue;
    if (in_array($email, $getResponseEmails)) {
        $skippedGR++;
        continue;
    }
    // Dodaj kontakt do GetResponse
    $payload = [
        'email' => $email,
        'name' => $contact['NAME'] ?? '',
        'campaign' => ['campaignId' => $listId]
    ];
    $ch = curl_init('https://api.getresponse.com/v3/contacts');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Auth-Token: api-key ' . $apiKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 202) {
        $addedToGR++;
    }
    usleep(500000); // 0.5s dla limitu GetResponse
}

// --- Synchronizacja GetResponse -> Bitrix24 ---
// Pobierz wszystkie kontakty z Bitrix24 (emaile)
$bitrixEmails = array_map(function($c) {
    return strtolower($c['EMAIL'][0]['VALUE'] ?? '');
}, $bitrixContacts);

$addedToB24 = 0;
$skippedB24 = 0;
foreach ($getResponseContacts as $contact) {
    $email = strtolower($contact['email'] ?? '');
    if (!$email) continue;
    if (in_array($email, $bitrixEmails)) {
        $skippedB24++;
        continue;
    }
    // Dodaj kontakt do Bitrix24
    $payload = [
        'fields' => [
            'NAME' => $contact['name'] ?? '',
            'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']]
        ]
    ];
    $result = CRest::call('crm.contact.add', $payload);
    if (!empty($result['result'])) {
        $addedToB24++;
    }
    sleep(1); // Bitrix24 API limit
}

// Loguj podsumowanie
$logData = [
    'direction' => 'sync_both',
    'bitrix_to_gr_added' => $addedToGR,
    'bitrix_to_gr_skipped' => $skippedGR,
    'gr_to_bitrix_added' => $addedToB24,
    'gr_to_bitrix_skipped' => $skippedB24,
    'time' => date('c'),
    'source' => 'sync.php'
];
CosmosDB::insert($domain, $logData);

// Zwróć podsumowanie
echo json_encode([
    'success' => true,
    'message' => "Synchronization completed. Added to GetResponse: $addedToGR, skipped: $skippedGR. Added to Bitrix24: $addedToB24, skipped: $skippedB24."
]); 