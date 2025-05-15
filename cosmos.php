<?php
/**
 * Azure Cosmos DB integration for Bitrix24 REST API
 */

class CosmosDB
{
    // Cosmos DB configuration
    const ENDPOINT = 'https://bitrixusersdb.documents.azure.com:443/';
    const KEY = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    const DATABASE = 'bitrixapp';
    const CONTAINER = 'subscriptions';
    const API_VERSION = '2018-12-31';

    /**
     * Build authorization token for Cosmos DB
     */
    public static function build_auth_token($verb, $resourceType, $resourceLink, $date, $key, $keyType = 'master', $tokenVersion = '1.0') {
        $stringToSign = strtolower($verb) . "\n" .
                        strtolower($resourceType) . "\n" .
                        $resourceLink . "\n" .
                        strtolower($date) . "\n\n";
        $decodedKey = base64_decode($key);
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));
        return urlencode("type={$keyType}&ver={$tokenVersion}&sig={$signature}");
    }

    /**
     * Insert document into Cosmos DB
     */
    public static function insert($partitionKey, $document) {
        $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER;
        $date = gmdate('D, d M Y H:i:s T');
        $token = self::build_auth_token('POST', 'docs', $resourceLink, $date, self::KEY);

        $headers = [
            'Authorization: ' . $token,
            'x-ms-date: ' . $date,
            'x-ms-version: ' . self::API_VERSION,
            'Content-Type: application/json',
            'x-ms-documentdb-partitionkey: ["' . $partitionKey . '"]'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink . '/docs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'code' => $code,
            'response' => $response,
            'curl_error' => $curlError
        ];
    }

    /**
     * Query document from Cosmos DB by domain
     */
    public static function queryByDomain($partitionKey) {
        $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER;
        $date = gmdate('D, d M Y H:i:s T');
        $token = self::build_auth_token('POST', 'docs', $resourceLink, $date, self::KEY);

        $query = [
            'query' => 'SELECT * FROM c WHERE c.domain = @domain',
            'parameters' => [
                ['name' => '@domain', 'value' => $partitionKey]
            ]
        ];

        $headers = [
            'Authorization: ' . $token,
            'x-ms-date: ' . $date,
            'x-ms-version: ' . self::API_VERSION,
            'Content-Type: application/query+json',
            'x-ms-documentdb-isquery: true',
            'x-ms-documentdb-partitionkey: ["' . $partitionKey . '"]'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink . '/docs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'code' => $code,
            'response' => $response,
            'curl_error' => $curlError
        ];
    }

    /**
     * Update document in Cosmos DB
     */
    public static function update($partitionKey, $document) {
        $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER;
        $date = gmdate('D, d M Y H:i:s T');
        $token = self::build_auth_token('PUT', 'docs', $resourceLink, $date, self::KEY);

        $headers = [
            'Authorization: ' . $token,
            'x-ms-date: ' . $date,
            'x-ms-version: ' . self::API_VERSION,
            'Content-Type: application/json',
            'x-ms-documentdb-partitionkey: ["' . $partitionKey . '"]'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink . '/docs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'code' => $code,
            'response' => $response,
            'curl_error' => $curlError
        ];
    }

    /**
     * Get settings document from Cosmos DB by domain
     */
    public static function getSettings($domain) {
        $result = self::queryByDomain($domain);
        if ($result['code'] >= 200 && $result['code'] < 300) {
            $data = json_decode($result['response'], true);
            if (isset($data['Documents'][0])) {
                return $data['Documents'][0];
            }
        }
        return null;
    }

    /**
     * Set (insert or update) settings document in Cosmos DB by domain
     */
    public static function setSettings($domain, $settingsArray) {
        // Sprawdź, czy istnieje dokument dla domeny
        $existing = self::getSettings($domain);
        if ($existing && isset($existing['id'])) {
            $settingsArray['id'] = $existing['id'];
            return self::update($domain, $settingsArray);
        } else {
            if (!isset($settingsArray['id'])) {
                $settingsArray['id'] = uniqid();
            }
            $settingsArray['domain'] = $domain;
            return self::insert($domain, $settingsArray);
        }
    }
} 