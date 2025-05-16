<?php
require_once(__DIR__.'/crest.php');

// Get domain from request or server
$domain = $_REQUEST['DOMAIN'] ?? ($_SERVER['HTTP_HOST'] ?? '');
$cosmos = new CosmosDB();
$settings = $cosmos->getSettings($domain);

// Pobierz ustawienia GetResponse
$apiKey = $settings['getresponse_api_key'] ?? '';
$listId = $settings['getresponse_list_id'] ?? '';

// License status logic (single status)
$status = strtolower($settings['license_status'] ?? 'active'); // trial, active, expired, pending, inactive
$statusLabel = ucfirst($status);
$statusBadge = $status === 'active' ? 'success' : ($status === 'trial' ? 'info' : ($status === 'pending' ? 'warning' : 'danger'));
$expiry = $settings['license_expiry'] ?? '2025-12-31';
$connection = $settings['connection_status'] ?? 'Connected';
$connectionBadge = $connection === 'Connected' ? 'success' : 'danger';
$daysLeft = null;
if ($status === 'trial' && !empty($expiry)) {
    $daysLeft = (strtotime($expiry) - strtotime('today')) / 86400;
    $daysLeft = max(0, (int)$daysLeft);
}

// Sprawdź parametry URL dla komunikatów
$paymentStatus = $_GET['payment'] ?? '';
$message = '';
$messageType = '';

if ($paymentStatus === 'success') {
    $message = 'Płatność zakończona sukcesem!';
    $messageType = 'success';
} elseif ($paymentStatus === 'cancel') {
    $message = 'Płatność anulowana.';
    $messageType = 'warning';
}

// Sprawdź parametry URL dla komunikatów konfiguracji
$configStatus = $_GET['config'] ?? '';
if ($configStatus === 'success') {
    $message = 'Ustawienia zapisane pomyślnie!';
    $messageType = 'success';
} elseif ($configStatus === 'error') {
    $message = 'Wystąpił błąd podczas zapisywania ustawień.';
    $messageType = 'danger';
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
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Bitrix24 ↔ GetResponse Client Panel</h4>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">Dashboard</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab" aria-controls="config" aria-selected="false">Konfiguracja</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sync-tab" data-bs-toggle="tab" data-bs-target="#sync" type="button" role="tab" aria-controls="sync" aria-selected="false">Synchronizacja</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="help-tab" data-bs-toggle="tab" data-bs-target="#help" type="button" role="tab" aria-controls="help" aria-selected="false">Pomoc</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
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
                        <div class="tab-pane fade" id="config" role="tabpanel" aria-labelledby="config-tab">
                            <form id="configForm">
                                <div class="mb-3">
                                    <label for="apiKey" class="form-label">GetResponse API Key:</label>
                                    <input type="text" class="form-control" id="apiKey" name="apiKey" value="<?php echo htmlspecialchars($apiKey); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="listId" class="form-label">GetResponse List ID:</label>
                                    <input type="text" class="form-control" id="listId" name="listId" value="<?php echo htmlspecialchars($listId); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="sync" role="tabpanel" aria-labelledby="sync-tab">
                            <button id="startSync" class="btn btn-success">Rozpocznij synchronizację</button>
                        </div>
                        <div class="tab-pane fade" id="help" role="tabpanel" aria-labelledby="help-tab">
                            <h5>Instrukcje:</h5>
                            <p>1. Wprowadź klucz API GetResponse i ID listy w zakładce "Konfiguracja".</p>
                            <p>2. Użyj przycisku "Rozpocznij synchronizację" w zakładce "Synchronizacja".</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    async function startPayment() {
        try {
            // Pobierz APP_ID z adresu URL (ostatni segment po /)
            const pathParts = window.location.pathname.split('/').filter(Boolean);
            let appId = '';
            const appIdx = pathParts.indexOf('app');
            if (appIdx !== -1 && pathParts.length > appIdx + 1) {
                appId = pathParts[appIdx + 1];
            }
            const response = await fetch('create_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ DOMAIN: "<?php echo htmlspecialchars($domain); ?>", APP_ID: appId })
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

    document.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('payment') === 'success') {
            showPaymentBanner('Payment successful! Your license is now active.', 'success');
        } else if (params.get('payment') === 'cancel') {
            showPaymentBanner('Payment was cancelled. You can try again anytime.', 'warning');
        }

        function showPaymentBanner(message, type) {
            const banner = document.createElement('div');
            banner.className = 'alert alert-' + type + ' text-center';
            banner.style.position = 'fixed';
            banner.style.top = '0';
            banner.style.left = '0';
            banner.style.width = '100%';
            banner.style.zIndex = '9999';
            banner.innerText = message;
            document.body.appendChild(banner);
            setTimeout(() => banner.remove(), 6000);
        }
    });

    // Obsługa zapisu konfiguracji
    document.getElementById('configForm').onsubmit = async function(e) {
        e.preventDefault();
        const apiKey = document.getElementById('apiKey').value;
        const listId = document.getElementById('listId').value;
        const response = await fetch('save_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                DOMAIN: "<?php echo htmlspecialchars($domain); ?>",
                getresponse_api_key: apiKey,
                getresponse_list_id: listId
            })
        });
        const result = await response.json();
        if (result.success) {
            window.location.href = window.location.pathname + '?config=success';
        } else {
            window.location.href = window.location.pathname + '?config=error';
        }
    };

    // Obsługa synchronizacji (przykładowa logika)
    document.getElementById('startSync').onclick = function() {
        alert('Synchronizacja zostanie uruchomiona (tu podłącz backend do synchronizacji).');
    };
</script>
</body>
</html>