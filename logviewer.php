<?php
$baseDir = __DIR__;  // katalog startowy
$path = isset($_GET['file']) ? realpath($baseDir . '/' . $_GET['file']) : $baseDir;

function listFiles($dir, $base = '') {
    $output = '<ul>';
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = "$dir/$item";
        $relPath = ltrim("$base/$item", '/');
        if (is_dir($fullPath)) {
            $output .= "<li>📁 <strong>$relPath</strong><br>" . listFiles($fullPath, $relPath) . "</li>";
        } else {
            $url = htmlspecialchars($_SERVER['PHP_SELF']) . '?file=' . urlencode($relPath);
            $output .= "<li>📄 <a href=\"$url\">$relPath</a></li>";
        }
    }
    return $output . '</ul>';
}

if (is_file($path)) {
    echo "<h3>📄 Podgląd pliku: <code>" . htmlspecialchars($_GET['file']) . "</code></h3>";
    echo "<pre style='background:#f4f4f4;padding:10px;border:1px solid #ccc;overflow:auto;max-height:600px'>" . 
         htmlspecialchars(file_get_contents($path)) . "</pre>";
    echo "<p><a href='logviewer.php'>🔙 Wróć</a></p>";
} else {
    echo "<h2>📁 Zawartość katalogu startowego: <code>$baseDir</code></h2>";
    echo listFiles($baseDir);
}
?>
