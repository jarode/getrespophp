<?php
require_once(__DIR__.'/crest.php');

$input = file_get_contents('php://input');
file_put_contents('log.txt', date('c') . "\n" . $input . "\n", FILE_APPEND);

$response = CRest::call('user.current');
file_put_contents('log.txt', print_r($response, true), FILE_APPEND);

echo 'OK';