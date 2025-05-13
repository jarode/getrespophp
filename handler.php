<?php

include_once('crest.php');

// 📁 Katalog na logi
$dir = __DIR__ . '/tmp/';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

// 📥 Wczytanie danych JSON z Bitrix webhooka
$json = file_get_contents('php://input');
file_put_contents($dir . '_raw.json', $json . "\n", FILE_APPEND);
$data = json_decode($json, true);

// 🔎 Sprawdź jaki event został uruchomiony
$event = $data['event'] ?? null;
$fields = $data['data']['FIELDS'] ?? [];


// 📌 Obsługa leadów → wysyłka do GetResponse
if ($event === 'ONCRMLEADADD') {

    $leadId = $fields['ID'] ?? null;
    $lead_result = CRest::call('crm.lead.get', ['ID' => $leadId]);

    $lead = $lead_result['result'] ?? [];

    $name  = $lead['NAME'] ?? '';
    $email = $lead['EMAIL'][0]['VALUE'] ?? '';

    // ✅ Wyślij do GetResponse
    if ($email) {
        $apiKey = '62a96f1wzus8pp7s6o83s233j2to908k'; // ← Twój klucz
        $campaignId = 'id0Rg';                        // ← Twoja kampania

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
                'lead_id'  => $leadId,
                'name'     => $name,
                'email'    => $email,
                'response' => $response,
                'http'     => $httpCode,
                'raw'      => $data
            ], true)
        );
    }
}
