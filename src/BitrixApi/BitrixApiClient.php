<?php

require_once __DIR__ . '/../../crest.php';

class BitrixApiClient {
    private $status;
    private $delay;
    private $lastRequestTime = 0;

    // Limity na podstawie statusu licencji
    private static $rateLimits = [
        'start' => 2,
        'trial' => 2,
        'standard' => 3,
        'professional' => 4,
        'active' => 4, // aktywna licencja = professional
        'enterprise' => 5
    ];
    private static $batchMaxSize = 50;
    private static $pageSize = 100;

    public function __construct($license = []) {
        // $license może być tablicą lub stringiem
        if (is_array($license)) {
            $this->status = strtolower($license['license_status'] ?? 'trial');
        } else {
            $this->status = strtolower($license ?: 'trial');
        }
        $this->delay = 1.0 / self::getRateLimit($this->status);
    }

    public static function getRateLimit($status) {
        return self::$rateLimits[$status] ?? self::$rateLimits['trial'];
    }
    public static function getBatchMaxSize() {
        return self::$batchMaxSize;
    }
    public static function getPageSize() {
        return self::$pageSize;
    }

    /**
     * Wykonaj pojedyncze zapytanie do API z uwzględnieniem limitów
     */
    public function call($method, $params = []) {
        $this->waitForRateLimit();
        $result = CRest::call($method, $params);
        $this->lastRequestTime = microtime(true);
        return $result;
    }

    /**
     * Wykonaj batch request z uwzględnieniem limitów
     */
    public function callBatch($commands, $halt = 0) {
        $this->waitForRateLimit();
        $result = CRest::callBatch($commands, $halt);
        $this->lastRequestTime = microtime(true);
        return $result;
    }

    /**
     * Pobierz listę kontaktów z paginacją
     */
    public function getContacts($filter = [], $select = ['ID', 'NAME', 'UF_CRM_EMAIL_SYNC_AUTOMATION']) {
        $start = 0;
        $allContacts = [];
        do {
            $params = [
                'select' => $select,
                'filter' => $filter,
                'order' => ['ID' => 'ASC'],
                'start' => $start,
                'limit' => self::getPageSize()
            ];
            $result = $this->call('crm.contact.list', $params);
            if (!empty($result['result'])) {
                $allContacts = array_merge($allContacts, $result['result']);
                $start = $result['next'] ?? 0;
            }
        } while (!empty($result['result']) && $start > 0);
        return $allContacts;
    }

    /**
     * Aktualizuj kontakty w batchu
     */
    public function updateContactsBatch($contacts, $fields) {
        $batches = array_chunk($contacts, self::getBatchMaxSize());
        $results = [];
        foreach ($batches as $batch) {
            $commands = [];
            foreach ($batch as $contact) {
                $commands[] = [
                    'method' => 'crm.contact.update',
                    'params' => [
                        'id' => $contact['ID'],
                        'fields' => $fields
                    ]
                ];
            }
            $result = $this->callBatch($commands);
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Poczekaj na dostępność limitu zapytań
     */
    private function waitForRateLimit() {
        if ($this->lastRequestTime > 0) {
            $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
            if ($timeSinceLastRequest < $this->delay) {
                usleep(($this->delay - $timeSinceLastRequest) * 1000000);
            }
        }
    }
} 