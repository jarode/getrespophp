<?php
$logDir = __DIR__ . '/tmp/';
$files = glob($logDir . '*.txt');

usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a); // najnowsze na górze
});
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Log Viewer</title>
    <style>
        body { font-family: monospace; background: #f9f9f9; padding: 2rem; }
        h2 { margin-top: 3rem; }
        pre { background: #fff; padding: 1rem; border: 1px solid #ccc; overflow-x: auto; }
        a { font-size: 0.9rem; display: inline-block; margin: 1rem 0; }
    </style>
</head>
<body>
    <h1>Logi aplikacji (tmp/)</h1>
    <a href="?clear=1" onclick="return confirm('Na pewno chcesz usunąć wszystkie logi?')">🗑 Wyczyść wszystkie logi</a>
    <hr>

    <?php
    // Obsługa czyszczenia logów
    if (isset($_GET['clear']) && $_GET['clear'] === '1') {
        foreach ($files as $file) unlink($file);
        echo "<p>✅ Wszystkie logi zostały usunięte.</p>";
        exit;
    }

    if (empty($files)) {
        echo "<p>Brak plików logów w katalogu <code>tmp/</code>.</p>";
    }

    foreach ($files as $file):
        $basename = basename($file);
        $content = file_get_contents($file);
    ?>
        <h2><?= htmlspecialchars($basename) ?></h2>
        <pre><?= htmlspecialchars($content) ?></pre>
    <?php endforeach; ?>
</body>
</html>
