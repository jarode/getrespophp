<?php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/cosmos.php');

// Testowy webhook secret Stripe
$endpoint_secret = 'whsec_rkFvtV0zCJ4nZDrl04h5EIl55TYk6At9';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}

switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        $domain = $session->metadata->domain ?? null;
        if ($domain) {
            $settings = CosmosDB::getSettings($domain);
            if ($settings) {
                $settings['license_status'] = 'active';
                $settings['license_expiry'] = date('Y-m-d', strtotime('+1 month'));
                $settings['payment_id'] = $session->payment_intent ?? null;
                $settings['payment_date'] = date('Y-m-d H:i:s');
                CosmosDB::update($domain, $settings);
            }
        }
        break;
    default:
        echo 'Received unknown event type ' . $event->type;
}

http_response_code(200);
echo json_encode(['status' => 'success']); 