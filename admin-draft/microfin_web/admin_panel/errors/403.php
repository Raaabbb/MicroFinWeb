<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden | MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .error-container { text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 400px; }
        h1 { font-size: 72px; margin: 0; color: #64748b; }
        p { color: #64748b; margin: 20px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #4f46e5; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="error-container">
        <div style="background: #fef2f2; color: #b91c1c; padding: 24px; border-radius: 16px; border: 1px solid #fecaca; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(185, 28, 28, 0.08);">
            <h1 style="color: #ef4444; margin: 0; font-size: 64px;">403</h1>
            <h2 style="margin: 10px 0;">Access Denied</h2>
            <p style="color: #991b1b; margin: 0; font-weight: 500;">You don't have permission to access this page. This usually happens if your session has expired.</p>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
            <a href="../../tenant_login/login.php?auth=1" class="btn">Sign In to Continue</a>
            <a href="../admin.php" style="color: #64748b; text-decoration: none; font-size: 14px;">Return to Dashboard</a>
        </div>
    </div>
</body>
</html>
