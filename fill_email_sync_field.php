<?php
// fill_email_sync_field.php
// Masowe uzupełnianie pola UF_CRM_EMAIL_SYNC_AUTOMATION w kontaktach Bitrix24 przez crest.php

require_once 'src/BitrixApi/BitrixApiClient.php';
require_once 'cosmos.php';

header('Content-Type: application/json');

$domain = $_REQUEST['DOMAIN'] ?? null;
if (!$domain) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

$cosmos = new CosmosDB();
$settings = $cosmos->getSettings($domain);
$license = $cosmos->getLicenseStatus($domain);
$status = strtolower($license['license_status'] ?? 'trial');

// Określ plan na podstawie statusu licencji
$plan = 'start'; // domyślnie start
if ($status === 'active') {
    $plan = 'professional'; // zakładamy, że aktywna licencja = professional
}

try {
    $api = new BitrixApiClient($license);
    
    // Pobierz kontakty bez pola automatyzacji
    $contacts = $api->getContacts(
        ['UF_CRM_EMAIL_SYNC_AUTOMATION' => ''],
        ['ID', 'NAME', 'EMAIL', 'UF_CRM_EMAIL_SYNC_AUTOMATION']
    );
    
    $updated = 0;
    $errors = [];
    
    // Aktualizuj kontakty w batchu
    foreach ($contacts as $contact) {
        $emails = $contact['EMAIL'] ?? [];
        if (!empty($emails)) {
            $email = strtolower($emails[0]['VALUE'] ?? '');
            if ($email) {
                $result = $api->call('crm.contact.update', [
                    'id' => $contact['ID'],
                    'fields' => [
                        'UF_CRM_EMAIL_SYNC_AUTOMATION' => $email
                    ]
                ]);
                
                if (!empty($result['result'])) {
                    $updated++;
                } else {
                    $errors[] = [
                        'id' => $contact['ID'],
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'errors' => $errors,
        'total' => count($contacts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 