<?php
require_once __DIR__ . '/../config.php';

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

try {
    $pdo = get_db();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $host_name = trim($input['host_name'] ?? 'Reception Printer Host');
    $admin_pin = trim($input['admin_pin'] ?? '123456');

    $now_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";

    // Check if a default host already exists
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();

    if (!$host) {
        // Create new host
        $host_uuid = generate_code('RPH');
        $stmt = $pdo->prepare("
            INSERT INTO hosts (host_uuid, host_name, admin_pin, status, last_seen, require_approval, auto_delete_minutes) 
            VALUES (:host_uuid, :host_name, :admin_pin, 'ONLINE', CURRENT_TIMESTAMP, 0, 30)
        ");
        $stmt->execute([
            ':host_uuid' => $host_uuid,
            ':host_name' => $host_name,
            ':admin_pin' => $admin_pin
        ]);
        
        $host_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM hosts WHERE id = ?");
        $stmt->execute([$host_id]);
        $host = $stmt->fetch();
    } else {
        // Touch last_seen
        $stmt = $pdo->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP, status = 'ONLINE' WHERE id = ?");
        $stmt->execute([$host['id']]);
    }

    // Auto-sync printers for new host
    auto_sync_windows_printers($pdo, $host['id']);

    // Get Printers for this host
    $stmt = $pdo->prepare("SELECT * FROM printers WHERE host_id = ? ORDER BY is_default DESC, id ASC");
    $stmt->execute([$host['id']]);
    $printers = $stmt->fetchAll();

    json_response([
        'success' => true,
        'host' => $host,
        'printers' => $printers,
        'qr_url' => '/print.php?host_id=' . $host['host_uuid']
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 200);
}
