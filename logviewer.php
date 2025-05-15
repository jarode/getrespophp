<?php
require_once __DIR__ . '/crest.php';

$logDir = __DIR__ . '/logs';
$logs = [];

// Get local logs
if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content) {
            $logs[] = [
                'file' => basename($file),
                'content' => $content,
                'time' => filemtime($file),
                'source' => 'local'
            ];
        }
    }
}

// Get Cosmos DB logs
$resourceLink = "dbs/" . CRest::COSMOS_DATABASE . "/colls/" . CRest::COSMOS_CONTAINER_LOGS;
$query = "SELECT * FROM c ORDER BY c.timestamp DESC";
$params = [];

$utcDate = gmdate('D, d M Y H:i:s T');
$token = CRest::build_auth_token('POST', 'docs', $resourceLink, $utcDate, CRest::COSMOS_KEY);

$headers = [
    'Content-Type: application/query+json',
    'x-ms-documentdb-isquery: true',
    'x-ms-date: ' . $utcDate,
    'x-ms-version: 2023-11-15',
    'Authorization: ' . $token
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, CRest::COSMOS_ENDPOINT . $resourceLink . '/docs');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'query' => $query,
    'parameters' => $params
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    $result = json_decode($response, true);
    if (isset($result['Documents'])) {
        foreach ($result['Documents'] as $doc) {
            $logs[] = [
                'file' => 'cosmos_' . $doc['id'],
                'content' => json_encode($doc['data'], JSON_PRETTY_PRINT),
                'time' => strtotime($doc['timestamp']),
                'source' => 'cosmos'
            ];
        }
    }
}

// Sort all logs by time (newest first)
usort($logs, function($a, $b) {
    return $b['time'] - $a['time'];
});
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 0;
        }
        .log-entry {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .source-badge {
            margin-left: 0.5rem;
        }
    </style>
</head>
<body class="container-fluid py-4">
    <h1>Log Viewer</h1>
    
    <?php if (empty($logs)): ?>
        <div class="alert alert-info">No logs found.</div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
            <div class="log-entry">
                <h5>
                    <?= htmlspecialchars($log['file']) ?>
                    <span class="badge <?= $log['source'] === 'cosmos' ? 'bg-primary' : 'bg-secondary' ?> source-badge">
                        <?= ucfirst($log['source']) ?>
                    </span>
                </h5>
                <small class="text-muted"><?= date('Y-m-d H:i:s', $log['time']) ?></small>
                <pre><?= htmlspecialchars($log['content']) ?></pre>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
