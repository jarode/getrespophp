<?php
require_once (__DIR__.'/crest.php');

$install_result = CRest::installApp();

// embedded for placement "placement.php"
$handlerBackUrl = ($_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] === '443' ? 'https' : 'http') . '://'
	. $_SERVER['SERVER_NAME']
	. (in_array($_SERVER['SERVER_PORT'],	['80', '443'], true) ? '' : ':' . $_SERVER['SERVER_PORT'])
	. str_replace($_SERVER['DOCUMENT_ROOT'], '',__DIR__)
	. '/handler.php?v2';

$result = CRest::call(
	'event.bind',
	[
		'EVENT' => 'ONCRMLEADADD',
		'HANDLER' => $handlerBackUrl,
		'EVENT_TYPE' => 'online'
	]
);

CRest::setLog(['lead_add' => $result], 'installation');

// Dodaj pole niestandardowe do kontaktów (jeśli nie istnieje)
$userFields = CRest::call('crm.contact.userfield.list', [
    'filter' => ['FIELD_NAME' => 'UF_CRM_EMAIL_SYNC_AUTOMATION']
]);
if (empty($userFields['result'])) {
    $addFieldResult = CRest::call('crm.contact.userfield.add', [
        'fields' => [
            'FIELD_NAME' => 'UF_CRM_EMAIL_SYNC_AUTOMATION',
            'EDIT_FORM_LABEL' => ['pl' => 'Email Sync Automation', 'en' => 'Email Sync Automation'],
            'LIST_COLUMN_LABEL' => ['pl' => 'Email Sync Automation', 'en' => 'Email Sync Automation'],
            'USER_TYPE_ID' => 'string',
            'XML_ID' => 'EMAIL_SYNC_AUTOMATION',
            'SORT' => 1000,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'Y',
            'SHOW_IN_LIST' => 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'Y'
        ]
    ]);
    CRest::setLog(['add_userfield' => $addFieldResult], 'installation');
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