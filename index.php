<?php
require_once(__DIR__.'/crest.php');

// Pobierz domenę z requestu lub serwera
$domain = $_REQUEST['DOMAIN'] ?? ($_SERVER['HTTP_HOST'] ?? '');
$settings = [];
if ($domain) {
    $settings = CosmosDB::getSettings($domain) ?? [];
}

// Przykładowe fallbacki, jeśli brak danych
$status = $settings['license_status'] ?? 'AKTYWNA';
$statusBadge = $status === 'AKTYWNA' ? 'success' : ($status === 'WYGASŁA' ? 'danger' : 'secondary');
$expiry = $settings['license_expiry'] ?? '2025-12-31';
$type = $settings['subscription_type'] ?? 'premium';
$apiKey = $settings['getresponse_api_key'] ?? '************';
$listId = $settings['getresponse_list_id'] ?? 'id0Rg';
$connection = $settings['connection_status'] ?? 'Połączono';
$connectionBadge = $connection === 'Połączono' ? 'success' : 'danger';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel klienta Bitrix24 ↔ GetResponse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Panel klienta Bitrix24 ↔ GetResponse</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Domena Bitrix24:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="fw-bold"><?php echo htmlspecialchars($domain); ?></span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Status licencji:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Ważna do:</label>
                        <div class="col-sm-8 pt-2">
                            <span><?php echo htmlspecialchars($expiry); ?></span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Typ subskrypcji:</label>
                        <div class="col-sm-8 pt-2">
                            <span><?php echo htmlspecialchars($type); ?></span>
                        </div>
                    </div>
                    <hr>
                    <h5>Ustawienia GetResponse</h5>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">API Key:</label>
                        <div class="col-sm-8 pt-2">
                            <input type="password" class="form-control-plaintext d-inline w-auto" value="<?php echo htmlspecialchars($apiKey); ?>" readonly>
                            <button class="btn btn-sm btn-outline-secondary ms-2" disabled>Zmień</button>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Lista (ID kampanii):</label>
                        <div class="col-sm-8 pt-2">
                            <input type="text" class="form-control-plaintext d-inline w-auto" value="<?php echo htmlspecialchars($listId); ?>" readonly>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Stan połączenia:</label>
                        <div class="col-sm-8 pt-2">
                            <span class="badge bg-<?php echo $connectionBadge; ?>"><?php echo htmlspecialchars($connection); ?></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-warning" disabled>Odnowienie/zakup licencji</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>