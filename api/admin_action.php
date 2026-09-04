<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = trim($input['action'] ?? '');
$pin = trim($input['pin'] ?? '');

$stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
$host = $stmt->fetch();

if (!$host) {
    json_response(['success' => false, 'error' => 'Host not found.'], 404);
}

// PIN Verification for security-sensitive admin actions
if (!in_array($action, ['get_queue', 'get_history'])) {
    if (empty($pin) || $pin !== $host['admin_pin']) {
        json_response(['success' => false, 'error' => 'Invalid Host Security PIN.'], 403);
    }
}

switch ($action) {
    case 'update_settings':
        $host_name = trim($input['host_name'] ?? $host['host_name']);
        $require_approval = isset($input['require_approval']) ? ((int)$input['require_approval'] ? 1 : 0) : $host['require_approval'];
        $auto_delete_minutes = max(1, (int)($input['auto_delete_minutes'] ?? $host['auto_delete_minutes']));
        $new_pin = !empty($input['new_pin']) ? trim($input['new_pin']) : $host['admin_pin'];

        $upd_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql')
            ? "UPDATE hosts SET host_name = :name, require_approval = :req_app, auto_delete_minutes = :del_min, admin_pin = :pin, updated_at = NOW() WHERE id = :id"
            : "UPDATE hosts SET host_name = :name, require_approval = :req_app, auto_delete_minutes = :del_min, admin_pin = :pin, updated_at = datetime('now') WHERE id = :id";

        $upd = $pdo->prepare($upd_sql);
        $upd->execute([
            ':name' => $host_name,
            ':req_app' => $require_approval,
            ':del_min' => $auto_delete_minutes,
            ':pin' => $new_pin,
            ':id' => $host['id']
        ]);

        json_response(['success' => true, 'message' => 'Host settings updated successfully.']);
        break;

    case 'select_printer':
        $printer_id = (int)($input['printer_id'] ?? 0);
        if (!$printer_id) {
            json_response(['success' => false, 'error' => 'Printer ID required.'], 400);
        }

        // Reset default for all printers of host
        $pdo->prepare("UPDATE printers SET is_default = 0 WHERE host_id = ?")->execute([$host['id']]);
        $pdo->prepare("UPDATE printers SET is_default = 1 WHERE id = ? AND host_id = ?")->execute([$printer_id, $host['id']]);

        json_response(['success' => true, 'message' => 'Default printer updated.']);
        break;

    case 'approve_job':
        $job_id = (int)($input['job_id'] ?? 0);
        $approve = (bool)($input['approve'] ?? true);
        
        $now_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";
        if ($approve) {
            $upd = $pdo->prepare("UPDATE print_jobs SET approval_status = 'approved', status = 'QUEUED' WHERE id = ? AND status = 'PENDING_APPROVAL'");
            $upd->execute([$job_id]);
            $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, 'APPROVED', 'Print job approved by host admin.')")->execute([$job_id]);
        } else {
            $upd = $pdo->prepare("UPDATE print_jobs SET approval_status = 'rejected', status = 'CANCELLED', completed_at = {$now_sql} WHERE id = ? AND status = 'PENDING_APPROVAL'");
            $upd->execute([$job_id]);
            $pdo->prepare("INSERT INTO print_logs (job_id, event, message) VALUES (?, 'REJECTED', 'Print job rejected by host admin.')")->execute([$job_id]);
        }

        json_response(['success' => true, 'message' => $approve ? 'Job approved and queued.' : 'Job rejected.']);
        break;

    case 'regenerate_qr':
        $new_uuid = generate_code('RPH');
        $now_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";
        $upd = $pdo->prepare("UPDATE hosts SET host_uuid = ?, updated_at = {$now_sql} WHERE id = ?");
        $upd->execute([$new_uuid, $host['id']]);

        json_response(['success' => true, 'new_host_uuid' => $new_uuid, 'message' => 'Host QR Code regenerated. Old QR code is now invalid.']);
        break;

    case 'clear_queue':
        $now_sql = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";
        $upd = $pdo->prepare("UPDATE print_jobs SET status = 'CANCELLED', completed_at = {$now_sql} WHERE host_id = ? AND status IN ('QUEUED', 'PENDING_APPROVAL')");
        $upd->execute([$host['id']]);

        json_response(['success' => true, 'message' => 'Print queue cleared.']);
        break;

    case 'get_queue':
        $stmt = $pdo->prepare("
            SELECT j.*, p.printer_name 
            FROM print_jobs j 
            LEFT JOIN printers p ON j.printer_id = p.id 
            WHERE j.host_id = ? AND j.status IN ('QUEUED', 'PENDING_APPROVAL', 'PROCESSING', 'SENDING_TO_PRINTER', 'PRINTING')
            ORDER BY j.id DESC
        ");
        $stmt->execute([$host['id']]);
        $queue = $stmt->fetchAll();
        json_response(['success' => true, 'queue' => $queue]);
        break;

    case 'get_history':
        $filter = trim($input['filter'] ?? 'today');
        $where_clause = "j.host_id = ?";
        $params = [$host['id']];

        if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
            if ($filter === 'today') {
                $where_clause .= " AND DATE(j.created_at) = CURDATE()";
            } elseif ($filter === 'yesterday') {
                $where_clause .= " AND DATE(j.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            } elseif ($filter === 'week') {
                $where_clause .= " AND DATE(j.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            }
            $today_cond = "DATE(created_at) = CURDATE()";
        } else {
            if ($filter === 'today') {
                $where_clause .= " AND DATE(j.created_at) = DATE('now', 'localtime')";
            } elseif ($filter === 'yesterday') {
                $where_clause .= " AND DATE(j.created_at) = DATE('now', 'localtime', '-1 day')";
            } elseif ($filter === 'week') {
                $where_clause .= " AND DATE(j.created_at) >= DATE('now', 'localtime', '-7 days')";
            }
            $today_cond = "DATE(created_at) = DATE('now', 'localtime')";
        }

        $stmt = $pdo->prepare("
            SELECT j.*, p.printer_name 
            FROM print_jobs j 
            LEFT JOIN printers p ON j.printer_id = p.id 
            WHERE {$where_clause}
            ORDER BY j.id DESC LIMIT 100
        ");
        $stmt->execute($params);
        $history = $stmt->fetchAll();

        // Calculate statistics
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_jobs,
                SUM(CASE WHEN {$today_cond} THEN 1 ELSE 0 END) as today_jobs,
                SUM(CASE WHEN status = 'COMPLETED' THEN (copies * total_pages) ELSE 0 END) as total_pages_printed
            FROM print_jobs WHERE host_id = ?
        ");
        $stats_stmt->execute([$host['id']]);
        $stats = $stats_stmt->fetch();

        json_response([
            'success' => true,
            'history' => $history,
            'stats' => [
                'total_jobs' => (int)($stats['total_jobs'] ?? 0),
                'completed_jobs' => (int)($stats['completed_jobs'] ?? 0),
                'failed_jobs' => (int)($stats['failed_jobs'] ?? 0),
                'today_jobs' => (int)($stats['today_jobs'] ?? 0),
                'total_pages_printed' => (int)($stats['total_pages_printed'] ?? 0)
            ]
        ]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Unknown admin action.'], 400);
}
