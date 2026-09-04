<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$json_input = json_encode([
    'host_uuid' => 'RPH-C92E35BE',
    'agent_version' => '1.0.0',
    'printers' => [
        ['name' => 'HP LaserJet 1020', 'system_name' => 'HP LaserJet 1020', 'is_virtual' => 0]
    ]
]);

// Include config and test heartbeat logic
require_once __DIR__ . '/../config.php';

$pdo = get_db();

$input = json_decode($json_input, true);
$host_uuid = trim($input['host_uuid'] ?? '');
$printers = $input['printers'] ?? [];
$agent_version = trim($input['agent_version'] ?? '1.0.0');

if (empty($host_uuid)) {
    $stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
    $host = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ?");
    $stmt->execute([$host_uuid]);
    $host = $stmt->fetch();
}

if (!$host) {
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

echo "Matched Host ID: " . $host['id'] . " UUID: " . $host['host_uuid'] . "\n";

// Use ANSI standard CURRENT_TIMESTAMP which works on both SQLite and MySQL!
$stmt = $pdo->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP, status = 'ONLINE', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$host['id']]);

echo "Updated last_seen successfully!\n";

if (is_array($printers) && count($printers) > 0) {
    foreach ($printers as $p) {
        $name = trim(is_array($p) ? ($p['name'] ?? '') : $p);
        $sys_name = trim(is_array($p) ? ($p['system_name'] ?? $name) : $p);
        $is_virtual = (is_array($p) && isset($p['is_virtual'])) ? ($p['is_virtual'] ? 1 : 0) : 0;
        
        if (empty($name)) continue;

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

echo "Printers updated successfully!\n";

cleanup_expired_files($pdo);

echo "Heartbeat test COMPLETED SUCCESSFULLY!\n";
