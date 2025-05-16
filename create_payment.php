<?php
// create_payment.php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/crest.php');

// Get domain and app_id from JSON POST
$data = json_decode(file_get_contents('php://input'), true);
$domain = $data['DOMAIN'] ?? '';
$appId = $data['APP_ID'] ?? '';
if (empty($domain)) {
    die(json_encode(['success' => false, 'error' => 'Domain is required']));
}

// First try to get APP_ID from Bitrix24 API
$appInfo = CRest::call('app.info');
// Log response for debugging
file_put_contents(__DIR__.'/appinfo_debug.log', print_r($appInfo, true), FILE_APPEND);
$appId = $appInfo['result']['ID'] ?? '';

// If not found in API, use the one from POST
if ($appId === '' || $appId === null) {
    $appId = $data['APP_ID'] ?? '';
}

// If still not found, error out
if ($appId === '' || $appId === null) {
    die(json_encode(['success' => false, 'error' => 'App ID not found for this domain']));
}

// Save APP_ID to settings in CosmosDB
$settings = CosmosDB::getSettings($domain) ?: [];
$settings['app_id'] = $appId;
CosmosDB::saveSettings($domain, $settings);

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