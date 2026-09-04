<?php
require_once __DIR__ . '/../config.php';

$pdo = get_db();
$stmt = $pdo->query("SELECT * FROM hosts ORDER BY id ASC LIMIT 1");
$host = $stmt->fetch();

if (!$host) {
    header('Location: ../index.php');
    exit;
}

// Auto-sync Windows local printers if table is empty
auto_sync_windows_printers($pdo, $host['id']);

// Calculate host server IP address for LAN access
$host_ip = gethostbyname(gethostname());
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . str_replace('/host/dashboard.php', '', $_SERVER['SCRIPT_NAME']);
$qr_full_url = $base_url . '/print.php?host_id=' . $host['host_uuid'];
$qr_img_src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qr_full_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - Print Cafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/qrcode.min.js"></script>
</head>
<body>
    <header class="app-header">
        <a href="../index.php" class="brand-logo">
            <div class="brand-icon">🖨️</div>
            <span>Print Cafe Host</span>
        </a>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div id="host-status-badge" class="badge-status badge-online">
                <span class="pulse-dot"></span> <span id="host-status-text">ONLINE</span>
            </div>
            <button class="btn btn-secondary" onclick="openSettingsModal()" style="padding: 0.5rem 1rem;">⚙️ Settings</button>
        </div>
    </header>

    <main class="container">
        <!-- Top Stats Banner -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div class="glass-panel" style="padding: 1.25rem;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">TOTAL PRINTS</div>
                <div id="stat-total-jobs" style="font-size: 1.8rem; font-weight: 800; font-family: 'Outfit';">0</div>
            </div>
            <div class="glass-panel" style="padding: 1.25rem;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">PAGES PRINTED</div>
                <div id="stat-pages-printed" style="font-size: 1.8rem; font-weight: 800; font-family: 'Outfit'; color: var(--primary);">0</div>
            </div>
            <div class="glass-panel" style="padding: 1.25rem;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">PRINTED TODAY</div>
                <div id="stat-today-jobs" style="font-size: 1.8rem; font-weight: 800; font-family: 'Outfit'; color: var(--accent-emerald);">0</div>
            </div>
            <div class="glass-panel" style="padding: 1.25rem;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">FAILED JOBS</div>
                <div id="stat-failed-jobs" style="font-size: 1.8rem; font-weight: 800; font-family: 'Outfit'; color: var(--accent-rose);">0</div>
            </div>
        </div>

        <div class="grid-3">
            <!-- Left Sidebar: Host Info & QR Code -->
            <div>
                <div class="glass-panel" style="padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.25rem; margin-bottom: 0.25rem;" id="display-host-name"><?= htmlspecialchars($host['host_name']) ?></h3>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">Host ID: <code id="display-host-id"><?= htmlspecialchars($host['host_uuid']) ?></code></div>

                    <!-- Fixed QR Code Container -->
                    <div style="background: #fff; padding: 0.75rem; border-radius: var(--radius-md); display: inline-block; box-shadow: var(--shadow-card); margin-bottom: 1rem;">
                        <div id="qrcode-container" style="width:180px; height:180px; display:flex; align-items:center; justify-content:center;">
                            <img id="qr-img-element" src="<?= $qr_img_src ?>" alt="Host QR Code" style="width:180px; height:180px; display:block;">
                        </div>
                    </div>

                    <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 1rem; word-break: break-all;">
                        Scan QR Code to Print
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <button class="btn btn-secondary" onclick="downloadQR()" style="font-size: 0.85rem; padding: 0.5rem 0.85rem;">📥 Download</button>
                        <button class="btn btn-secondary" onclick="printQRTag()" style="font-size: 0.85rem; padding: 0.5rem 0.85rem;">🖨️ Print Tag</button>
                    </div>
                </div>

                <!-- Printer Selection Panel -->
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
                        <span>Active Printer</span>
                        <span id="printer-status-badge" class="badge-status badge-online" style="font-size: 0.75rem;">🟢 Ready</span>
                    </h4>

                    <div class="form-group">
                        <select id="printer-select" class="form-control" onchange="changePrinter(this.value)">
                            <option value="">Scanning system printers...</option>
                        </select>
                    </div>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
                        <input type="checkbox" id="filter-virtual-chk" checked onchange="loadPrinters()">
                        Show physical printers only
                    </label>
                </div>
            </div>

            <!-- Main Content Area: Queue & History -->
            <div>
                <!-- Approval Requests (If Require Approval is ON) -->
                <div id="approval-section" class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem; display: none; border-color: rgba(245, 158, 11, 0.4);">
                    <h3 style="font-size: 1.1rem; color: var(--accent-amber); margin-bottom: 1rem;">⚠️ Pending Print Approvals</h3>
                    <div id="approval-list"></div>
                </div>

                <!-- Live Print Queue -->
                <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.15rem;">📋 Live Print Queue</h3>
                        <button class="btn btn-danger" onclick="promptClearQueue()" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Clear Queue</button>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Job ID</th>
                                    <th>Document</th>
                                    <th>Device</th>
                                    <th>Pages</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="queue-tbody">
                                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No active jobs in queue</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Print History & Logs -->
                <div class="glass-panel" style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.15rem;">📜 Print History</h3>
                        <select id="history-filter" class="form-control" style="width: auto; padding: 0.4rem 0.8rem; font-size: 0.85rem;" onchange="loadHistory()">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="week">Last 7 Days</option>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>File Name</th>
                                    <th>Pages</th>
                                    <th>Copies</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="history-tbody">
                                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Loading print history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Settings Modal -->
    <div id="settings-modal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem;">⚙️ Host Admin Settings</h3>
            <div class="form-group">
                <label class="form-label">Host Name</label>
                <input type="text" id="setting-host-name" class="form-control" value="<?= htmlspecialchars($host['host_name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Require Print Approval</label>
                <select id="setting-require-approval" class="form-control">
                    <option value="0" <?= !$host['require_approval'] ? 'selected' : '' ?>>Auto Print (Immediate)</option>
                    <option value="1" <?= $host['require_approval'] ? 'selected' : '' ?>>Require Host Admin Approval</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Auto-Delete Temporary Files (Minutes)</label>
                <input type="number" id="setting-auto-delete" class="form-control" value="<?= (int)$host['auto_delete_minutes'] ?>" min="1" max="1440">
            </div>
            <div class="form-group">
                <label class="form-label">Host Security PIN</label>
                <input type="password" id="setting-pin" class="form-control" placeholder="Enter Host PIN" value="123456">
            </div>
            
            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between;">
                <button class="btn btn-danger" onclick="regenerateQR()" style="font-size: 0.85rem;">🔄 Regenerate QR Code</button>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary" onclick="closeSettingsModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveSettings()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Printable QR Tag (For Printing) -->
    <div id="printable-qr-tag" style="display: none;">
        <h1 style="font-size: 28pt; margin-bottom: 10pt; color: #000;">Print Cafe</h1>
        <h2 style="font-size: 18pt; color: #333; margin-bottom: 20pt;" id="print-tag-host-name"><?= htmlspecialchars($host['host_name']) ?></h2>
        <div id="printable-qrcode-container" style="display: flex; justify-content: center; margin: 20pt 0;">
            <img src="<?= $qr_img_src ?>" alt="Host QR Code Tag" width="260" height="260" style="display: inline-block; border: 2px solid #000; padding: 10px; background: #fff;">
        </div>
        <p style="font-size: 14pt; margin-top: 15pt; color: #111;">Scan QR Code with any phone/tablet to print to this printer.</p>
        <p style="font-size: 11pt; color: #555; margin-top: 10pt;">Host ID: <?= htmlspecialchars($host['host_uuid']) ?></p>
    </div>

    <script>
        const qrUrl = <?= json_encode($qr_full_url) ?>;
        const hostUuid = <?= json_encode($host['host_uuid']) ?>;

        window.addEventListener('DOMContentLoaded', () => {
            loadPrinters();
            startPolling();
        });

        // Polling loop for queue, heartbeat, printers & history
        function startPolling() {
            fetchQueue();
            fetchHistory();
            loadPrinters();
            setInterval(fetchQueue, 3000);
            setInterval(fetchHistory, 5000);
            setInterval(loadPrinters, 5000);
        }

        async function fetchQueue() {
            try {
                const res = await fetch('../api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_queue' })
                });
                const data = await res.json();
                if (data.success) {
                    renderQueue(data.queue);
                }
            } catch (e) {}
        }

        function renderQueue(queue) {
            const tbody = document.getElementById('queue-tbody');
            const approvalSec = document.getElementById('approval-section');
            const approvalList = document.getElementById('approval-list');
            
            const pendingJobs = queue.filter(j => j.status === 'PENDING_APPROVAL');
            const activeJobs = queue.filter(j => j.status !== 'PENDING_APPROVAL');

            // Render Pending Approval Cards
            if (pendingJobs.length > 0) {
                approvalSec.style.display = 'block';
                approvalList.innerHTML = pendingJobs.map(j => `
                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.05); padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 0.5rem;">
                        <div>
                            <strong>${j.file_name}</strong> (${j.copies} copies, ${j.total_pages} pages)
                            <div style="font-size: 0.8rem; color: var(--text-muted);">${j.user_device} • Job ${j.job_uuid}</div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="approveJob(${j.id}, true)" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">Approve</button>
                            <button class="btn btn-danger" onclick="approveJob(${j.id}, false)" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">Reject</button>
                        </div>
                    </div>
                `).join('');
            } else {
                approvalSec.style.display = 'none';
            }

            // Render Queue Table
            if (activeJobs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No active jobs in queue</td></tr>';
                return;
            }

            tbody.innerHTML = activeJobs.map(j => `
                <tr>
                    <td><code>${j.job_uuid}</code></td>
                    <td><strong>${j.file_name}</strong></td>
                    <td>${j.user_device}</td>
                    <td>${j.page_selection_type === 'all' ? 'All (' + j.total_pages + ')' : j.page_from + '-' + j.page_to}</td>
                    <td><span class="badge-status ${getStatusBadgeClass(j.status)}">${j.status}</span></td>
                    <td>
                        <button class="btn btn-danger" onclick="cancelJob('${j.job_uuid}')" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Cancel</button>
                    </td>
                </tr>
            `).join('');
        }

        async function fetchHistory() {
            const filter = document.getElementById('history-filter').value;
            try {
                const res = await fetch('../api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_history', filter })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('stat-total-jobs').innerText = data.stats.total_jobs;
                    document.getElementById('stat-pages-printed').innerText = data.stats.total_pages_printed;
                    document.getElementById('stat-today-jobs').innerText = data.stats.today_jobs;
                    document.getElementById('stat-failed-jobs').innerText = data.stats.failed_jobs;

                    const tbody = document.getElementById('history-tbody');
                    if (data.history.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No print history records</td></tr>';
                        return;
                    }

                    tbody.innerHTML = data.history.map(j => `
                        <tr>
                            <td>${new Date(j.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</td>
                            <td>${j.file_name}</td>
                            <td>${j.total_pages}</td>
                            <td>${j.copies}</td>
                            <td><span class="badge-status ${getStatusBadgeClass(j.status)}">${j.status}</span></td>
                        </tr>
                    `).join('');
                }
            } catch (e) {}
        }

        function loadHistory() { fetchHistory(); }

        function getStatusBadgeClass(status) {
            if (status === 'COMPLETED') return 'badge-online';
            if (status === 'FAILED' || status === 'CANCELLED') return 'badge-offline';
            return 'badge-pending';
        }

        async function loadPrinters() {
            const filterVirtual = document.getElementById('filter-virtual-chk').checked;
            try {
                const res = await fetch(`../api/get_host.php?host_id=${hostUuid}`);
                const data = await res.json();
                if (data.success) {
                    const select = document.getElementById('printer-select');
                    let printers = data.printers || [];

                    // Update Online Badge
                    const statusText = document.getElementById('host-status-text');
                    const statusBadge = document.getElementById('host-status-badge');
                    if (data.host.is_online) {
                        statusText.innerText = 'ONLINE';
                        statusBadge.className = 'badge-status badge-online';
                    } else {
                        statusText.innerText = 'AGENT OFFLINE';
                        statusBadge.className = 'badge-status badge-pending';
                    }

                    if (filterVirtual) {
                        printers = printers.filter(p => !p.is_virtual);
                    }
                    if (printers.length === 0) {
                        select.innerHTML = '<option value="">No physical printers detected. Uncheck filter to view all.</option>';
                        return;
                    }

                    const currentSelection = select.value;
                    select.innerHTML = printers.map(p => `
                        <option value="${p.id}" ${(p.is_default || p.id == currentSelection) ? 'selected' : ''}>
                            ${p.printer_name} ${p.is_default ? '(Default)' : ''}
                        </option>
                    `).join('');
                }
            } catch(e) {}
        }

        async function changePrinter(printerId) {
            if (!printerId) return;
            const pin = prompt('Enter Host Security PIN to change printer:', '123456');
            if (!pin) return;

            const res = await fetch('../api/admin_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'select_printer', printer_id: printerId, pin })
            });
            const data = await res.json();
            if (data.success) {
                alert('Active printer updated.');
                loadPrinters();
            } else {
                alert('Error: ' + data.error);
                loadPrinters();
            }
        }

        async function approveJob(jobId, approve) {
            const pin = '123456';
            const res = await fetch('../api/admin_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve_job', job_id: jobId, approve, pin })
            });
            fetchQueue();
        }

        async function cancelJob(jobUuid) {
            if (!confirm('Cancel this print job?')) return;
            await fetch('../api/cancel_job.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: jobUuid })
            });
            fetchQueue();
        }

        async function promptClearQueue() {
            const pin = prompt('Enter Host Security PIN to clear print queue:', '123456');
            if (!pin) return;
            const res = await fetch('../api/admin_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_queue', pin })
            });
            const data = await res.json();
            if (data.success) {
                alert('Queue cleared.');
                fetchQueue();
            } else {
                alert('Error: ' + data.error);
            }
        }

        function openSettingsModal() { document.getElementById('settings-modal').classList.add('active'); }
        function closeSettingsModal() { document.getElementById('settings-modal').classList.remove('active'); }

        async function saveSettings() {
            const hostName = document.getElementById('setting-host-name').value;
            const requireApproval = document.getElementById('setting-require-approval').value;
            const autoDelete = document.getElementById('setting-auto-delete').value;
            const pin = document.getElementById('setting-pin').value;

            const res = await fetch('../api/admin_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_settings',
                    host_name: hostName,
                    require_approval: requireApproval,
                    auto_delete_minutes: autoDelete,
                    pin
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('Settings saved successfully.');
                closeSettingsModal();
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        }

        async function regenerateQR() {
            if (!confirm('Regenerating the QR code will invalidate the existing printed QR code. Continue?')) return;
            const pin = document.getElementById('setting-pin').value;
            const res = await fetch('../api/admin_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'regenerate_qr', pin })
            });
            const data = await res.json();
            if (data.success) {
                alert('QR Code regenerated!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        }

        function downloadQR() {
            const img = document.getElementById('qr-img-element');
            if (!img) return;

            // Convert image to downloadable PNG via canvas
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth || 220;
            canvas.height = img.naturalHeight || 220;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const link = document.createElement('a');
            link.download = `PrintCafe_QR_${hostUuid}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        function printQRTag() {
            window.print();
        }
    </script>
</body>
</html>
