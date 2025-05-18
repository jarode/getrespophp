<?php
// fill_email_sync_field.php
// Masowe uzupełnianie pola UF_CRM_EMAIL_SYNC_AUTOMATION w kontaktach Bitrix24 przez crest.php

// Funkcja do wywoływania crest.php przez HTTP
function callCrest($method, $params = []) {
    $url = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/crest.php';
    $payload = [
        'method' => $method,
        'params' => $params
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$updated = 0;
$checked = 0;
$start = 0;
do {
    $batch = callCrest('crm.contact.list', [
        'order' => ['ID' => 'ASC'],
        'select' => ['ID', 'UF_CRM_EMAIL_SYNC_AUTOMATION'],
        'filter' => [],
        'start' => $start
    ]);
    if (!empty($batch['result'])) {
        foreach ($batch['result'] as $contact) {
            $checked++;
            $emailField = $contact['UF_CRM_EMAIL_SYNC_AUTOMATION'] ?? '';
            if (empty($emailField)) {
                // Pobierz szczegóły kontaktu
                $details = callCrest('crm.contact.get', ['ID' => $contact['ID']]);
                $c = $details['result'] ?? [];
                $emails = $c['EMAIL'] ?? [];
                $mainEmail = '';
                if (!empty($emails) && isset($emails[0]['VALUE'])) {
                    $mainEmail = $emails[0]['VALUE'];
                }
                if ($mainEmail) {
                    $res = callCrest('crm.contact.update', [
                        'id' => $contact['ID'],
                        'fields' => [
                            'UF_CRM_EMAIL_SYNC_AUTOMATION' => $mainEmail
                        ]
                    ]);
                    $updated++;
                }
            }
        }
    }
    $start = $batch['next'] ?? 0;
    sleep(1);
} while (!empty($batch['result']) && $start > 0);

// Wynik
header('Content-Type: application/json');
echo json_encode([
    'checked' => $checked,
    'updated' => $updated,
    'success' => true
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 