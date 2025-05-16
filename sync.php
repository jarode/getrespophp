<?php
require_once 'cosmos.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$domain = $data['DOMAIN'] ?? ($_REQUEST['DOMAIN'] ?? null);

if (!$domain) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Domain is required']);
    exit;
}

$cosmos = new CosmosDB();
$license = $cosmos->getLicenseStatus($domain);
$status = strtolower($license['license_status'] ?? 'trial');
$expiry = $license['license_expiry'] ?? null;
$today = date('Y-m-d');
if (!in_array($status, ['trial', 'active']) || ($expiry && $expiry < $today)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Synchronization is available only with an active license or during the trial period, and only if the license is valid.']);
    exit;
}

// --- Synchronization logic here ---
// Example response:
echo json_encode(['success' => true, 'message' => 'Synchronization completed (demo).']); 