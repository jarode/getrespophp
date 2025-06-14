<?php
require_once(__DIR__.'/crest.php');

// Get domain ONLY from request (GET/POST), never from $_SERVER['HTTP_HOST']
$domain = $_REQUEST['DOMAIN'] ?? '';
if (empty($domain)) {
    echo '<div style="margin:2em auto;max-width:600px;text-align:center;color:red;font-weight:bold;">Błąd: Brak domeny Bitrix24 w adresie URL. Uruchom panel z poziomu Bitrix24 lub skontaktuj się z administratorem.</div>';
    exit;
}
$cosmos = new CosmosDB();
$license = $cosmos->getLicenseStatus($domain);
$settings = $cosmos->getSettings($domain);

if ($license && isset($license['license_status'])) {
    $status = strtolower($license['license_status']);
    $expiry = $license['license_expiry'] ?? '2025-12-31';
} else {
    $status = strtolower($settings['license_status'] ?? 'trial');
    $expiry = $settings['license_expiry'] ?? '2025-12-31';
}

$apiKey = $settings['getresponse_api_key'] ?? '';
$listId = $settings['getresponse_list_id'] ?? '';
$connection = $settings['connection_status'] ?? 'Unknown';
$connectionBadge = $connection === 'Connected' ? 'success' : 'danger';
$statusLabel = ucfirst($status);
$statusBadge = $status === 'active' ? 'success' : ($status === 'trial' ? 'info' : ($status === 'pending' ? 'warning' : 'danger'));
$daysLeft = null;
$today = date('Y-m-d');
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

// Licencja ważna tylko jeśli status trial/active i data >= dziś
$licenseValid = in_array($status, ['trial', 'active']) && ($expiry >= $today);
$canConfig = $licenseValid;
$canSync = $licenseValid;
$canPay = in_array($status, ['trial', 'expired', 'inactive', 'pending']) || ($expiry < $today);
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
                            <button class="nav-link<?php if (!$canConfig) echo ' disabled'; ?>" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab" aria-controls="config" aria-selected="false">Configuration</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="help-tab" data-bs-toggle="tab" data-bs-target="#help" type="button" role="tab" aria-controls="help" aria-selected="false">Help</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bitrixdebug-tab" data-bs-toggle="tab" data-bs-target="#bitrixdebug" type="button" role="tab" aria-controls="bitrixdebug" aria-selected="false">Bitrix Debug</button>
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
                            <?php elseif ($status === 'expired' || $status === 'inactive' || $expiry < $today): ?>
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
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label">Connection status:</label>
                                <div class="col-sm-8 pt-2">
                                    <span class="badge bg-<?php echo $connectionBadge; ?>"><?php echo htmlspecialchars($connection); ?></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($canPay): ?>
                                    <button class="btn btn-warning" onclick="startPayment()">Buy license (29.99 PLN/month)</button>
                                <?php endif; ?>
                                <?php if ($status === 'trial'): ?>
                                    <button class="btn btn-success ms-2" disabled>Trial active</button>
                                <?php elseif ($status === 'active'): ?>
                                    <button class="btn btn-outline-primary ms-2" disabled>License active</button>
                                <?php endif; ?>
                            </div>
                            <?php if (!$licenseValid): ?>
                                <div class="alert alert-warning mt-4">Integration features are disabled until your license is active.</div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="config" role="tabpanel" aria-labelledby="config-tab">
                            <?php if ($canConfig): ?>
                                <form id="configForm">
                                    <div class="mb-3">
                                        <label for="apiKey" class="form-label">GetResponse API Key:</label>
                                        <input type="text" class="form-control" id="apiKey" name="apiKey" value="<?php echo htmlspecialchars($apiKey); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="listId" class="form-label">GetResponse List:</label>
                                        <select class="form-control" id="listId" name="listId" required>
                                            <option value="">-- Select list --</option>
                                        </select>
                                        <div id="listLoader" style="display:none;" class="form-text">Loading lists...</div>
                                        <div id="listError" class="text-danger small"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save settings</button>
                                    <button type="button" class="btn btn-success ms-2" id="importAllBtn">Importuj wszystko z GetResponse</button>
                                </form>
                                <div class="mt-3">
                                    <span class="badge bg-<?php echo $connectionBadge; ?>">Connection: <?php echo htmlspecialchars($connection); ?></span>
                                </div>
                                <div id="importResult" class="mt-3" style="display:none;"></div>
                            <?php else: ?>
                                <div class="alert alert-warning mt-3">Configuration is available only with an active license or during the trial period.</div>
                                <form>
                                    <div class="mb-3">
                                        <label for="apiKey" class="form-label">GetResponse API Key:</label>
                                        <input type="text" class="form-control" id="apiKey" name="apiKey" value="<?php echo htmlspecialchars($apiKey); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="listId" class="form-label">GetResponse List ID:</label>
                                        <input type="text" class="form-control" id="listId" name="listId" value="<?php echo htmlspecialchars($listId); ?>" disabled>
                                    </div>
                                    <button type="button" class="btn btn-primary" disabled>Save settings</button>
                                </form>
                                <div class="mt-3">
                                    <span class="badge bg-<?php echo $connectionBadge; ?>">Connection: <?php echo htmlspecialchars($connection); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="help" role="tabpanel" aria-labelledby="help-tab">
                            <h5>Instrukcje:</h5>
                            <ol>
                                <li>Wprowadź klucz API GetResponse i ID listy w zakładce <b>Configuration</b> (dostępne tylko z aktywną licencją lub w okresie trial).</li>
                                <li>Użyj przycisku <b>Start synchronization</b> w zakładce <b>Synchronization</b> (dostępne tylko z aktywną licencją lub w okresie trial).</li>
                                <li>W razie problemów z płatnością lub licencją, skontaktuj się z supportem.</li>
                            </ol>
                        </div>
                        <div class="tab-pane fade" id="bitrixdebug" role="tabpanel" aria-labelledby="bitrixdebug-tab">
                            <h5>Test zapytań crm.contact.list (Bitrix24)</h5>
                            <form method="post">
                                <button type="submit" name="bitrix_debug" class="btn btn-primary">Testuj zapytania</button>
                            </form>
                            <button id="fillAutomationBtn" class="btn btn-success mt-3">Uzupełnij pole automatyzacji</button>
                            <div id="fillAutomationResult" class="mt-3"></div>
                            <button id="testUpdateOriginBtn" class="btn btn-warning mt-3">Testuj update ORIGIN_*</button>
                            <div id="testUpdateOriginResult" class="mt-3"></div>
                            <button id="validateContactsBtn" class="btn btn-info mt-3">Waliduj kontakty</button>
                            <div id="validationResults" class="mt-3"></div>
                            <div id="rawResponses" class="mt-3" style="display: none;">
                                <h6 class="mt-4">Surowe odpowiedzi z API:</h6>
                                <div class="accordion" id="rawResponsesAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#getResponseRaw" aria-expanded="false" aria-controls="getResponseRaw">
                                                GetResponse API Responses
                                            </button>
                                        </h2>
                                        <div id="getResponseRaw" class="accordion-collapse collapse" data-bs-parent="#rawResponsesAccordion">
                                            <div class="accordion-body">
                                                <pre id="getResponseRawContent" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bitrixRaw" aria-expanded="false" aria-controls="bitrixRaw">
                                                Bitrix24 API Responses
                                            </button>
                                        </h2>
                                        <div id="bitrixRaw" class="accordion-collapse collapse" data-bs-parent="#rawResponsesAccordion">
                                            <div class="accordion-body">
                                                <pre id="bitrixRawContent" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
            const response = await fetch('create_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    DOMAIN: "<?php echo htmlspecialchars($domain); ?>"
                })
            });
            const result = await response.json();
            if (result.success && result.url) {
                window.open(result.url, '_blank');
            } else {
                alert(result.error || 'Payment error.');
            }
        } catch (e) {
            alert('Payment error.');
        }
    }

    // Konfiguracja formularza
    <?php if ($canConfig): ?>
    // Automatyczne pobieranie list po wpisaniu API Key
    const apiKeyInput = document.getElementById('apiKey');
    const listIdSelect = document.getElementById('listId');
    const listLoader = document.getElementById('listLoader');
    const listError = document.getElementById('listError');
    let connectionStatus = 'Unknown';

    // Funkcja do pobierania domeny Bitrix24 z URL (jeśli nie ma w PHP)
    function getBitrixDomain() {
        // Najpierw spróbuj z PHP (wygenerowane w kodzie)
        var phpDomain = "<?php echo htmlspecialchars($domain); ?>";
        if (phpDomain && phpDomain.length > 0) return phpDomain;
        // Potem spróbuj z parametrów URL
        const params = new URLSearchParams(window.location.search);
        if (params.has('DOMAIN')) return params.get('DOMAIN');
        // Ostatecznie zwróć pusty string
        return '';
    }

    async function fetchGRLists(apiKey) {
        listLoader.style.display = 'block';
        listError.textContent = '';
        listIdSelect.innerHTML = '<option value="">-- Select list --</option>';
        try {
            const response = await fetch('get_gr_lists.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key: apiKey, domain: getBitrixDomain() })
            });
            const result = await response.json();
            if (result.success && Array.isArray(result.lists)) {
                result.lists.forEach(list => {
                    const opt = document.createElement('option');
                    opt.value = list.id;
                    opt.textContent = list.name + ' (' + list.id + ')';
                    listIdSelect.appendChild(opt);
                });
                // Walidacja połączenia
                const selectedListId = listIdSelect.value;
                if (selectedListId && result.lists.some(l => l.id === selectedListId)) {
                    connectionStatus = 'Connected';
                } else {
                    connectionStatus = 'Not connected';
                }
            } else {
                connectionStatus = 'Not connected';
                listError.textContent = result.error || 'No lists found.';
            }
        } catch (e) {
            connectionStatus = 'Not connected';
            listError.textContent = 'Error fetching lists.';
        }
        listLoader.style.display = 'none';
    }

    apiKeyInput.addEventListener('blur', function() {
        const apiKey = apiKeyInput.value.trim();
        if (apiKey.length > 0) {
            fetchGRLists(apiKey);
        }
    });

    // Jeśli jest już API Key i List ID, pobierz listy i ustaw wybraną
    window.addEventListener('DOMContentLoaded', function() {
        const apiKey = apiKeyInput.value.trim();
        const currentListId = "<?php echo htmlspecialchars($listId); ?>";
        if (apiKey.length > 0) {
            fetchGRLists(apiKey).then(() => {
                if (currentListId) {
                    listIdSelect.value = currentListId;
                }
            });
        }
    });

    document.getElementById('configForm').onsubmit = async function(e) {
        e.preventDefault();
        const apiKey = apiKeyInput.value;
        const listId = listIdSelect.value;
        const domain = getBitrixDomain();
        if (!listId) {
            alert('Please select a list.');
            return;
        }
        // Ustaw connectionStatus na podstawie wybranej listy
        if (listId && Array.from(listIdSelect.options).some(opt => opt.value === listId)) {
            connectionStatus = 'Connected';
        } else {
            connectionStatus = 'Not connected';
        }
        const response = await fetch('save_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                DOMAIN: domain,
                getresponse_api_key: apiKey,
                getresponse_list_id: listId,
                connection_status: connectionStatus
            })
        });
        const result = await response.json();
        if (result.success) {
            window.location.href = window.location.pathname + '?DOMAIN=' + encodeURIComponent(domain) + '&config=success';
        } else {
            alert(result.error || 'Error saving settings.');
        }
    };

    document.getElementById('importAllBtn')?.addEventListener('click', function() {
        if (!confirm('Czy na pewno zaimportować wszystkie kontakty z GetResponse do Bitrix24?')) return;
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Importowanie...';
        fetch('sync.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({DOMAIN: '<?php echo $domain; ?>'})
        })
        .then(r => r.json())
        .then(data => {
            let msg = '';
            if (data.success) {
                msg = `<div class='alert alert-success'>Zaimportowano: ${data.results.imported}, zaktualizowano: ${data.results.updated}, pominięto: ${data.results.skipped} (łącznie w GR: ${data.results.total_getresponse})</div>`;
            } else {
                msg = `<div class='alert alert-danger'>Błąd importu: ${data.error || 'Nieznany błąd'}</div>`;
            }
            document.getElementById('importResult').innerHTML = msg;
            document.getElementById('importResult').style.display = '';
        })
        .catch(e => {
            document.getElementById('importResult').innerHTML = `<div class='alert alert-danger'>Błąd importu: ${e}</div>`;
            document.getElementById('importResult').style.display = '';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Importuj wszystko z GetResponse';
        });
    });
    <?php endif; ?>

    document.getElementById('fillAutomationBtn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Uzupełnianie...';
        const resultDiv = document.getElementById('fillAutomationResult');
        resultDiv.innerHTML = '';
        try {
            const response = await fetch('fill_email_sync_field.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'DOMAIN=' + encodeURIComponent('<?php echo $domain; ?>')
            });
            const data = await response.json();
            if (data.success) {
                resultDiv.innerHTML = `<div class='alert alert-success'>Zaktualizowano: ${data.updated} / ${data.total}<br>Błędy: ${data.errors.length}</div>`;
                if (data.errors.length > 0) {
                    resultDiv.innerHTML += `<pre style='background:#fff;padding:1em;border:1px solid #ccc;'>${JSON.stringify(data.errors, null, 2)}</pre>`;
                }
            } else {
                resultDiv.innerHTML = `<div class='alert alert-danger'>Błąd: ${data.error || 'Nieznany błąd'}</div>`;
            }
        } catch (e) {
            resultDiv.innerHTML = `<div class='alert alert-danger'>Błąd: ${e}</div>`;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Uzupełnij pole automatyzacji';
        }
    });

    document.getElementById('testUpdateOriginBtn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Aktualizowanie...';
        const resultDiv = document.getElementById('testUpdateOriginResult');
        resultDiv.innerHTML = '';
        try {
            // Pobierz pierwszy kontakt
            const listResp = await fetch('test_update_origin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'DOMAIN=' + encodeURIComponent('<?php echo $domain; ?>') + '&action=get_first'
            });
            const listData = await listResp.json();
            if (!listData.success || !listData.contact) {
                resultDiv.innerHTML = `<div class='alert alert-danger'>Błąd pobierania kontaktu: ${listData.error || 'Brak kontaktu'}</div>`;
                btn.disabled = false;
                btn.textContent = 'Testuj update ORIGIN_*';
                return;
            }
            const contact = listData.contact;
            // Wywołaj update ORIGIN_*
            const updateResp = await fetch('test_update_origin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'DOMAIN=' + encodeURIComponent('<?php echo $domain; ?>') + '&action=update&id=' + encodeURIComponent(contact.ID)
            });
            const updateData = await updateResp.json();
            resultDiv.innerHTML = `<div class='alert alert-info'>Odpowiedź API na update:<br><pre>${JSON.stringify(updateData.update_response, null, 2)}</pre></div>`;
            // Pobierz ponownie kontakt
            if (updateData.contact_after) {
                resultDiv.innerHTML += `<div class='alert alert-success'>Kontakt po update:<br><pre>${JSON.stringify(updateData.contact_after, null, 2)}</pre></div>`;
            }
        } catch (e) {
            resultDiv.innerHTML = `<div class='alert alert-danger'>Błąd: ${e}</div>`;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Testuj update ORIGIN_*';
        }
    });

    // Dodaj obsługę walidacji kontaktów
    document.getElementById('validateContactsBtn').addEventListener('click', function() {
        const resultsDiv = document.getElementById('validationResults');
        const rawResponsesDiv = document.getElementById('rawResponses');
        resultsDiv.innerHTML = '<div class="alert alert-info">Walidacja w toku...</div>';
        rawResponsesDiv.style.display = 'none';

        fetch('validate_contacts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                DOMAIN: '<?php echo $domain; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="card">';
                html += '<div class="card-header bg-primary text-white">GetResponse</div>';
                html += '<div class="card-body">';
                html += `<p>Łączna liczba kontaktów: ${data.getresponse.total}</p>`;
                html += `<p>Kontakty z email: ${data.getresponse.with_email}</p>`;
                html += `<p>Kontakty z imieniem: ${data.getresponse.with_name}</p>`;
                html += `<p>Kontakty z email i imieniem: ${data.getresponse.with_both}</p>`;
                if (data.getresponse.errors.length > 0) {
                    html += '<div class="alert alert-warning">';
                    html += '<h6>Błędy:</h6>';
                    html += '<ul>';
                    data.getresponse.errors.forEach(error => {
                        html += `<li>${error.type}: ${JSON.stringify(error)}</li>`;
                    });
                    html += '</ul></div>';
                }
                html += '</div></div>';

                html += '<div class="card mt-3">';
                html += '<div class="card-header bg-primary text-white">Bitrix24</div>';
                html += '<div class="card-body">';
                html += `<p>Łączna liczba kontaktów: ${data.bitrix.total}</p>`;
                html += `<p>Kontakty z email: ${data.bitrix.with_email}</p>`;
                html += `<p>Kontakty z imieniem: ${data.bitrix.with_name}</p>`;
                html += `<p>Kontakty z email i imieniem: ${data.bitrix.with_both}</p>`;
                html += `<p>Kontakty z ORIGIN_*: ${data.bitrix.with_origin}</p>`;
                if (data.bitrix.errors.length > 0) {
                    html += '<div class="alert alert-warning">';
                    html += '<h6>Błędy:</h6>';
                    html += '<ul>';
                    data.bitrix.errors.forEach(error => {
                        html += `<li>${error.type}: ${JSON.stringify(error)}</li>`;
                    });
                    html += '</ul></div>';
                }
                html += '</div></div>';

                resultsDiv.innerHTML = html;

                // Wyświetl surowe odpowiedzi
                rawResponsesDiv.style.display = 'block';
                document.getElementById('getResponseRawContent').textContent = JSON.stringify(data.getresponse.raw_response, null, 2);
                document.getElementById('bitrixRawContent').textContent = JSON.stringify(data.bitrix.raw_response, null, 2);
            } else {
                resultsDiv.innerHTML = `<div class="alert alert-danger">Błąd: ${data.error}</div>`;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = `<div class="alert alert-danger">Błąd: ${error.message}</div>`;
        });
    });
</script>
</body>
</html>