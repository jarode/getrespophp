<?php
// Bezpieczeństwo - sprawdź czy użytkownik jest zalogowany lub ma odpowiednie uprawnienia
session_start();
if (!isset($_SESSION['authorized']) && !isset($_GET['auth'])) {
    $_SESSION['authorized'] = true; // Tymczasowo - w produkcji należy dodać prawdziwą autoryzację
}

// Funkcja do bezpiecznego wyświetlania ścieżek
function sanitizePath($path) {
    return str_replace(['..', '//'], '', $path);
}

// Pobierz aktualny katalog
$currentDir = isset($_GET['dir']) ? sanitizePath($_GET['dir']) : '';
$baseDir = __DIR__;
$fullPath = $baseDir . '/' . $currentDir;

// Sprawdź czy ścieżka jest bezpieczna
if (!is_dir($fullPath)) {
    $fullPath = $baseDir;
    $currentDir = '';
}

// Pobierz listę plików i katalogów
$items = [];
if ($handle = opendir($fullPath)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $path = $fullPath . '/' . $entry;
            $items[] = [
                'name' => $entry,
                'path' => $currentDir ? $currentDir . '/' . $entry : $entry,
                'is_dir' => is_dir($path),
                'size' => is_file($path) ? filesize($path) : 0,
                'modified' => filemtime($path),
                'content' => is_file($path) ? file_get_contents($path) : null
            ];
        }
    }
    closedir($handle);
}

// Sortuj - najpierw katalogi, potem pliki
usort($items, function($a, $b) {
    if ($a['is_dir'] === $b['is_dir']) {
        return strcasecmp($a['name'], $b['name']);
    }
    return $a['is_dir'] ? -1 : 1;
});
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>File Browser</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
        }
        .file-entry {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .file-path {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 0.5rem;
        }
        .breadcrumb {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .folder-icon {
            color: #ffc107;
        }
        .file-icon {
            color: #6c757d;
        }
    </style>
</head>
<body class="container-fluid py-4">
    <h1>File Browser</h1>
    
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?dir=">Root</a></li>
            <?php
            $pathParts = explode('/', trim($currentDir, '/'));
            $currentPath = '';
            foreach ($pathParts as $part) {
                $currentPath .= '/' . $part;
                echo '<li class="breadcrumb-item"><a href="?dir=' . urlencode(trim($currentPath, '/')) . '">' . htmlspecialchars($part) . '</a></li>';
            }
            ?>
        </ol>
    </nav>

    <!-- Files and directories list -->
    <div class="list-group mb-4">
        <?php foreach ($items as $item): ?>
            <div class="list-group-item">
                <?php if ($item['is_dir']): ?>
                    <a href="?dir=<?= urlencode($item['path']) ?>" class="text-decoration-none">
                        <i class="bi bi-folder-fill folder-icon"></i> 📁 <?= htmlspecialchars($item['name']) ?>
                    </a>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-file-text file-icon"></i> 📄 <?= htmlspecialchars($item['name']) ?>
                        </span>
                        <small class="text-muted">
                            <?= number_format($item['size'] / 1024, 2) ?> KB
                            (<?= date('Y-m-d H:i:s', $item['modified']) ?>)
                        </small>
                    </div>
                    <?php if ($item['content'] !== null): ?>
                        <div class="mt-2">
                            <pre><?= htmlspecialchars($item['content']) ?></pre>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 