<?php
require_once(__DIR__.'/cosmos.php');

// Read payload and headers
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Try to parse JSON
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

switch ($event['type']) {
    case 'checkout.session.completed':
        $session = $event['data']['object'];
        $domain = $session['metadata']['domain'] ?? null;
        if ($domain) {
            $settings = CosmosDB::getSettings($domain);
            if ($settings) {
                $settings['license_status'] = 'active';
                $settings['license_expiry'] = date('Y-m-d', strtotime('+1 month'));
                $settings['payment_id'] = $session['payment_intent'] ?? null;
                $settings['payment_date'] = date('Y-m-d H:i:s');
                CosmosDB::update($domain, $settings);
            }
        }
        break;
    default:
        echo 'Received unknown event type ' . $event['type'];
}

http_response_code(200);
echo json_encode(['status' => 'success']); 