<?php
require_once (__DIR__.'/crest.php');
require_once __DIR__ . '/cosmos.php';

$install_result = CRest::installApp();

// embedded for placement "placement.php"
$handlerBackUrl2 = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http') . '://'
	. $_SERVER['SERVER_NAME']
	. (in_array($_SERVER['SERVER_PORT'],	['80', '443'], true) ? '' : ':' . $_SERVER['SERVER_PORT'])
	. str_replace($_SERVER['DOCUMENT_ROOT'], '',__DIR__)
	. '/handler.php';

$handlerBackUrl = 'https://bitrix-php-app.nicetree-ab137c51.westeurope.azurecontainerapps.io/handler.php?v=2';

$result = CRest::call(
	'event.bind',
	[
		'EVENT' => 'ONCRMLEADADD',
		'HANDLER' => $handlerBackUrl,
        'EVENT_TYPE' => 'online'
	]
);

CRest::setLog(['lead_add' => $result], 'installation');

// ➕ Zapisz dane instalacyjne do bazy Cosmos DB (jeśli instalacja OK)
if ($install_result['install'] === true) {
    $request = $_REQUEST;
    $auth = CRest::getAppSettings(); // zawiera access_token i inne

    cosmos_insert([
        'portal' => $request['member_id'] ?? null,
        'domain' => $request['DOMAIN'] ?? null,
        'auth' => $auth['access_token'] ?? null,
        'date' => date('c'),
        'plan' => 'trial',
        'status' => 'active'
    ]);
}

if($install_result['rest_only'] === false):?>
<head>
	<script src="//api.bitrix24.com/api/v1/"></script>
	<?if($install_result['install'] == true):?>
	<script>
		BX24.init(function(){
			BX24.installFinish();
		});
	</script>
	<?endif;?>
</head>
<body>
	<?if($install_result['install'] == true):?>
		installation has been finished
	<?else:?>
		installation error
	<?endif;?>
</body>
<?endif;

file_put_contents(__DIR__ . '/logs/install_debug_' . time() . '.json', json_encode([
    'request' => $request,
    'auth' => $auth
], JSON_PRETTY_PRINT));
