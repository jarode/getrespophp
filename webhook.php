<?php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/crest.php');

// Initialize Stripe with your secret key
$stripe = new \Stripe\StripeClient(getenv('STRIPE_SECRET_KEY'));

// Your webhook secret
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

// Get the webhook payload and signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // Verify webhook signature
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );

    // Handle the event
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            
            // Get domain from metadata
            $domain = $session->metadata->domain;
            
            // Get settings from Cosmos DB
            $settings = $cosmos->get($domain);
            if ($settings) {
                // Update license status
                $settings['license_status'] = 'active';
                $settings['license_expiry'] = date('Y-m-d', strtotime('+1 month'));
                $settings['payment_id'] = $session->payment_intent;
                $settings['payment_date'] = date('Y-m-d H:i:s');
                
                // Save to Cosmos DB
                $cosmos->update($domain, $settings);
            }
            break;
            
        default:
            echo 'Received unknown event type ' . $event->type;
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 