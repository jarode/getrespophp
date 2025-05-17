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

// Ograniczenie liczby kontaktów w trialu
$importLimit = ($status === 'trial') ? 100 : 100000;

try {
    // --- Pobierz kontakty z GetResponse ---
    $getResponseContacts = [];
    $page = 1;
    do {
        $url = 'https://api.getresponse.com/v3/contacts?query[campaignId]=' . urlencode($listId) . '&perPage=100&page=' . $page;
        $ch = curl_init($url);
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
                if (count($getResponseContacts) >= $importLimit) {
                    $getResponseContacts = array_slice($getResponseContacts, 0, $importLimit);
                    break;
                }
                $page++;
                usleep(500000); // 0.5s dla limitu GetResponse
            } else {
                break;
            }
        } else {
            break;
        }
    } while (true);

    // --- Pobierz istniejące kontakty z Bitrix24 (po emailu) ---
    $bitrixEmails = [];
    $bitrixMap = [];
    $start = 0;
    do {
        $batch = CRest::call('crm.contact.list', [
            'order' => ['ID' => 'ASC'],
            'select' => ['ID', 'NAME', 'EMAIL', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION'],
            'filter' => ['HAS_EMAIL' => 'Y'],
            'start' => $start
        ]);
        if (!empty($batch['result'])) {
            foreach ($batch['result'] as $c) {
                $emails = $c['EMAIL'] ?? [];
                foreach ($emails as $em) {
                    $email = strtolower($em['VALUE']);
                    if ($email) {
                        $bitrixEmails[] = $email;
                        $bitrixMap[$email] = $c;
                    }
                }
            }
        }
        $start = $batch['next'] ?? 0;
        sleep(1);
    } while (!empty($batch['result']) && $start > 0);

    // --- Importuj kontakty z GetResponse do Bitrix24 ---
    $added = 0;
    $updated = 0;
    $skipped = 0;
    foreach ($getResponseContacts as $contact) {
        $email = strtolower($contact['email'] ?? '');
        if (!$email) continue;
        if (isset($bitrixMap[$email])) {
            // Kontakt istnieje w Bitrix24
            $b24 = $bitrixMap[$email];
            // Jeśli dane się zgadzają (np. imię), tylko uzupełnij ORIGIN_*
            $fieldsToUpdate = [];
            if (empty($b24['ORIGIN_ID']) || $b24['ORIGIN_ID'] !== $contact['contactId']) {
                $fieldsToUpdate['ORIGIN_ID'] = $contact['contactId'];
            }
            if (empty($b24['ORIGINATOR_ID']) || $b24['ORIGINATOR_ID'] !== 'getresponse') {
                $fieldsToUpdate['ORIGINATOR_ID'] = 'getresponse';
            }
            $fieldsToUpdate['ORIGIN_VERSION'] = date('Y-m-d H:i:s');
            if (!empty($fieldsToUpdate)) {
                CRest::call('crm.contact.update', [
                    'id' => $b24['ID'],
                    'fields' => $fieldsToUpdate
                ]);
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            // Kontakt nie istnieje – dodaj
            $result = CRest::call('crm.contact.add', [
                'fields' => [
                    'NAME' => $contact['name'] ?? '',
                    'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                    'ORIGIN_ID' => $contact['contactId'],
                    'ORIGINATOR_ID' => 'getresponse',
                    'ORIGIN_VERSION' => date('Y-m-d H:i:s')
                ]
            ]);
            if (!empty($result['result'])) {
                $added++;
            } else {
                $skipped++;
            }
        }
        usleep(200000); // 0.2s dla limitu API
    }

    $results = [
        'imported' => $added,
        'updated' => $updated,
        'skipped' => $skipped,
        'total_getresponse' => count($getResponseContacts),
        'total_bitrix' => count($bitrixMap)
    ];

    // Zapisz logi do CosmosDB
    $cosmos->insert('import_logs', [
        'id' => uniqid('import_'),
        'domain' => $domain,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 