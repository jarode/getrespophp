<?php
// stripe_test.php
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stripe Test</title>
</head>
<body>
    <h2>Test Stripe Checkout</h2>
    <form id="stripeForm">
        <label>Domain (opcjonalnie): <input type="text" name="DOMAIN" value="<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>"></label><br><br>
        <button type="submit">Start Stripe Checkout</button>
    </form>
    <div id="result"></div>
    <script>
    document.getElementById('stripeForm').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        const params = new URLSearchParams();
        for (const pair of data) {
            params.append(pair[0], pair[1]);
        }
        document.getElementById('result').innerText = 'Loading...';
        const response = await fetch('create_payment.php', {
            method: 'POST',
            body: params
        });
        const json = await response.json();
        if (json.success) {
            window.location.href = json.url;
        } else {
            document.getElementById('result').innerText = 'Error: ' + json.error;
        }
    };
    </script>
</body>
</html> 