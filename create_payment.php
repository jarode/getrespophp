<?php
// create_payment.php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/crest.php');

// Get domain from JSON POST
$data = json_decode(file_get_contents('php://input'), true);
$domain = $data['DOMAIN'] ?? '';
if (empty($domain)) {
    die(json_encode(['success' => false, 'error' => 'Domain is required']));
}

// Pobierz app_id z CosmosDB
$settings = CosmosDB::getSettings($domain);
$appId = $settings['app_id'] ?? '';

if (empty($appId)) {
    // Try to fetch from Bitrix24 API and save to CosmosDB
    $appInfo = CRest::call('app.info');
    // Log response for debugging
    file_put_contents(__DIR__.'/appinfo_debug.log', print_r($appInfo, true), FILE_APPEND);
    $appId = $appInfo['result']['ID'] ?? '';
    if (!empty($appId)) {
        $settings['app_id'] = $appId;
        CosmosDB::saveSettings($domain, $settings);
    }
}

if (empty($appId)) {
    die(json_encode(['success' => false, 'error' => 'App ID not found for this domain']));
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