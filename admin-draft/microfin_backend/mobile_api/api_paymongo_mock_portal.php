<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock Payment Portal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #F9FAFB; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 90%; width: 350px; }
        .icon { width: 64px; height: 64px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; font-weight: bold; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #111827; }
        p { margin: 0 0 20px; font-size: 14px; color: #6B7280; line-height: 1.5; }
        .amount { font-size: 24px; font-weight: 800; color: #10B981; margin-bottom: 30px; }
        button { background: #0F292B; color: white; border: none; padding: 14px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Payment Simulated</h1>
        <p>This is a mock sandbox environment. The payment via <strong><?php echo htmlspecialchars($_GET['method'] ?? 'Online'); ?></strong> has been successfully simulated.</p>
        <div class="amount">₱ <?php echo number_format(floatval($_GET['amount'] ?? 0), 2); ?></div>
        <button onclick="window.close();">Close Window</button>
        <p style="margin-top: 15px; font-size: 12px; font-style: italic;">(Return to the Microfin app to see your receipt)</p>
    </div>
</body>
</html>
