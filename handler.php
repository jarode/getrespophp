<?php
require_once(__DIR__.'/crest.php');

if ($_REQUEST['event'] === 'ONCRMLEADADD') {
	$leadId = $_REQUEST['data']['FIELDS']['ID'] ?? null;
	if ($leadId) {
		// Pobierz dane leada z Bitrix24
		$leadResult = CRest::call('crm.lead.get', ['ID' => $leadId]);
		$lead = $leadResult['result'] ?? null;
		$email = $lead['EMAIL'][0]['VALUE'] ?? '';
		$name = $lead['NAME'] ?? '';

		// Jeśli nie ma emaila lub imienia, a jest CONTACT_ID, pobierz kontakt
		if ((empty($email) || empty($name)) && !empty($lead['CONTACT_ID'])) {
			$contactResult = CRest::call('crm.contact.get', ['ID' => $lead['CONTACT_ID']]);
			$contact = $contactResult['result'] ?? null;
			if ($contact) {
				if (empty($email)) {
					$email = $contact['EMAIL'][0]['VALUE'] ?? '';
				}
				if (empty($name)) {
					$name = $contact['NAME'] ?? '';
				}
			}
		}

		if (!empty($email)) {
			// Przygotuj dane do GetResponse
			$apiKey = '62a96f1wzus8pp7s6o83s233j2to908k'; // ← Twój klucz
			$listId = 'id0Rg';   // ← Twoje ID kampanii
			$contactData = [
				'email' => $email,
				'name' => $name,
				'campaign' => ['campaignId' => $listId],
				// Dodaj inne mapowania pól jeśli potrzeba
			];
			// Wyślij do GetResponse
			$ch = curl_init('https://api.getresponse.com/v3/contacts');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contactData));
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'X-Auth-Token: api-key ' . $apiKey
			]);
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlError = curl_error($ch);
			curl_close($ch);

			// Loguj do Cosmos DB
			$logData = [
				'lead_id' => $leadId,
				'lead_data' => $lead,
				'contact_data' => $contact ?? null,
				'getresponse_payload' => $contactData,
				'getresponse_response' => $response,
				'getresponse_http_code' => $httpCode,
				'getresponse_curl_error' => $curlError,
				'time' => date('c'),
				'source' => 'handler.php'
			];
			$domain = $_REQUEST['auth']['domain'] ?? ($_REQUEST['DOMAIN'] ?? null);
			if ($domain) {
				CosmosDB::insert($domain, $logData);
			}
		}
	}
}

?>