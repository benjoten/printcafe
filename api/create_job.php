<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$host_uuid = trim($input['host_id'] ?? $input['host_uuid'] ?? '');
$file_path = trim($input['file_path'] ?? '');
$file_name = trim($input['file_name'] ?? 'document.pdf');
$file_type = strtolower(trim($input['file_type'] ?? 'pdf'));
$file_size = (int)($input['file_size'] ?? 0);

if (empty($host_uuid) || empty($file_path) || !file_exists($file_path)) {
    json_response(['success' => false, 'error' => 'Invalid file or host identification.'], 400);
}

// Find host
$stmt = $pdo->prepare("SELECT * FROM hosts WHERE host_uuid = ? OR id = ?");
$stmt->execute([$host_uuid, $host_uuid]);
$host = $stmt->fetch();

if (!$host) {
    json_response(['success' => false, 'error' => 'Target Host Printer not found.'], 404);
}

// Find active printer for host
$printer_id = (int)($input['printer_id'] ?? 0);
if (!$printer_id) {
    $p_stmt = $pdo->prepare("SELECT id FROM printers WHERE host_id = ? ORDER BY is_default DESC LIMIT 1");
    $p_stmt->execute([$host['id']]);
    $pr = $p_stmt->fetch();
    $printer_id = $pr ? $pr['id'] : null;
}

$job_uuid = generate_code('JOB');

// Determine status based on host approval setting
$require_approval = (int)$host['require_approval'];
$approval_status = $require_approval ? 'pending' : 'approved';
$job_status = $require_approval ? 'PENDING_APPROVAL' : 'QUEUED';

// Print parameters
$page_selection_type = trim($input['page_selection_type'] ?? 'all');
$page_from = max(1, (int)($input['page_from'] ?? 1));
$page_to = max(1, (int)($input['page_to'] ?? 1));
$custom_pages = trim($input['custom_pages'] ?? '');
$total_pages = max(1, (int)($input['total_pages'] ?? 1));
$copies = max(1, (int)($input['copies'] ?? 1));
$orientation = in_array(strtolower($input['orientation'] ?? ''), ['portrait', 'landscape']) ? strtolower($input['orientation']) : 'portrait';
$scaling = trim($input['scaling'] ?? 'fit');
$margin_type = trim($input['margin_type'] ?? 'default');
$margin_top = (float)($input['margin_top'] ?? 10.0);
$margin_bottom = (float)($input['margin_bottom'] ?? 10.0);
$margin_left = (float)($input['margin_left'] ?? 10.0);
$margin_right = (float)($input['margin_right'] ?? 10.0);
$paper_size = trim($input['paper_size'] ?? 'A4');
$color_mode = trim($input['color_mode'] ?? 'color');
$duplex_mode = trim($input['duplex_mode'] ?? 'single');
$user_device = trim($input['user_device'] ?? 'Mobile');

$stmt = $pdo->prepare("
    INSERT INTO print_jobs (
        job_uuid, host_id, printer_id, file_name, file_path, file_type, file_size,
        page_selection_type, page_from, page_to, custom_pages, total_pages, copies,
        orientation, scaling, margin_type, margin_top, margin_bottom, margin_left, margin_right,
        paper_size, color_mode, duplex_mode, approval_status, status, user_device
    ) VALUES (
        :job_uuid, :host_id, :printer_id, :file_name, :file_path, :file_type, :file_size,
        :page_selection_type, :page_from, :page_to, :custom_pages, :total_pages, :copies,
        :orientation, :scaling, :margin_type, :margin_top, :margin_bottom, :margin_left, :margin_right,
        :paper_size, :color_mode, :duplex_mode, :approval_status, :status, :user_device
    )
");

$stmt->execute([
    ':job_uuid' => $job_uuid,
    ':host_id' => $host['id'],
    ':printer_id' => $printer_id,
    ':file_name' => $file_name,
    ':file_path' => $file_path,
    ':file_type' => $file_type,
    ':file_size' => $file_size,
    ':page_selection_type' => $page_selection_type,
    ':page_from' => $page_from,
    ':page_to' => $page_to,
    ':custom_pages' => $custom_pages,
    ':total_pages' => $total_pages,
    ':copies' => $copies,
    ':orientation' => $orientation,
    ':scaling' => $scaling,
    ':margin_type' => $margin_type,
    ':margin_top' => $margin_top,
    ':margin_bottom' => $margin_bottom,
    ':margin_left' => $margin_left,
    ':margin_right' => $margin_right,
    ':paper_size' => $paper_size,
    ':color_mode' => $color_mode,
    ':duplex_mode' => $duplex_mode,
    ':approval_status' => $approval_status,
    ':status' => $job_status,
    ':user_device' => $user_device
]);

$job_id = $pdo->lastInsertId();

// Log initial event
$log_stmt = $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, ?, ?)");
$log_msg = $require_approval ? 'Job submitted and pending host approval.' : 'Job queued for printing.';
$log_stmt->execute([$job_id, 'CREATED', $log_msg]);

json_response([
    'success' => true,
    'job_id' => $job_id,
    'job_uuid' => $job_uuid,
    'status' => $job_status,
    'require_approval' => (bool)$require_approval,
    'message' => $log_msg
]);
