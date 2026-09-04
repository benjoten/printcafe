<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = get_db();

    $host_uuid = trim($_GET['host_id'] ?? $_GET['host_uuid'] ?? '');

    if (empty($host_uuid)) {
        $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
        $host = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ? OR id = ?");
        $stmt->execute([$host_uuid, $host_uuid]);
        $host = $stmt->fetch();
    }

    if (!$host) {
        die("Invalid or unconfigured Printer Host. Please contact counter admin.");
    }

    // Clean up old expired sessions
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("DELETE FROM qr_sessions WHERE expires_at < NOW() - INTERVAL 5 MINUTE OR is_used = 1");
        } else {
            $pdo->exec("DELETE FROM qr_sessions WHERE datetime(expires_at) < datetime('now', '-5 minutes') OR is_used = 1");
        }
    } catch (Exception $e) {}

    // Generate unique single-use 60-second token for THIS scan event
    $token = generate_code('SESS');
    $expires_at = date('Y-m-d H:i:s', time() + 60);

    $ins = $pdo->prepare("INSERT INTO qr_sessions (session_token, host_id, is_used, expires_at) VALUES (?, ?, 0, ?)");
    $ins->execute([$token, $host['id'], $expires_at]);

    // Redirect user phone browser to unique event print URL
    $target_url = "print.php?host_id=" . urlencode($host['host_uuid']) . "&token=" . urlencode($token);
    header("Location: " . $target_url, true, 302);
    exit;

} catch (Throwable $e) {
    die("Server error initializing print session: " . htmlspecialchars($e->getMessage()));
}
