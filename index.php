<?php
require_once __DIR__ . '/config.php';

$host = null;
$db_error = null;
try {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Cafe - Wireless Touchless Printing</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <a href="index.php" class="brand-logo">
            <div class="brand-icon">🖨️</div>
            <span>Print Cafe</span>
        </a>
        <div>
            <?php if ($host): ?>
                <span class="badge-status badge-online">
                    <span class="pulse-dot"></span> Host Active
                </span>
            <?php endif; ?>
        </div>
    </header>

    <main class="client-container" style="padding-top: 3rem;">
        <div class="glass-panel" style="padding: 2.5rem; text-align: center;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">☕ 🖨️</div>
            <h1 style="font-size: 2.2rem; margin-bottom: 0.75rem;">Welcome to Print Cafe</h1>
            <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.05rem;">
                Browser-based wireless printing system. Scan QR from any device, upload document, customize settings & print effortlessly!
            </p>

            <?php if ($db_error): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 2rem; color: #fca5a5; text-align: left; font-size: 0.9rem;">
                    <strong>⚠️ Database Connection Notice:</strong><br>
                    <?= htmlspecialchars($db_error) ?>
                    <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                        Please verify that the database name <code>if0_42829854_printcafe</code> has been created in your InfinityFree Control Panel.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($host): ?>
                <div style="background: rgba(99,102,241,0.08); border: 1px solid var(--border-accent); padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
                    <div style="font-weight: 700; color: #fff; font-size: 1.1rem;"><?= htmlspecialchars($host['host_name']) ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Host ID: <code><?= htmlspecialchars($host['host_uuid']) ?></code></div>
                </div>

                <a href="host/dashboard.php" class="btn btn-primary btn-block" style="font-size: 1.1rem; padding: 1rem;">
                    🖥️ Open Host Control Dashboard
                </a>
            <?php else: ?>
                <button id="start-host-btn" class="btn btn-primary btn-block" style="font-size: 1.1rem; padding: 1rem;">
                    🚀 START AS HOST
                </button>
            <?php endif; ?>

            <div style="margin-top: 2.5rem; font-size: 0.85rem; color: var(--text-dim); border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                No drivers or software required for client devices. Supported on iOS, Android, Windows & Mac.
            </div>
        </div>
    </main>

    <script>
        document.getElementById('start-host-btn')?.addEventListener('click', async () => {
            try {
                const res = await fetch('api/host_register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ host_name: 'Reception Printer' })
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    alert('Server Error Response:\n' + text.substring(0, 300));
                    return;
                }

                if (data && data.success) {
                    window.location.href = 'host/dashboard.php';
                } else {
                    alert('Error starting host: ' + (data ? data.error : 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error starting host session: ' + err.message);
            }
        });
    </script>
</body>
</html>
