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
    cosmos_insert([
        'portal' => $install_result['member_id'],
        'domain' => $install_result['domain'],
        'auth' => $install_result['auth'],
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
