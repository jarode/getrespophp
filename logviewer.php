<?php
require_once(__DIR__ . '/cosmos.php');

$domain = $_REQUEST['DOMAIN'] ?? null;
$type = $_REQUEST['type'] ?? null;
$start_date = $_REQUEST['start_date'] ?? null;
$end_date = $_REQUEST['end_date'] ?? null;
$limit = $_REQUEST['limit'] ?? 100;

$logs = [];
if ($domain) {
    $options = [
        'type' => $type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'limit' => $limit
    ];
    $logs = cosmos_get_logs($domain, $options);
}
?>

<html>
<head>
    <meta charset="utf-8">
    <title>Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-entry {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .log-entry.error {
            background-color: #fff3f3;
            border-color: #ffcdd2;
        }
        .log-entry.success {
            background-color: #f3fff3;
            border-color: #c8e6c9;
        }
        .log-timestamp {
            color: #666;
            font-size: 0.9em;
        }
        .log-type {
            font-weight: bold;
            margin-right: 1rem;
        }
        .log-data {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body class="container-fluid py-4">
    <h1>Log Viewer</h1>
    
    <form method="GET" class="mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Domain</label>
                <input type="text" name="DOMAIN" value="<?= htmlspecialchars($domain ?? '') ?>" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <input type="text" name="type" value="<?= htmlspecialchars($type ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Limit</label>
                <input type="number" name="limit" value="<?= htmlspecialchars($limit ?? '100') ?>" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </div>
    </form>

    <?php if ($domain && !empty($logs)): ?>
        <div class="logs-container">
            <?php foreach ($logs as $log): ?>
                <div class="log-entry <?= $log['status'] ?>">
                    <div class="log-header">
                        <span class="log-timestamp"><?= htmlspecialchars($log['timestamp']) ?></span>
                        <span class="log-type"><?= htmlspecialchars($log['type']) ?></span>
                        <span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars($log['status']) ?>
                        </span>
                    </div>
                    <div class="log-data">
                        <pre><?= htmlspecialchars(json_encode($log['data'], JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($domain): ?>
        <div class="alert alert-info">No logs found for the selected criteria.</div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
