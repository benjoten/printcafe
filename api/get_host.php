<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$host_uuid = trim($_GET['host_id'] ?? $_GET['host_uuid'] ?? '');

if (empty($host_uuid)) {
    // Fallback to first host if none specified
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ? OR id = ?");
    $stmt->execute([$host_uuid, $host_uuid]);
    $host = $stmt->fetch();
}

if (!$host) {
    // Fallback to first host if specified host_uuid wasn't found
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
    if (!$host) {
        $new_uuid = generate_code('RPH');
        $ins = $pdo->prepare("INSERT INTO hosts (host_uuid, host_name, admin_pin, status) VALUES (?, 'Reception Printer', '123456', 'ONLINE')");
        $ins->execute([$new_uuid]);
        $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
        $host = $stmt->fetch();
    }
}

// Auto-sync Windows local printers if table is empty
auto_sync_windows_printers($pdo, $host['id']);

// Calculate online status based on 45s heartbeat threshold
$last_seen_time = strtotime($host['last_seen']);
$now = time();
$is_online = ($now - $last_seen_time) <= 45; // 45 seconds grace period

// Fetch printers for this host
$stmt = $pdo->prepare("SELECT * FROM printers WHERE host_id = ? ORDER BY is_default DESC, id ASC");
$stmt->execute([$host['id']]);
$printers = $stmt->fetchAll();

// Get active / default printer
$active_printer = null;
foreach ($printers as $p) {
    if ($p['is_default']) {
        $active_printer = $p;
        break;
    }
}
if (!$active_printer && count($printers) > 0) {
    $active_printer = $printers[0];
}

json_response([
    'success' => true,
    'host' => [
        'id' => $host['id'],
        'host_uuid' => $host['host_uuid'],
        'host_name' => $host['host_name'],
        'is_online' => $is_online,
        'status' => $is_online ? 'ONLINE' : 'OFFLINE',
        'last_seen' => $host['last_seen'],
        'require_approval' => (bool)$host['require_approval'],
        'auto_delete_minutes' => $host['auto_delete_minutes']
    ],
    'active_printer' => $active_printer,
    'printers' => $printers
]);
