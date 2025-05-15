<?php
/**
 * Azure Cosmos DB integration for Bitrix24 REST API
 */

class CosmosDB
{
    // Cosmos DB configuration
    const ENDPOINT = 'https://bitrixsubscriptionsdb.documents.azure.com:443/';
    const KEY = 'odY3Dp2pgTdoxv7NGoipcqmFJwit4pfhd4hdOzxOxQmFN1yevkKNRB8oRKafzUTZbAisDyoPHGGeACDbVIfAmw==';
    const DATABASE = 'bitrixapp';
    const CONTAINER_SUBSCRIPTIONS = 'subscriptions';

    /**
     * Build authorization token for Cosmos DB
     */
    public static function build_auth_token($verb, $resourceType, $resourceLink, $utcDate, $key, $keyType = 'master', $tokenVersion = '1.0') {
        $stringToSign = strtolower($verb) . "\n" .
                        strtolower($resourceType) . "\n" .
                        $resourceLink . "\n" .
                        strtolower($utcDate) . "\n\n";

        $decodedKey = base64_decode($key);
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

        return urlencode("type={$keyType}&ver={$tokenVersion}&sig={$signature}");
    }

    /**
     * Get document from Cosmos DB by domain
     */
    public static function get_by_domain($domain) {
        $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER_SUBSCRIPTIONS;
        $query = "SELECT * FROM c WHERE c.domain = @domain";
        $params = [['name' => '@domain', 'value' => $domain]];
        
        $utcDate = gmdate('D, d M Y H:i:s T');
        $token = self::build_auth_token('POST', 'docs', $resourceLink, $utcDate, self::KEY);
        
        $headers = [
            'Content-Type: application/query+json',
            'x-ms-documentdb-isquery: true',
            'x-ms-date: ' . $utcDate,
            'x-ms-version: 2023-11-15',
            'Authorization: ' . $token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink . '/docs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'query' => $query,
            'parameters' => $params
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code >= 200 && $code < 300) {
            $result = json_decode($response, true);
            return $result['Documents'][0] ?? null;
        }
        
        return null;
    }

    /**
     * Update or insert document in Cosmos DB
     */
    public static function update($domain, $fields) {
        $resourceLink = "dbs/" . self::DATABASE . "/colls/" . self::CONTAINER_SUBSCRIPTIONS;
        
        // Get existing document
        $existing = self::get_by_domain($domain);
        
        // Prepare document
        $document = [
            'id' => $existing['id'] ?? uniqid(),
            'domain' => $domain,
            'updated_at' => date('c')
        ];
        
        // Merge with new fields
        $document = array_merge($document, $fields);
        
        $utcDate = gmdate('D, d M Y H:i:s T');
        $token = self::build_auth_token($existing ? 'PUT' : 'POST', 'docs', $resourceLink, $utcDate, self::KEY);
        
        $headers = [
            'Content-Type: application/json',
            'x-ms-date: ' . $utcDate,
            'x-ms-version: 2023-11-15',
            'x-ms-documentdb-partitionkey' => '["' . $domain . '"]',
            'Authorization: ' . $token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $resourceLink . '/docs' . ($existing ? '/' . $existing['id'] : ''));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $existing ? 'PUT' : 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($document));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $code >= 200 && $code < 300;
    }
} 