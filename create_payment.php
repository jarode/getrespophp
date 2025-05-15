<?php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/crest.php');

// Get domain from request
$domain = $_REQUEST['DOMAIN'] ?? '';
if (empty($domain)) {
    die(json_encode(['success' => false, 'error' => 'Domain is required']));
}

// Initialize Stripe
$stripe = new \Stripe\StripeClient(getenv('STRIPE_SECRET_KEY'));

try {
    // Create checkout session
    $checkout_session = $stripe->checkout->sessions->create([
        'success_url' => "https://{$domain}/bitrix/admin/getrespophp_success.php?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url' => "https://{$domain}/bitrix/admin/getrespophp_cancel.php",
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Bitrix24 Integration - 1 Month',
                ],
                'unit_amount' => 2999, // $29.99
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