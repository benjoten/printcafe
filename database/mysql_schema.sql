-- Print Cafe MySQL Database Schema (InfinityFree / Production)

CREATE TABLE IF NOT EXISTS hosts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_uuid VARCHAR(64) UNIQUE NOT NULL,
    host_name VARCHAR(255) NOT NULL,
    admin_pin VARCHAR(32) DEFAULT '123456',
    status VARCHAR(32) DEFAULT 'ONLINE',
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    require_approval INT DEFAULT 0,
    auto_delete_minutes INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS printers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT NOT NULL,
    printer_name VARCHAR(255) NOT NULL,
    printer_system_name VARCHAR(255) NOT NULL,
    is_virtual INT DEFAULT 0,
    status VARCHAR(32) DEFAULT 'Ready',
    is_default INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    event VARCHAR(64) NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES print_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
