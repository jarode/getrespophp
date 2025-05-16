<?php
require_once(__DIR__.'/crest.php');

// Get domain from request or server
$domain = $_REQUEST['DOMAIN'] ?? ($_SERVER['HTTP_HOST'] ?? '');
$settings = [];
if ($domain) {
    $settings = CosmosDB::getSettings($domain) ?? [];
}

// License status logic (single status)
$status = strtolower($settings['license_status'] ?? 'active'); // trial, active, expired, pending, inactive
$statusLabel = ucfirst($status);
$statusBadge = $status === 'active' ? 'success' : ($status === 'trial' ? 'info' : ($status === 'pending' ? 'warning' : 'danger'));
$expiry = $settings['license_expiry'] ?? '2025-12-31';
$apiKey = $settings['getresponse_api_key'] ?? '************';
$listId = $settings['getresponse_list_id'] ?? 'id0Rg';
$connection = $settings['connection_status'] ?? 'Connected';
$connectionBadge = $connection === 'Connected' ? 'success' : 'danger';
$daysLeft = null;
if ($status === 'trial' && !empty($expiry)) {
    $daysLeft = (strtotime($expiry) - strtotime('today')) / 86400;
    $daysLeft = max(0, (int)$daysLeft);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitrix24 ↔ GetResponse Client Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Bitrix24 ↔ GetResponse Client Panel</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Bitrix24 domain:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="fw-bold"><?php echo htmlspecialchars($domain); ?></span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">License status:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                        </div>
                    </div>
                    <?php if ($status === 'trial'): ?>
                        <div class="alert alert-info">Your free trial is active. <?php echo $daysLeft; ?> day(s) left.</div>
                    <?php elseif ($status === 'expired' || $status === 'inactive'): ?>
                        <div class="alert alert-danger">Your license has expired or is inactive. Please renew to continue using the integration.</div>
                    <?php elseif ($status === 'pending'): ?>
                        <div class="alert alert-warning">Your payment is being processed. Please wait or contact support if this takes too long.</div>
                    <?php endif; ?>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Valid until:</label>
                        <div class="col-sm-8 pt-2">
                            <span><?php echo htmlspecialchars($expiry); ?></span>
                        </div>
                    </div>
                    <hr>
                    <h5>GetResponse Settings</h5>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">API Key:</label>
                        <div class="col-sm-8 pt-2">
                            <input type="password" class="form-control-plaintext d-inline w-auto" value="<?php echo htmlspecialchars($apiKey); ?>" readonly>
                            <button class="btn btn-sm btn-outline-secondary ms-2" disabled>Change</button>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">List (campaign ID):</label>
                        <div class="col-sm-8 pt-2">
                            <input type="text" class="form-control-plaintext d-inline w-auto" value="<?php echo htmlspecialchars($listId); ?>" readonly>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Connection status:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="badge bg-<?php echo $connectionBadge; ?>"><?php echo htmlspecialchars($connection); ?></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if ($status === 'expired' || $status === 'inactive'): ?>
                            <button class="btn btn-warning" onclick="startPayment()">Kup licencję ($29.99/miesiąc)</button>
                        <?php elseif ($status === 'trial'): ?>
                            <button class="btn btn-success" disabled>Trial active</button>
                            <button class="btn btn-warning ms-2" onclick="startPayment()">Kup licencję ($29.99/miesiąc)</button>
                        <?php elseif ($status === 'pending'): ?>
                            <button class="btn btn-secondary" disabled>Payment pending...</button>
                        <?php else: ?>
                            <button class="btn btn-outline-primary" disabled>License active</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($status === 'expired' || $status === 'inactive' || $status === 'pending'): ?>
                        <div class="alert alert-warning mt-4">Integration features are disabled until your license is active.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    async function startPayment() {
        try {
            const response = await fetch('create_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.open(data.url, '_blank');
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
</script>
</body>
</html>