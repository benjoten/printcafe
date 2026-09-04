<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db();
    $pdo->exec("DELETE FROM printers");
    $host_uuid = 'RPH-C92E35BE';
    $printers = [
        ['name' => 'HP LaserJet 1020', 'system_name' => 'HP LaserJet 1020', 'is_virtual' => 0]
    ];

    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ?");
    $stmt->execute([$host_uuid]);
    $host = $stmt->fetch();

    if (!$host) {
        die("Host not found\n");
    }

    echo "Host ID: " . $host['id'] . "\n";

    foreach ($printers as $p) {
        $name = trim($p['name']);
        $sys_name = trim($p['system_name']);
        $is_virtual = $p['is_virtual'] ? 1 : 0;

        $chk = $pdo->prepare("SELECT id FROM printers WHERE host_id = ? AND printer_system_name = ?");
        $chk->execute([$host['id'], $sys_name]);
        $existing = $chk->fetch();

        if ($existing) {
            echo "Updating existing printer ID " . $existing['id'] . "\n";
            $upd = $pdo->prepare("UPDATE printers SET printer_name = ?, is_virtual = ?, status = 'Ready' WHERE id = ?");
            $upd->execute([$name, $is_virtual, $existing['id']]);
        } else {
            echo "Inserting new printer...\n";
            $ins = $pdo->prepare("INSERT INTO printers (host_id, printer_name, printer_system_name, is_virtual, status, is_default) VALUES (?, ?, ?, ?, 'Ready', 0)");
            $ins->execute([(int)$host['id'], $name, $sys_name, $is_virtual]);
        }
    }
    echo "SUCCESS!\n";
} catch (Exception $e) {
    echo "EXCAUGHT: " . $e->getMessage() . "\n";
}
