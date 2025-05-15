<?php
$logDir = __DIR__ . '/logs';
$logs = [];

if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content) {
            $logs[] = [
                'file' => basename($file),
                'content' => $content,
                'time' => filemtime($file)
            ];
        }
    }
    // Sortuj po czasie (najnowsze na górze)
    usort($logs, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}
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
    </style>
</head>
<body class="container-fluid py-4">
    <h1>Log Viewer</h1>
    
    <?php if (empty($logs)): ?>
        <div class="alert alert-info">No logs found.</div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
            <div class="log-entry">
                <h5><?= htmlspecialchars($log['file']) ?></h5>
                <small class="text-muted"><?= date('Y-m-d H:i:s', $log['time']) ?></small>
                <pre><?= htmlspecialchars($log['content']) ?></pre>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
