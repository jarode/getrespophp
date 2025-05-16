<?php
// create_payment.php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/crest.php');

// Get domain and app_id from JSON POST
$data = json_decode(file_get_contents('php://input'), true);
$domain = $data['DOMAIN'] ?? '';
$appId = $data['APP_ID'] ?? '';
if (empty($domain) || empty($appId)) {
    die(json_encode(['success' => false, 'error' => 'Domain and App ID are required']));
}

// Testowy klucz Stripe
$stripe = new \Stripe\StripeClient('sk_test_51RP8JHPCW5Rb7LaJuEMR6gEUKKOVoEyBZzM5n4qj3ZX4C06NP5nMIFVXYTorJHJx8Ji3xlc3djHFpWblYZ0ZWhl800y6kkd9di');

try {
    // Create checkout session
    $checkout_session = $stripe->checkout->sessions->create([
        'success_url' => "https://$domain/marketplace/app/$appId/?payment=success",
        'cancel_url' => "https://$domain/marketplace/app/$appId/?payment=cancel",
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'pln',
                'product_data' => [
                    'name' => 'Bitrix24 Integration - 1 Year',
                ],
                'unit_amount' => 50000, // 500 PLN
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'metadata' => [
            'domain' => $domain
        ]
    ]);

    // Return checkout URL
    echo json_encode([
        'success' => true,
        'url' => $checkout_session->url
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 