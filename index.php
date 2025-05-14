<?php
require_once(__DIR__ . '/cosmos.php');

// 1. Identyfikator instancji (np. z Bitrix requestu)
$domain = $_REQUEST['DOMAIN'] ?? null;

$config = [];
if ($domain) {
    $config = cosmos_get_by_domain($domain);
}

// 2. Obsługa formularza POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $domain) {
    $api_key = $_POST['api_key'] ?? '';
    $campaign_id = $_POST['campaign_id'] ?? '';

    // aktualizacja w Cosmos
    cosmos_update($domain, [
        'api_key' => $api_key,
        'campaign_id' => $campaign_id,
        'updated_at' => date('c')
    ]);

    $config['api_key'] = $api_key;
    $config['campaign_id'] = $campaign_id;
}
?>

<html>
<head>
    <meta charset="utf-8">
    <title>Index</title>
    <link rel="stylesheet" href="css/app.css">
    <script src="https://code.jquery.com/jquery-3.6.0.js"
            integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk="
            crossorigin="anonymous"></script>
    <style>
        .box {
            background-size: cover;
            background-image: url('back.jpg');
        }
        .background-tint {
            background-color: rgba(255,255,255,.8);
            background-blend-mode: screen;
        }
        .config-form {
            margin-top: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
        }
    </style>
</head>
<body class="container-fluid box background-tint">
<div class="center-block">
    <h1>How to use our app?</h1>
    <h2>Step 1</h2>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</p>
    <h2>Step 2</h2>
    <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco...</p>
    <h2>Step 3</h2>
    <p>Excepteur sint occaecat cupidatat non proident...</p>

    <?php if ($domain): ?>
    <div class="config-form">
        <h3>⚙️ Ustawienia GetResponse dla: <?= htmlspecialchars($domain) ?></h3>
        <form method="POST">
            <label>API Key:</label>
            <input type="text" name="api_key" value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" required class="form-control">
            <br>
            <label>ID kampanii:</label>
            <input type="text" name="campaign_id" value="<?= htmlspecialchars($config['campaign_id'] ?? '') ?>" required class="form-control">
            <br>
            <button type="submit" class="btn btn-primary">💾 Zapisz</button>
        </form>
    </div>
    <?php else: ?>
    <p style="color: red;">Brak informacji o portalu. Aplikacja nie została poprawnie załadowana z Bitrix24 (brakuje parametru `DOMAIN`).</p>
    <?php endif; ?>
</div>
</body>
</html>
