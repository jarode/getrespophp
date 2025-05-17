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
        try {
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

            if ($code >= 400) {
                throw new Exception('CosmosDB insert failed: ' . ($curlError ?: $response));
            }

            return [
                'code' => $code,
                'response' => $response,
                'curl_error' => $curlError
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'response' => $e->getMessage(),
                'curl_error' => $e->getMessage()
            ];
        }
    }

    /**
     * Query document from Cosmos DB by domain
     */
    public static function queryByDomain($partitionKey) {
        try {
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

            if ($code >= 400) {
                throw new Exception('CosmosDB query failed: ' . ($curlError ?: $response));
            }

            return [
                'code' => $code,
                'response' => $response,
                'curl_error' => $curlError
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'response' => $e->getMessage(),
                'curl_error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update document in Cosmos DB
     */
    public static function update($partitionKey, $document) {
        try {
            $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER . "/docs/" . $document['id'];
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
            curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($code >= 400) {
                throw new Exception('CosmosDB update failed: ' . ($curlError ?: $response));
            }

            return [
                'code' => $code,
                'response' => $response,
                'curl_error' => $curlError
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'response' => $e->getMessage(),
                'curl_error' => $e->getMessage()
            ];
        }
    }

    /**
     * Zapisz ustawienia użytkownika w zunifikowanej strukturze
     * @param string $domain Domena Bitrix24
     * @param array $settings Ustawienia (np. klucz API, lista GetResponse)
     * @return array Wynik operacji
     */
    public static function saveSettings($domain, $settings)
    {
        $logType = 'settings';
        $now = gmdate('c');
        // Sprawdź, czy istnieje dokument settings dla domeny
        $existing = self::getSettings($domain);
        $doc = [
            'domain' => $domain,
            'log_type' => $logType,
            'log_data' => $settings,
            'log_time' => $now,
            'source' => 'saveSettings',
        ];
        if ($existing && isset($existing['id'])) {
            $doc['id'] = $existing['id'];
            return self::update($domain, $doc);
        } else {
            $doc['id'] = uniqid();
            return self::insert($domain, $doc);
        }
    }

    /**
     * Pobierz ustawienia użytkownika (log_type = 'settings')
     * @param string $domain
     * @return array|null
     */
    public static function getSettings($domain)
    {
        $result = self::queryByDomain($domain);
        if ($result['code'] >= 200 && $result['code'] < 300) {
            $data = json_decode($result['response'], true);
            if (isset($data['Documents']) && is_array($data['Documents'])) {
                // Szukaj najnowszego wpisu typu 'settings'
                $settingsDocs = array_filter($data['Documents'], function($doc) {
                    return isset($doc['log_type']) && $doc['log_type'] === 'settings';
                });
                if (!empty($settingsDocs)) {
                    // Sortuj po log_time malejąco
                    usort($settingsDocs, function($a, $b) {
                        return strcmp($b['log_time'], $a['log_time']);
                    });
                    // Zwróć log_data najnowszego wpisu
                    return $settingsDocs[0]['log_data'] ?? null;
                }
            }
        }
        return null;
    }

    /**
     * Zapisz status licencji w zunifikowanej strukturze
     * @param string $domain
     * @param array $licenseData
     * @return array
     */
    public static function saveLicenseStatus($domain, $licenseData)
    {
        $logType = 'license';
        $now = gmdate('c');
        $existing = self::getLicenseStatus($domain, true); // true = return full doc
        $doc = [
            'domain' => $domain,
            'log_type' => $logType,
            'log_data' => $licenseData,
            'log_time' => $now,
            'source' => 'saveLicenseStatus',
        ];
        if ($existing && isset($existing['id'])) {
            $doc['id'] = $existing['id'];
            return self::update($domain, $doc);
        } else {
            $doc['id'] = uniqid();
            return self::insert($domain, $doc);
        }
    }

    /**
     * Pobierz status licencji (log_type = 'license')
     * @param string $domain
     * @param bool $fullDoc Jeśli true, zwraca cały dokument, jeśli false tylko log_data
     * @return array|null
     */
    public static function getLicenseStatus($domain, $fullDoc = false)
    {
        $result = self::queryByDomain($domain);
        if ($result['code'] >= 200 && $result['code'] < 300) {
            $data = json_decode($result['response'], true);
            if (isset($data['Documents']) && is_array($data['Documents'])) {
                $licenseDocs = array_filter($data['Documents'], function($doc) {
                    return isset($doc['log_type']) && $doc['log_type'] === 'license';
                });
                if (!empty($licenseDocs)) {
                    usort($licenseDocs, function($a, $b) {
                        return strcmp($b['log_time'], $a['log_time']);
                    });
                    return $fullDoc ? $licenseDocs[0] : ($licenseDocs[0]['log_data'] ?? null);
                }
            }
        }
        return null;
    }
} 