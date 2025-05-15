<?php
require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/cosmos.php';

// 1. Inicjalizacja instalacji i zapis do settings.json + Cosmos DB
$install_result = CRest::installApp();

// 2. Rejestracja webhooka do handlera
$handlerBackUrl = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/handler.php?v=2';

$result = CRest::call('event.bind', [
    'EVENT' => 'ONCRMLEADADD',
    'HANDLER' => $handlerBackUrl,
    'EVENT_TYPE' => 'online'
]);

// 3. Zapis logu instalacji (tylko na potrzeby debugowania)
CRest::setLog(['lead_add' => $result], 'installation');

// 4. Dane debugowe (opcjonalne, pomocne przy testach lokalnych)
$request = $_REQUEST;
$auth = CRest::getAppSettings();

file_put_contents(__DIR__ . '/logs/install_debug_' . time() . '.json', json_encode([
    'request' => $request,
    'auth' => $auth
], JSON_PRETTY_PRINT));

// 5. Render odpowiedzi dla aplikacji osadzanej w iframe (embed / placement)
if ($install_result['rest_only'] === false): ?>
<head>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <?php if ($install_result['install'] === true): ?>
    <script>
        BX24.init(function(){
            BX24.installFinish();
        });
    </script>
    <?php endif; ?>
</head>
<body>
    <?php if ($install_result['install'] === true): ?>
        installation has been finished
    <?php else: ?>
        installation error
    <?php endif; ?>
</body>
<?php endif;
