<?php
include_once('crest.php');

// 📁 Katalog na logi
$dir = __DIR__ . '/tmp/';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

// 📌 Obsługa kontaktów: aktualizacja wielkości liter
if (in_array($_REQUEST['event'], ['ONCRMCONTACTUPDATE', 'ONCRMCONTACTADD'])) {

    $contactId = $_REQUEST['data']['FIELDS']['ID'] ?? null;
    $get_result = CRest::call('crm.contact.get', ['ID' => $contactId]);

    $name        = mb_convert_case($get_result['result']['NAME'], MB_CASE_TITLE, "UTF-8");
    $last_name   = mb_convert_case($get_result['result']['LAST_NAME'], MB_CASE_TITLE, "UTF-8");
    $middle_name = mb_convert_case($get_result['result']['SECOND_NAME'], MB_CASE_TITLE, "UTF-8");

    if (
        ($get_result['result']['NAME'] != $name) ||
        ($get_result['result']['LAST_NAME'] != $last_name) ||
        ($get_result['result']['SECOND_NAME'] != $middle_name)
    ) {
        $update_result = CRest::call('crm.contact.update', [
            'ID' => $contactId,
            'FIELDS' => [
                'NAME'        => $name,
                'LAST_NAME'   => $last_name,
                'SECOND_NAME' => $middle_name
            ]
        ]);
    }

    file_put_contents(
        $dir . time() . '_' . rand(1, 9999) . '_contact.txt',
        var_export([
            'get'     => $get_result,
            'update'  => $update_result ?? [],
            'names'   => [$name, $last_name, $middle_name],
            'request' => $_REQUEST
        ], true)
    );
}

// 📌 Obsługa leadów → wysyłka do GetResponse
if ($_REQUEST['event'] === 'ONCRMLEADADD') {

    $leadId = $_REQUEST['data']['FIELDS']['ID'] ?? null;
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
                'raw'      => $_REQUEST
            ], true)
        );
    }
}
?>
