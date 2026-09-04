<?php
// Print Cafe - Central Configuration & Database Bootstrap

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Base Paths
define('BASE_DIR', __DIR__);
define('UPLOAD_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp');
define('DB_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'database');
define('DB_PATH', DB_DIR . DIRECTORY_SEPARATOR . 'printcafe.sqlite');

// Database Selection: Read environment variables if available (e.g. on Render/Cloud), or fallback to constants
$db_type_env = getenv('DB_TYPE') ?: 'sqlite'; // Default to 'sqlite' locally, set to 'mysql' or 'sqlite' in env
define('DB_TYPE', strtolower($db_type_env)); 

// MySQL Connection Parameters
define('DB_HOST', getenv('DB_HOST') ?: 'sql308.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_42829854_printcafe');
define('DB_USER', getenv('DB_USER') ?: 'if0_42829854');
define('DB_PASS', getenv('DB_PASS') ?: 'Printcafe2026');

// Ensure required directories exist
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(DB_DIR)) {
    @mkdir(DB_DIR, 0777, true);
}

// Database Connection Factory
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (DB_TYPE === 'mysql') {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 10
                ]);
                
                // Auto-initialize MySQL schema if tables do not exist
                init_mysql_schema($pdo);
            } else {
                $pdo = new PDO('sqlite:' . DB_PATH);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                
                // Auto-initialize SQLite schema if tables do not exist
                init_sqlite_schema($pdo);
            }
        } catch (PDOException $e) {
            $err_msg = 'Database Connection Failed: ' . $e->getMessage();
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                json_response(['success' => false, 'error' => $err_msg], 200);
            } else {
                throw new Exception($err_msg);
            }
        }
    }
    return $pdo;
}

// Auto MySQL Schema Initialization
function init_mysql_schema($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hosts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                host_uuid VARCHAR(64) UNIQUE NOT NULL,
                host_name VARCHAR(255) NOT NULL,
                admin_pin VARCHAR(32) DEFAULT '123456',
                status VARCHAR(32) DEFAULT 'ONLINE',
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                require_approval INT DEFAULT 0,
                auto_delete_minutes INT DEFAULT 30,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS printers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                host_id INT NOT NULL,
                printer_name VARCHAR(255) NOT NULL,
                printer_system_name VARCHAR(255) NOT NULL,
                is_virtual INT DEFAULT 0,
                status VARCHAR(32) DEFAULT 'Ready',
                is_default INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS print_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_uuid VARCHAR(64) UNIQUE NOT NULL,
                host_id INT NOT NULL,
                printer_id INT,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type VARCHAR(32) NOT NULL,
                file_size INT NOT NULL,
                page_selection_type VARCHAR(32) DEFAULT 'all',
                page_from INT DEFAULT 1,
                page_to INT DEFAULT 1,
                custom_pages VARCHAR(255) DEFAULT '',
                total_pages INT DEFAULT 1,
                copies INT DEFAULT 1,
                orientation VARCHAR(32) DEFAULT 'portrait',
                scaling VARCHAR(32) DEFAULT 'fit',
                margin_type VARCHAR(32) DEFAULT 'default',
                margin_top FLOAT DEFAULT 10.0,
                margin_bottom FLOAT DEFAULT 10.0,
                margin_left FLOAT DEFAULT 10.0,
                margin_right FLOAT DEFAULT 10.0,
                paper_size VARCHAR(32) DEFAULT 'A4',
                color_mode VARCHAR(32) DEFAULT 'color',
                duplex_mode VARCHAR(32) DEFAULT 'single',
                approval_status VARCHAR(32) DEFAULT 'approved',
                status VARCHAR(32) DEFAULT 'QUEUED',
                error_message TEXT,
                user_device VARCHAR(64) DEFAULT 'Mobile',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS print_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_id INT NOT NULL,
                event VARCHAR(64) NOT NULL,
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES print_jobs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {
        // Table creation catch
    }
}

// Auto SQLite Schema Initialization
function init_sqlite_schema($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hosts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            host_uuid TEXT UNIQUE NOT NULL,
            host_name TEXT NOT NULL,
            admin_pin TEXT DEFAULT '123456',
            status TEXT DEFAULT 'ONLINE',
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            require_approval INTEGER DEFAULT 0,
            auto_delete_minutes INTEGER DEFAULT 30,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS printers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            host_id INTEGER NOT NULL,
            printer_name TEXT NOT NULL,
            printer_system_name TEXT NOT NULL,
            is_virtual INTEGER DEFAULT 0,
            status TEXT DEFAULT 'Ready',
            is_default INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS print_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_uuid TEXT UNIQUE NOT NULL,
            host_id INTEGER NOT NULL,
            printer_id INTEGER,
            file_name TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_type TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            page_selection_type TEXT DEFAULT 'all',
            page_from INTEGER DEFAULT 1,
            page_to INTEGER DEFAULT 1,
            custom_pages TEXT DEFAULT '',
            total_pages INTEGER DEFAULT 1,
            copies INTEGER DEFAULT 1,
            orientation TEXT DEFAULT 'portrait',
            scaling TEXT DEFAULT 'fit',
            margin_type TEXT DEFAULT 'default',
            margin_top REAL DEFAULT 10.0,
            margin_bottom REAL DEFAULT 10.0,
            margin_left REAL DEFAULT 10.0,
            margin_right REAL DEFAULT 10.0,
            paper_size TEXT DEFAULT 'A4',
            color_mode TEXT DEFAULT 'color',
            duplex_mode TEXT DEFAULT 'single',
            approval_status TEXT DEFAULT 'approved',
            status TEXT DEFAULT 'QUEUED',
            error_message TEXT DEFAULT '',
            user_device TEXT DEFAULT 'Mobile',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS print_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            event TEXT NOT NULL,
            message TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES print_jobs(id) ON DELETE CASCADE
        );
    ");
}

// Auto-detect and sync Windows installed printers
function auto_sync_windows_printers($pdo, $host_id) {
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM printers WHERE host_id = ?");
        $stmt->execute([$host_id]);
        $cnt = (int)($stmt->fetch()['cnt'] ?? 0);

        if ($cnt > 0) {
            return; // Printers already populated
        }

        @exec('powershell -Command "Get-Printer | Select-Object -Property Name | ConvertTo-Json"', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $json = implode("\n", $output);
            $data = json_decode($json, true);
            if (is_array($data)) {
                if (isset($data['Name'])) $data = [$data];
                
                $virtual_keywords = ['pdf', 'onenote', 'xps', 'anydesk', 'fax', 'microsoft print to pdf', 'adobe pdf'];
                $has_default = false;

                foreach ($data as $item) {
                    $name = trim($item['Name'] ?? '');
                    if (empty($name)) continue;

                    $is_virtual = 0;
                    foreach ($virtual_keywords as $kw) {
                        if (stripos($name, $kw) !== false) {
                            $is_virtual = 1;
                            break;
                        }
                    }

                    $is_default = 0;
                    if (!$is_virtual && !$has_default) {
                        $is_default = 1;
                        $has_default = true;
                    }

                    $ins = $pdo->prepare("INSERT INTO printers (host_id, printer_name, printer_system_name, is_virtual, status, is_default) VALUES (?, ?, ?, ?, 'Ready', ?)");
                    $ins->execute([$host_id, $name, $name, $is_virtual, $is_default]);
                }

                if (!$has_default) {
                    $pdo->prepare("UPDATE printers SET is_default = 1 WHERE host_id = ? LIMIT 1")->execute([$host_id]);
                }
            }
        }
    } catch (Exception $e) {
        // Silent catch
    }
}

// JSON Helper (Sends HTTP 200 so free hosting proxies do not discard JSON body)
function json_response($data, $code = 200) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Generate unique host or job ID (e.g. RPH-8F32A91C or JOB-9B2A4E7F)
function generate_code($prefix = 'RPH') {
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// Cleanup old files
function cleanup_expired_files($pdo) {
    try {
        if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
            $stmt = $pdo->prepare("
                SELECT j.id, j.file_path, h.auto_delete_minutes 
                FROM print_jobs j 
                JOIN hosts h ON j.host_id = h.id 
                WHERE j.status IN ('COMPLETED', 'FAILED', 'CANCELLED')
                AND j.created_at <= DATE_SUB(NOW(), INTERVAL h.auto_delete_minutes MINUTE)
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT j.id, j.file_path, h.auto_delete_minutes 
                FROM print_jobs j 
                JOIN hosts h ON j.host_id = h.id 
                WHERE j.status IN ('COMPLETED', 'FAILED', 'CANCELLED')
                AND datetime(j.created_at, '+' || h.auto_delete_minutes || ' minutes') <= datetime('now')
            ");
        }
        $stmt->execute();
        $jobs = $stmt->fetchAll();
        foreach ($jobs as $job) {
            if (file_exists($job['file_path'])) {
                @unlink($job['file_path']);
            }
        }
    } catch (Exception $e) {
        // Silent catch for cleanup
    }
}
