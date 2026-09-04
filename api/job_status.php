<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$job_uuid = trim($_GET['job_id'] ?? $_GET['job_uuid'] ?? '');

if (empty($job_uuid)) {
    json_response(['success' => false, 'error' => 'Job ID required.'], 400);
}

$stmt = $pdo->prepare("
    SELECT j.*, h.host_name, h.host_uuid, p.printer_name 
    FROM print_jobs j 
    JOIN hosts h ON j.host_id = h.id 
    LEFT JOIN printers p ON j.printer_id = p.id 
    WHERE j.job_uuid = ? OR j.id = ?
");
$stmt->execute([$job_uuid, $job_uuid]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['success' => false, 'error' => 'Print job not found.'], 404);
}

// Get logs
$log_stmt = $pdo->prepare("SELECT * FROM print_logs WHERE job_id = ? ORDER BY id ASC");
$log_stmt->execute([$job['id']]);
$logs = $log_stmt->fetchAll();

// Map status to progress step (0 to 4)
$step_map = [
    'UPLOADED' => 0,
    'PENDING_APPROVAL' => 1,
    'QUEUED' => 1,
    'PROCESSING' => 2,
    'SENDING_TO_PRINTER' => 3,
    'PRINTING' => 4,
    'COMPLETED' => 5,
    'FAILED' => -1,
    'CANCELLED' => -2
];

$step_index = $step_map[$job['status']] ?? 0;

json_response([
    'success' => true,
    'job' => [
        'id' => $job['id'],
        'job_uuid' => $job['job_uuid'],
        'host_name' => $job['host_name'],
        'printer_name' => $job['printer_name'] ?? 'Host Default Printer',
        'file_name' => $job['file_name'],
        'file_type' => $job['file_type'],
        'file_size_formatted' => round($job['file_size'] / (1024 * 1024), 2) . ' MB',
        'copies' => $job['copies'],
        'pages_summary' => $job['page_selection_type'] === 'all' ? 'All Pages' : ($job['page_selection_type'] === 'custom' ? $job['custom_pages'] : "Pages {$job['page_from']}–{$job['page_to']}"),
        'orientation' => ucfirst($job['orientation']),
        'scaling' => ucfirst($job['scaling']),
        'paper_size' => $job['paper_size'],
        'color_mode' => ucfirst($job['color_mode']),
        'status' => $job['status'],
        'step_index' => $step_index,
        'error_message' => $job['error_message'],
        'created_at' => $job['created_at'],
        'completed_at' => $job['completed_at']
    ],
    'logs' => $logs
]);
