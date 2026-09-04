<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$job_uuid = trim($input['job_id'] ?? $input['job_uuid'] ?? '');
$status = strtoupper(trim($input['status'] ?? ''));
$message = trim($input['message'] ?? '');

if (empty($job_uuid) || empty($status)) {
    json_response(['success' => false, 'error' => 'Job ID and status required.'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM print_jobs WHERE job_uuid = ? OR id = ?");
$stmt->execute([$job_uuid, $job_uuid]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['success' => false, 'error' => 'Job not found.'], 404);
}

$valid_statuses = ['PROCESSING', 'SENDING_TO_PRINTER', 'PRINTING', 'COMPLETED', 'FAILED', 'CANCELLED'];
if (!in_array($status, $valid_statuses)) {
    json_response(['success' => false, 'error' => 'Invalid status transition.'], 400);
}

$completed_at_sql = ($status === 'COMPLETED' || $status === 'FAILED') ? ", completed_at = CURRENT_TIMESTAMP" : "";
$error_msg_sql = ($status === 'FAILED') ? ", error_message = :err_msg" : "";

$upd_query = "UPDATE print_jobs SET status = :status {$completed_at_sql} {$error_msg_sql} WHERE id = :id";
$upd_stmt = $pdo->prepare($upd_query);

$params = [':status' => $status, ':id' => $job['id']];
if ($status === 'FAILED') {
    $params[':err_msg'] = $message;
}

$upd_stmt->execute($params);

// Add event log
$log_stmt = $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, ?, ?)");
$log_stmt->execute([$job['id'], $status, $message ?: "Job status updated to {$status}."]);

// If job completed or failed, auto-cleanup option if zero minutes delay configured
if ($status === 'COMPLETED') {
    cleanup_expired_files($pdo);
}

json_response(['success' => true, 'job_id' => $job['id'], 'status' => $status]);
