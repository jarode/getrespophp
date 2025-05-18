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

$apiKey = $settings['getresponse_api_key'] ?? '';
$listId = $settings['getresponse_list_id'] ?? '';

if (!$apiKey || !$listId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'GetResponse API Key and List ID are required.']);
    exit;
}

try {
    // --- Pobierz kontakty z GetResponse ---
    $getResponseContacts = [];
    $page = 1;
    $getResponseStats = [
        'total' => 0,
        'with_email' => 0,
        'with_name' => 0,
        'with_both' => 0,
        'errors' => []
    ];

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
                    $getResponseStats['total']++;
                    
                    // Walidacja email
                    $hasEmail = !empty($contact['email']);
                    if ($hasEmail) {
                        $getResponseStats['with_email']++;
                    }
                    
                    // Walidacja imienia
                    $hasName = !empty($contact['name']);
                    if ($hasName) {
                        $getResponseStats['with_name']++;
                    }
                    
                    // Walidacja obu pól
                    if ($hasEmail && $hasName) {
                        $getResponseStats['with_both']++;
                    }
                    
                    // Sprawdź poprawność email
                    if ($hasEmail && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                        $getResponseStats['errors'][] = [
                            'type' => 'invalid_email',
                            'contact_id' => $contact['contactId'],
                            'email' => $contact['email']
                        ];
                    }
                }
                
                $getResponseContacts = array_merge($getResponseContacts, $contacts);
                $page++;
                usleep(500000); // 0.5s dla limitu GetResponse
            } else {
                break;
            }
        } else {
            $getResponseStats['errors'][] = [
                'type' => 'api_error',
                'http_code' => $httpCode,
                'response' => $response
            ];
            break;
        }
    } while (true);

    // --- Pobierz kontakty z Bitrix24 ---
    $bitrixStats = [
        'total' => 0,
        'with_email' => 0,
        'with_name' => 0,
        'with_both' => 0,
        'with_origin' => 0,
        'errors' => []
    ];

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
            foreach ($result['result'] as $contact) {
                $bitrixStats['total']++;
                
                // Walidacja email
                $hasEmail = !empty($contact['EMAIL']);
                if ($hasEmail) {
                    $bitrixStats['with_email']++;
                }
                
                // Walidacja imienia
                $hasName = !empty($contact['NAME']);
                if ($hasName) {
                    $bitrixStats['with_name']++;
                }
                
                // Walidacja obu pól
                if ($hasEmail && $hasName) {
                    $bitrixStats['with_both']++;
                }
                
                // Walidacja ORIGIN_*
                if (!empty($contact['ORIGIN_ID']) && !empty($contact['ORIGINATOR_ID'])) {
                    $bitrixStats['with_origin']++;
                }
                
                // Sprawdź poprawność email
                if ($hasEmail) {
                    foreach ($contact['EMAIL'] as $email) {
                        if (!filter_var($email['VALUE'], FILTER_VALIDATE_EMAIL)) {
                            $bitrixStats['errors'][] = [
                                'type' => 'invalid_email',
                                'contact_id' => $contact['ID'],
                                'email' => $email['VALUE']
                            ];
                        }
                    }
                }
            }
        }
        
        $start = $result['next'] ?? 0;
        if ($start <= 0) break;
        
        sleep(1); // Rate limiting dla Bitrix24
    } while (true);

    // Zapisz wyniki walidacji do CosmosDB
    $cosmos->insert($domain, [
        'log_type' => 'validation',
        'log_time' => date('c'),
        'source' => 'validate_contacts.php',
        'getresponse_stats' => $getResponseStats,
        'bitrix_stats' => $bitrixStats
    ]);

    echo json_encode([
        'success' => true,
        'getresponse' => $getResponseStats,
        'bitrix' => $bitrixStats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 