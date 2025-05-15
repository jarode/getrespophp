<?php
require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/cosmos.php';

// 1. Inicjalizacja instalacji i zapis do settings.json + Cosmos DB
$result = CRest::installApp();
if($result['rest_only'] === true)
{
    $result = CRest::call('app.info', []);
    if($result['error'] == 'expired_token')
    {
        $result = CRest::call('app.info', []);
    }
}

// Logowanie do Cosmos DB
try {
    $logData = [
        'domain' => $_REQUEST['DOMAIN'] ?? 'unknown',
        'type' => 'installation',
        'data' => [
            'request' => $_REQUEST,
            'result' => $result
        ],
        'status' => isset($result['error']) ? 'error' : 'success',
        'metadata' => [
            'source' => 'bitrix24',
            'action' => 'install'
        ]
    ];
    cosmos_add_log($logData);
} catch (Exception $e) {
    // Jeśli nie uda się zalogować do Cosmos DB, zapisz do pliku
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    file_put_contents(
        $logDir . '/install_' . time() . '.json',
        json_encode([
            'error' => 'cosmos_log_failed',
            'message' => $e->getMessage(),
            'data' => $logData
        ], JSON_PRETTY_PRINT)
    );
}

// 2. Rejestracja webhooka do handlera
$handlerBackUrl = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/handler.php?v=2';

$result = CRest::call('event.bind', [
    'EVENT' => 'ONCRMLEADADD',
    'HANDLER' => $handlerBackUrl,
    'EVENT_TYPE' => 'online'
]);

// 3. Zapis logu instalacji (tylko na potrzeby debugowania)
CRest::setLog(['lead_add' => $result], 'installation');

// 4. Render odpowiedzi dla aplikacji osadzanej w iframe (embed / placement)
if ($result['rest_only'] === false): ?>
<head>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <?php if ($result['install'] === true): ?>
    <script>
        BX24.init(function(){
            BX24.installFinish();
        });
    </script>
    <?php endif; ?>
</head>
<body>
    <?php if ($result['install'] === true): ?>
        installation has been finished
    <?php else: ?>
        installation error
    <?php endif; ?>
</body>
<?php endif;
