<?php
// create_payment.php
require_once(__DIR__.'/crest.php');

// Get domain from request
$domain = $_REQUEST['DOMAIN'] ?? ($_SERVER['HTTP_HOST'] ?? '');
if (empty($domain)) {
    die(json_encode(['success' => false, 'error' => 'Domain is required']));
}

// Stripe checkout URL (na tym etapie możesz zwrócić testowy link lub komunikat)
echo json_encode([
    'success' => false,
    'error' => 'Stripe integration temporarily unavailable'
]); 