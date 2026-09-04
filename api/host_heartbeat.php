<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$host_uuid = trim($input['host_uuid'] ?? '');
$printers = $input['printers'] ?? [];
$agent_version = trim($input['agent_version'] ?? '1.0.0');

if (empty($host_uuid)) {
    // If no host_uuid provided, find first host
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ?");
    $stmt->execute([$host_uuid]);
    $host = $stmt->fetch();
}

if (!$host) {
    // If specific host_uuid requested wasn't found, try getting default host and update its UUID to match agent, OR create new host
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
    if ($host) {
        if (!empty($host_uuid)) {
            $upd = $pdo->prepare("UPDATE hosts SET host_uuid = ? WHERE id = ?");
            $upd->execute([$host_uuid, $host['id']]);
            $host['host_uuid'] = $host_uuid;
        }
    } else {
        $final_uuid = !empty($host_uuid) ? $host_uuid : generate_code('RPH');
        $ins = $pdo->prepare("INSERT INTO hosts (host_uuid, host_name, admin_pin, status) VALUES (?, 'Reception Printer', '123456', 'ONLINE')");
        $ins->execute([$final_uuid]);
        $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ?");
        $stmt->execute([$final_uuid]);
        $host = $stmt->fetch();
    }
}

// Update last_seen timestamp
$now_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";
$stmt = $pdo->prepare("UPDATE hosts SET last_seen = {$now_sql}, status = 'ONLINE', updated_at = {$now_sql} WHERE id = ?");
$stmt->execute([$host['id']]);

// Update printers list if supplied by agent
if (is_array($printers) && count($printers) > 0) {
    foreach ($printers as $p) {
        $name = trim(is_array($p) ? ($p['name'] ?? '') : $p);
        $sys_name = trim(is_array($p) ? ($p['system_name'] ?? $name) : $p);
        $is_virtual = (is_array($p) && isset($p['is_virtual'])) ? ($p['is_virtual'] ? 1 : 0) : 0;
        
        if (empty($name)) continue;

        // Check if printer exists
        $chk = $pdo->prepare("SELECT id FROM printers WHERE host_id = ? AND printer_system_name = ?");
        $chk->execute([$host['id'], $sys_name]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE printers SET printer_name = ?, is_virtual = ?, status = 'Ready' WHERE id = ?");
            $upd->execute([$name, $is_virtual, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO printers (host_id, printer_name, printer_system_name, is_virtual, status, is_default) VALUES (?, ?, ?, ?, 'Ready', 0)");
            $ins->execute([$host['id'], $name, $sys_name, $is_virtual]);
        }
    }
}

// Clean up expired files asynchronously during heartbeat
cleanup_expired_files($pdo);

json_response([
    'success' => true,
    'host_id' => $host['id'],
    'host_uuid' => $host['host_uuid'],
    'status' => 'ONLINE',
    'require_approval' => (int)$host['require_approval'],
    'timestamp' => date('Y-m-d H:i:s')
]);
