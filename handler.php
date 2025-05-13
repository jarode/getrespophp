<?php

include_once('crest.php');

// 📁 Katalog na logi
$dir = __DIR__ . '/tmp/';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

// 🔎 Loguj dane wejściowe (dla pewności)
file_put_contents($dir . '_raw.txt', var_export($_POST, true), FILE_APPEND);

// Pobierz dane z POST
$event = $_POST['event'] ?? null;
$fields = $_POST['data']['FIELDS'] ?? [];

// 📌 Obsługa leadów → wysyłka do GetResponse
if ($event === 'ONCRMLEADADD') {

    $leadId = $fields['ID'] ?? null;
    $lead_result = CRest::call('crm.lead.get', ['ID' => $leadId]);
    $lead = $lead_result['result'] ?? [];

    $name  = $lead['NAME'] ?? '';
    $email = $lead['EMAIL'][0]['VALUE'] ?? '';

    // 🟨 Jeśli brak e-maila w leadzie, spróbuj pobrać z przypisanego kontaktu
    if (!$email && isset($lead['CONTACT_ID'])) {
        $contactId = $lead['CONTACT_ID'];
        $contact_result = CRest::call('crm.contact.get', ['ID' => $contactId]);

        if (!empty($contact_result['result']['EMAIL'][0]['VALUE'])) {
            $email = $contact_result['result']['EMAIL'][0]['VALUE'];
        }

        // Jeśli chcesz, możesz też nadpisać imię, jeśli brakuje
        if (!$name && !empty($contact_result['result']['NAME'])) {
            $name = $contact_result['result']['NAME'];
        }
    }

    // ✅ Wyślij do GetResponse, jeśli mamy e-mail
    if ($email) {
        $apiKey = '62a96f1wzus8pp7s6o83s233j2to908k';
        $campaignId = 'id0Rg';

        $payload = json_encode([
            'name' => $name,
            'email' => $email,
            'campaign' => ['campaignId' => $campaignId]
        ]);

        $ch = curl_init('https://api.getresponse.com/v3/contacts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-Auth-Token: api-key $apiKey"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents(
            $dir . time() . '_' . rand(1000, 9999) . '_lead.txt',
            var_export([
                'lead_id'     => $leadId,
                'name'        => $name,
                'email'       => $email,
                'response'    => $response,
                'http'        => $httpCode,
                'lead_raw'    => $lead,
                'contact_raw' => $contact_result['result'] ?? []
            ], true)
        );
    }
}

