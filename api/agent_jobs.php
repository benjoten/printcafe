<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$host_uuid = trim($_GET['host_id'] ?? $_GET['host_uuid'] ?? '');

if (empty($host_uuid)) {
    // Select first host
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

// Fetch oldest QUEUED job
$stmt = $pdo->prepare("
    SELECT j.*, p.printer_system_name, p.printer_name 
    FROM print_jobs j 
    LEFT JOIN printers p ON j.printer_id = p.id 
    WHERE j.host_id = ? AND j.status = 'QUEUED' AND j.approval_status = 'approved'
    ORDER BY j.id ASC LIMIT 1
");
$stmt->execute([$host['id']]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['success' => true, 'has_job' => false, 'job' => null]);
}

// Mark job as PROCESSING
$upd = $pdo->prepare("UPDATE print_jobs SET status = 'PROCESSING' WHERE id = ?");
$upd->execute([$job['id']]);

$log_stmt = $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, 'PROCESSING', 'Host agent picked up job for processing.')");
$log_stmt->execute([$job['id']]);

json_response([
    'success' => true,
    'has_job' => true,
    'job' => [
        'id' => $job['id'],
        'job_uuid' => $job['job_uuid'],
        'file_name' => $job['file_name'],
        'file_path' => $job['file_path'],
        'file_type' => $job['file_type'],
        'printer_system_name' => $job['printer_system_name'] ?? 'DEFAULT',
        'printer_name' => $job['printer_name'] ?? 'Default Printer',
        'page_selection_type' => $job['page_selection_type'],
        'page_from' => $job['page_from'],
        'page_to' => $job['page_to'],
        'custom_pages' => $job['custom_pages'],
        'copies' => $job['copies'],
        'orientation' => $job['orientation'],
        'scaling' => $job['scaling'],
        'margin_type' => $job['margin_type'],
        'margin_top' => $job['margin_top'],
        'margin_bottom' => $job['margin_bottom'],
        'margin_left' => $job['margin_left'],
        'margin_right' => $job['margin_right'],
        'paper_size' => $job['paper_size'],
        'color_mode' => $job['color_mode'],
        'duplex_mode' => $job['duplex_mode']
    ]
]);
