-- Print Cafe SQLite Database Schema

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
