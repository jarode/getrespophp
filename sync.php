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

// Odczytaj opcje synchronizacji z requestu
$syncNewOnly = $data['syncNewOnly'] ?? false;
$updateExisting = $data['updateExisting'] ?? false;
$filterTag = $data['filterTag'] ?? false;
$syncLimit = isset($data['syncLimit']) ? (int)$data['syncLimit'] : 1000;
$conflictRule = $data['conflictRule'] ?? 'bitrix';

// Wymuś ograniczenia trial/PRO
if ($status === 'trial') {
    $syncNewOnly = false;
    $updateExisting = false;
    $filterTag = false;
    $syncLimit = 20;
    $conflictRule = 'bitrix';
}

try {
    // --- Pobierz kontakty z Bitrix24 (optymalnie) ---
    $allContacts = [];
    $start = 0;
    do {
        $batch = CRest::call('crm.contact.list', [
            'order' => ['ID' => 'ASC'],
            'filter' => ['HAS_EMAIL' => 'Y'],
            'select' => ['ID', 'ORIGIN_ID', 'ORIGINATOR_ID', 'ORIGIN_VERSION', 'DATE_MODIFY'],
            'start' => $start
        ]);
        if (!empty($batch['result'])) {
            $allContacts = array_merge($allContacts, $batch['result']);
        }
        $start = $batch['next'] ?? 0;
        sleep(1);
    } while (!empty($batch['result']) && $start > 0);

    // Zbuduj mapy kontaktów po ORIGIN_ID i email (fallback)
    $bitrixContactsByOriginId = [];
    $bitrixContactsByEmail = [];
    $contactsToFetch = [];
    foreach ($allContacts as $contact) {
        if (!empty($contact['ORIGIN_ID'])) {
            $bitrixContactsByOriginId[$contact['ORIGIN_ID']] = $contact;
        }
        // Zaznacz do pobrania szczegółów, jeśli nie ma ORIGIN_ID lub wymaga eksportu
        $contactsToFetch[] = $contact['ID'];
    }

    // Pobierz szczegóły tylko dla kontaktów do synchronizacji (np. do eksportu lub porównania)
    $bitrixContacts = [];
    foreach ($contactsToFetch as $contactId) {
        $details = CRest::call('crm.contact.get', ['ID' => $contactId]);
        $c = $details['result'] ?? [];
        $emails = $c['EMAIL'] ?? [];
        if (!empty($emails)) {
            foreach ($emails as $em) {
                $email = strtolower($em['VALUE']);
                if ($email) {
                    $bitrixContactsByEmail[$email] = $c;
                }
            }
            $bitrixContacts[] = $c;
        }
        usleep(200000); // 0.2s dla limitu API
    }

    // Zapisz liczbę kontaktów w Bitrix24 przed synchronizacją
    $bitrixCountBefore = count($bitrixContacts);

    // Pobierz istniejące kontakty z GetResponse (wszystkie z danej listy)
    $getResponseContacts = [];
    $page = 1;
    do {
        $url = 'https://api.getresponse.com/v3/contacts?query[campaignId]=' . urlencode($listId) . '&perPage=100&page=' . $page;
        if ($filterTag && !empty($data['tag'])) {
            $url .= '&query[tag]=' . urlencode($data['tag']);
        }
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
                $page++;
                usleep(500000); // 0.5s dla limitu GetResponse
            } else {
                break;
            }
        } else {
            break;
        }
    } while (true);

    // Zapisz liczbę kontaktów w GetResponse przed synchronizacją
    $getResponseCountBefore = count($getResponseContacts);

    $getResponseEmails = [];
    $getResponseMap = [];
    foreach ($getResponseContacts as $c) {
        $email = strtolower($c['email'] ?? '');
        if ($email) {
            $getResponseEmails[] = $email;
            $getResponseMap[$email] = $c;
        }
    }

    // Synchronizuj tylko nowe kontakty (od ostatniej synchronizacji)
    if ($syncNewOnly) {
        $lastSync = $cosmos->getLastSyncTime($domain);
        if ($lastSync) {
            $bitrixContacts = array_filter($bitrixContacts, function($c) use ($lastSync) {
                return (isset($c['DATE_MODIFY']) && strtotime($c['DATE_MODIFY']) > strtotime($lastSync));
            });
        }
    }

    $addedToGR = 0;
    $skippedGR = 0;
    $updatedGR = 0;
    $count = 0;
    foreach ($bitrixContacts as $contact) {
        if ($syncLimit > 0 && $count >= $syncLimit) break;
        $email = strtolower($contact['EMAIL'][0]['VALUE'] ?? '');
        if (!$email) continue;
        $exists = in_array($email, $getResponseEmails);
        if ($exists) {
            $skippedGR++;
            // Aktualizuj istniejące jeśli opcja włączona
            if ($updateExisting) {
                $grContact = $getResponseMap[$email];
                $update = false;
                if ($conflictRule === 'bitrix') {
                    $update = true;
                } elseif ($conflictRule === 'getresponse') {
                    $update = false;
                } elseif ($conflictRule === 'newer') {
                    $b24Date = isset($contact['DATE_MODIFY']) ? strtotime($contact['DATE_MODIFY']) : 0;
                    $grDate = isset($grContact['changedOn']) ? strtotime($grContact['changedOn']) : 0;
                    $update = $b24Date > $grDate;
                }
                if ($update) {
                    $payload = [
                        'name' => $contact['NAME'] ?? '',
                    ];
                    $ch = curl_init('https://api.getresponse.com/v3/contacts/' . $grContact['contactId']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'X-Auth-Token: api-key ' . $apiKey
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode === 202) {
                        $updatedGR++;
                    }
                    usleep(500000);
                }
            }
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
        $count++;
        usleep(500000); // 0.5s dla limitu GetResponse
    }

    // --- Synchronizacja GetResponse -> Bitrix24 ---
    $bitrixEmails = [];
    $bitrixMap = [];
    foreach ($bitrixContacts as $c) {
        $email = strtolower($c['EMAIL'][0]['VALUE'] ?? '');
        if ($email) {
            $bitrixEmails[] = $email;
            $bitrixMap[$email] = $c;
        }
    }
    $addedToB24 = 0;
    $skippedB24 = 0;
    $updatedB24 = 0;
    $count = 0;
    foreach ($getResponseContacts as $contact) {
        if ($syncLimit > 0 && $count >= $syncLimit) break;
        $email = strtolower($contact['email'] ?? '');
        if (!$email) continue;
        $exists = in_array($email, $bitrixEmails);
        if ($exists) {
            $skippedB24++;
            // Aktualizuj istniejące jeśli opcja włączona
            if ($updateExisting) {
                $b24Contact = $bitrixMap[$email];
                $update = false;
                if ($conflictRule === 'getresponse') {
                    $update = true;
                } elseif ($conflictRule === 'bitrix') {
                    $update = false;
                } elseif ($conflictRule === 'newer') {
                    $b24Date = isset($b24Contact['DATE_MODIFY']) ? strtotime($b24Contact['DATE_MODIFY']) : 0;
                    $grDate = isset($contact['changedOn']) ? strtotime($contact['changedOn']) : 0;
                    $update = $grDate > $b24Date;
                }
                if ($update) {
                    $payload = [
                        'id' => $b24Contact['ID'],
                        'fields' => [
                            'NAME' => $contact['name'] ?? '',
                            'ORIGIN_ID' => $contact['contactId'] ?? '',
                            'ORIGINATOR_ID' => 'getresponse',
                            'ORIGIN_VERSION' => $contact['changedOn'] ?? date('c')
                        ]
                    ];
                    $result = CRest::call('crm.contact.update', $payload);
                    if (!empty($result['result'])) {
                        $updatedB24++;
                    }
                    sleep(1);
                }
            }
            continue;
        }
        // Dodaj kontakt do Bitrix24
        $payload = [
            'fields' => [
                'NAME' => $contact['name'] ?? '',
                'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                'ORIGIN_ID' => $contact['contactId'] ?? '',
                'ORIGINATOR_ID' => 'getresponse',
                'ORIGIN_VERSION' => $contact['changedOn'] ?? date('c')
            ]
        ];
        $result = CRest::call('crm.contact.add', $payload);
        if (!empty($result['result'])) {
            $addedToB24++;
        }
        $count++;
        sleep(1); // Bitrix24 API limit
    }

    // Po synchronizacji: pobierz ponownie liczbę kontaktów
    // Bitrix24
    $bitrixContactsAfter = [];
    $start = 0;
    do {
        $batch = CRest::call('crm.contact.list', [
            'order' => ['ID' => 'ASC'],
            'filter' => ['!EMAIL' => false],
            'select' => ['ID'],
            'start' => $start
        ]);
        if (!empty($batch['result'])) {
            $bitrixContactsAfter = array_merge($bitrixContactsAfter, $batch['result']);
        }
        $start = $batch['next'] ?? 0;
        sleep(1);
    } while (!empty($batch['result']) && $start > 0);
    $bitrixCountAfter = count($bitrixContactsAfter);

    // GetResponse
    $getResponseContactsAfter = [];
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
                $getResponseContactsAfter = array_merge($getResponseContactsAfter, $contacts);
                $page++;
                usleep(500000);
            } else {
                break;
            }
        } else {
            break;
        }
    } while (true);
    $getResponseCountAfter = count($getResponseContactsAfter);

    // Loguj podsumowanie przez centralny moduł logowania
    CRest::setLog([
        'direction' => 'sync_both',
        'bitrix_before' => $bitrixCountBefore,
        'getresponse_before' => $getResponseCountBefore,
        'bitrix_after' => $bitrixCountAfter,
        'getresponse_after' => $getResponseCountAfter,
        'bitrix_to_gr_added' => $addedToGR,
        'bitrix_to_gr_updated' => $updatedGR,
        'bitrix_to_gr_skipped' => $skippedGR,
        'gr_to_bitrix_added' => $addedToB24,
        'gr_to_bitrix_updated' => $updatedB24,
        'gr_to_bitrix_skipped' => $skippedB24,
        'options' => [
            'syncNewOnly' => $syncNewOnly,
            'updateExisting' => $updateExisting,
            'filterTag' => $filterTag,
            'syncLimit' => $syncLimit,
            'conflictRule' => $conflictRule
        ]
    ], 'sync');
    
    $lastSyncResult = $cosmos->setLastSyncTime($domain, date('c'));
    if ($lastSyncResult['code'] >= 400) {
        throw new Exception('Failed to update last sync time: ' . ($lastSyncResult['curl_error'] ?? 'Unknown error'));
    }

    // Zwróć podsumowanie
    $msg = "Synchronization completed. Added to GetResponse: $addedToGR, updated: $updatedGR, skipped: $skippedGR. Added to Bitrix24: $addedToB24, updated: $updatedB24, skipped: $skippedB24.";
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'summary' => [
            'bitrix_to_gr_added' => $addedToGR,
            'bitrix_to_gr_updated' => $updatedGR,
            'bitrix_to_gr_skipped' => $skippedGR,
            'gr_to_bitrix_added' => $addedToB24,
            'gr_to_bitrix_updated' => $updatedB24,
            'gr_to_bitrix_skipped' => $skippedB24
        ],
        'details' => [
            'bitrix_before' => $bitrixCountBefore,
            'getresponse_before' => $getResponseCountBefore,
            'bitrix_after' => $bitrixCountAfter,
            'getresponse_after' => $getResponseCountAfter
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 