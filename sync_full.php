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

$importLimit = ($status === 'trial') ? 100 : 100000;

try {
    // --- Pobierz kontakty z Bitrix24 ---
    $bitrixContacts = [];
    $bitrixByEmail = [];
    $start = 0;
    do {
        $params = [
            'filter' => ['HAS_EMAIL' => 'Y'],
            'select' => ['ID', 'NAME', 'EMAIL', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION'],
            'order' => ['ID' => 'ASC'],
            'start' => $start
        ];
        $result = CRest::call('crm.contact.list', $params);
        if (!empty($result['result'])) {
            foreach ($result['result'] as $c) {
                $bitrixContacts[] = $c;
                $emails = $c['EMAIL'] ?? [];
                foreach ($emails as $em) {
                    $email = strtolower($em['VALUE']);
                    if ($email) {
                        $bitrixByEmail[$email] = $c;
                    }
                }
            }
            $start = $result['next'] ?? 0;
        } else {
            break;
        }
    } while ($start > 0 && count($bitrixContacts) < $importLimit);

    // --- Pobierz kontakty z GetResponse ---
    $getResponseContacts = [];
    $grByEmail = [];
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
                foreach ($contacts as $contact) {
                    $email = strtolower($contact['email'] ?? '');
                    if ($email) {
                        $grByEmail[$email] = $contact;
                        $getResponseContacts[] = $contact;
                    }
                }
                if (count($getResponseContacts) >= $importLimit) {
                    $getResponseContacts = array_slice($getResponseContacts, 0, $importLimit);
                    break;
                }
                $page++;
                usleep(500000);
            } else {
                break;
            }
        } else {
            break;
        }
    } while (true);

    $addedToGR = 0;
    $updatedOriginInB24 = 0;
    $addedToB24 = 0;
    $updatedOriginInB24FromGR = 0;
    $errors = [];

    // --- Bitrix24 -> GetResponse ---
    foreach ($bitrixByEmail as $email => $b24) {
        if (isset($grByEmail[$email])) {
            // Kontakt istnieje w GR, uzupełnij ORIGIN_* w Bitrix24
            $contactGR = $grByEmail[$email];
            $fieldsToUpdate = [];
            if (empty($b24['ORIGIN_ID']) || $b24['ORIGIN_ID'] !== $contactGR['contactId']) {
                $fieldsToUpdate['ORIGIN_ID'] = $contactGR['contactId'];
            }
            if (empty($b24['ORIGINATOR_ID']) || $b24['ORIGINATOR_ID'] !== 'getresponse') {
                $fieldsToUpdate['ORIGINATOR_ID'] = 'getresponse';
            }
            $fieldsToUpdate['ORIGIN_VERSION'] = date('Y-m-d H:i:s');
            if (!empty($fieldsToUpdate)) {
                $res = CRest::call('crm.contact.update', [
                    'id' => $b24['ID'],
                    'fields' => $fieldsToUpdate
                ]);
                if (!empty($res['error'])) {
                    $errors[] = ['email' => $email, 'error' => $res['error']];
                } else {
                    $updatedOriginInB24++;
                }
            }
        } else {
            // Kontakt nie istnieje w GR, utwórz go
            $payload = [
                'email' => $email,
                'name' => $b24['NAME'] ?? '',
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
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($httpCode === 202 || $httpCode === 200) {
                $contactGR = json_decode($response, true);
                $contactId = $contactGR['contactId'] ?? null;
                if ($contactId) {
                    $fieldsToUpdate = [
                        'ORIGIN_ID' => $contactId,
                        'ORIGINATOR_ID' => 'getresponse',
                        'ORIGIN_VERSION' => date('Y-m-d H:i:s')
                    ];
                    $res = CRest::call('crm.contact.update', [
                        'id' => $b24['ID'],
                        'fields' => $fieldsToUpdate
                    ]);
                    if (!empty($res['error'])) {
                        $errors[] = ['email' => $email, 'error' => $res['error']];
                    }
                }
                $addedToGR++;
            } else {
                $errors[] = ['email' => $email, 'error' => $curlError ?: $response];
            }
            usleep(200000);
        }
    }

    // --- GetResponse -> Bitrix24 ---
    foreach ($grByEmail as $email => $contactGR) {
        if (!isset($bitrixByEmail[$email])) {
            // Kontakt nie istnieje w Bitrix24, utwórz go
            $fields = [
                'NAME' => $contactGR['name'] ?? '',
                'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                'ORIGIN_ID' => $contactGR['contactId'],
                'ORIGINATOR_ID' => 'getresponse',
                'ORIGIN_VERSION' => date('Y-m-d H:i:s')
            ];
            $res = CRest::call('crm.contact.add', [
                'fields' => $fields
            ]);
            if (!empty($res['result'])) {
                $addedToB24++;
            } else {
                $errors[] = ['email' => $email, 'error' => $res['error'] ?? 'Unknown error'];
            }
            usleep(200000);
        }
    }

    $results = [
        'added_to_gr' => $addedToGR,
        'updated_origin_in_b24' => $updatedOriginInB24,
        'added_to_b24' => $addedToB24,
        'errors' => $errors,
        'total_bitrix' => count($bitrixByEmail),
        'total_gr' => count($grByEmail)
    ];

    $cosmos->insert('import_logs', [
        'id' => uniqid('syncfull_'),
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