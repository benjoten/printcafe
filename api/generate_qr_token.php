<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        json_response(['success' => true]);
    }

    $host_uuid = trim($_GET['host_id'] ?? $_GET['host_uuid'] ?? $_POST['host_uuid'] ?? '');

    if (empty($host_uuid)) {
        $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
        $host = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ? OR id = ?");
        $stmt->execute([$host_uuid, $host_uuid]);
        $host = $stmt->fetch();
    }

    if (!$host) {
        json_response(['success' => false, 'error' => 'Host not found'], 404);
    }

    // Clean up expired or used sessions older than 5 minutes
    try {
        $pdo->exec("DELETE FROM qr_sessions WHERE datetime(expires_at) < datetime('now', '-5 minutes') OR is_used = 1");
    } catch (Exception $e) {}

    // Generate new single-use 60-second token
    $token = generate_code('SESS');
    $now_time = time();
    $expires_time = $now_time + 60; // 60 seconds TTL
    $expires_at = date('Y-m-d H:i:s', $expires_time);

    $ins = $pdo->prepare("INSERT INTO qr_sessions (session_token, host_id, is_used, expires_at) VALUES (?, ?, 0, ?)");
    $ins->execute([$token, $host['id'], $expires_at]);

    // Calculate base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host_header = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = str_replace('/api/generate_qr_token.php', '', $_SERVER['SCRIPT_NAME']);
    $base_url = $protocol . '://' . $host_header . rtrim($script_dir, '/');

    $full_qr_url = $base_url . '/print.php?host_id=' . $host['host_uuid'] . '&token=' . $token;
    $qr_img_src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($full_qr_url);

    json_response([
        'success' => true,
        'token' => $token,
        'host_uuid' => $host['host_uuid'],
        'expires_in_seconds' => 60,
        'expires_at' => $expires_at,
        'full_qr_url' => $full_qr_url,
        'qr_img_src' => $qr_img_src
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 200);
}
