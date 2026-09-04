<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$job_uuid = trim($input['job_id'] ?? $input['job_uuid'] ?? '');

if (empty($job_uuid)) {
    json_response(['success' => false, 'error' => 'Job ID required.'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM print_jobs WHERE job_uuid = ? OR id = ?");
$stmt->execute([$job_uuid, $job_uuid]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['success' => false, 'error' => 'Job not found.'], 404);
}

if (in_array($job['status'], ['COMPLETED', 'CANCELLED'])) {
    json_response(['success' => false, 'error' => 'Cannot cancel job in state: ' . $job['status']], 400);
}

$upd = $pdo->prepare("UPDATE print_jobs SET status = 'CANCELLED', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
$upd->execute([$job['id']]);

$log_stmt = $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, 'CANCELLED', 'Print job cancelled by user/admin.')");
$log_stmt->execute([$job['id']]);

json_response(['success' => true, 'message' => 'Print job cancelled successfully.']);
