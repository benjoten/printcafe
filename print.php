<?php
require_once __DIR__ . '/config.php';
$host_id = trim($_GET['host_id'] ?? $_GET['host_uuid'] ?? '');
$token = trim($_GET['token'] ?? '');

// Auto-redirect to scan.php to issue a fresh 60s event token if token is missing
if (empty($token) && !empty($host_id)) {
    header("Location: scan.php?host_id=" . urlencode($host_id), true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Print Cafe - Print Document</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- PDF.js CDN for Client Visual PDF Page Preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
</head>
<body>
    <header class="app-header" style="padding: 1rem 1.25rem;">
        <a href="#" class="brand-logo" style="font-size: 1.2rem;">
            <div class="brand-icon" style="width:32px; height:32px; font-size:1rem;">🖨️</div>
            <span>Print Cafe</span>
        </a>
        <div id="host-status-indicator">
            <span class="badge-status badge-pending"><span class="pulse-dot"></span> Checking Host...</span>
        </div>
    </header>

    <main class="client-container">
        <!-- QR Token Expired Screen -->
        <div id="token-expired-card" class="glass-panel" style="padding: 2rem; text-align: center; display: none;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">⏳</div>
            <h2 style="font-size: 1.5rem; color: var(--accent-rose); margin-bottom: 0.5rem;">QR Code Link Expired</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;" id="expired-reason-text">
                This QR Code link has expired (60-second time limit) or has already been used for printing.
            </p>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: var(--radius-sm); font-size: 0.9rem; margin-bottom: 1.5rem; text-align: left;">
                <div style="font-weight: 700; color: var(--accent-rose); margin-bottom: 0.25rem;">🔒 Security & Privacy Notice</div>
                For security and to prevent unauthorized printing, counter QR links expire after 60 seconds or after 1 print request.
            </div>
            <p style="font-size: 0.95rem; color: var(--text-main); font-weight: 600;">
                📸 Please rescan the live counter QR code to upload & print your file.
            </p>
        </div>

        <!-- Host Offline Screen -->
        <div id="host-offline-card" class="glass-panel" style="padding: 2rem; text-align: center; display: none;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
            <h2 style="font-size: 1.5rem; color: var(--accent-rose); margin-bottom: 0.5rem;">Printer Host Offline</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;" id="offline-reason-text">
                The target printer host PC is currently unavailable or offline.
            </p>
            <button class="btn btn-secondary btn-block" onclick="checkHostStatus()">🔄 Try Again</button>
        </div>

        <!-- Main Flow Container -->
        <div id="client-flow-card">
            <!-- Host Title Card -->
            <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">TARGET PRINTER</div>
                    <div style="font-size: 1.1rem; font-weight: 700;" id="display-printer-name">Reception Printer</div>
                </div>
                <div style="text-align: right;">
                    <span class="badge-status badge-online" id="host-online-badge">🟢 Ready</span>
                    <div style="font-size: 0.75rem; color: var(--accent-amber); font-weight: 600; margin-top: 0.35rem;" id="session-timer-badge">
                        ⏱️ Session: <span id="session-countdown-sec">60</span>s
                    </div>
                </div>
            </div>

            <!-- STEP 1: Upload Document -->
            <div id="step-1-upload" class="glass-panel" style="padding: 1.5rem;">
                <h3 style="font-size: 1.2rem; margin-bottom: 1rem; text-align: center;">Upload Document</h3>
                
                <div class="dropzone" id="dropzone" onclick="document.getElementById('file-input').click()">
                    <div class="dropzone-icon">📄</div>
                    <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.35rem;">Choose or Drop File</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                        Supported Formats: PDF, JPG, PNG
                    </div>
                    <button class="btn btn-primary" type="button" style="pointer-events: none;">Select File</button>
                    <input type="file" id="file-input" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" onchange="handleFileSelect(this.files[0])">
                </div>

                <div style="text-align: center; margin-top: 1rem; font-size: 0.8rem; color: var(--text-dim);">
                    Maximum File Size: 25 MB
                </div>
            </div>

            <!-- STEP 2: Preview & Print Settings (Initially Hidden) -->
            <div id="step-2-settings" class="glass-panel" style="padding: 1.5rem; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                    <div>
                        <div style="font-weight: 700; font-size: 1rem;" id="doc-file-name">Document.pdf</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);" id="doc-file-info">12 pages • 2.4 MB</div>
                    </div>
                    <button class="btn btn-secondary" onclick="resetUpload()" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">Change File</button>
                </div>

                <!-- PDF / Image Interactive Visual Preview -->
                <div style="background: var(--bg-input); border-radius: var(--radius-sm); padding: 1rem; text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <span>DOCUMENT PREVIEW</span>
                        <span id="preview-page-indicator">Page 1</span>
                    </div>

                    <div style="max-height: 240px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #000; border-radius: 6px; padding: 0.5rem;">
                        <canvas id="pdf-preview-canvas" style="max-width: 100%; max-height: 220px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); display: none;"></canvas>
                        <img id="img-preview-tag" style="max-width: 100%; max-height: 220px; object-fit: contain; display: none;">
                    </div>

                    <div id="pdf-controls" style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 0.75rem;">
                        <button class="btn btn-secondary" onclick="changePreviewPage(-1)" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">◀ Prev</button>
                        <button class="btn btn-secondary" onclick="changePreviewPage(1)" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Next ▶</button>
                    </div>
                </div>

                <!-- Print Options Form -->
                <h4 style="font-size: 1rem; margin-bottom: 1rem; border-left: 3px solid var(--primary); padding-left: 0.5rem;">Print Settings</h4>

                <!-- Orientation -->
                <div class="form-group">
                    <label class="form-label">Orientation</label>
                    <div class="option-cards-grid">
                        <div class="option-card active" id="opt-orient-portrait" onclick="setOrientation('portrait')">
                            <div class="option-card-icon">📄</div>
                            <div class="option-card-title">Portrait</div>
                        </div>
                        <div class="option-card" id="opt-orient-landscape" onclick="setOrientation('landscape')">
                            <div class="option-card-icon" style="transform: rotate(90deg);">📄</div>
                            <div class="option-card-title">Landscape</div>
                        </div>
                    </div>
                </div>

                <!-- Page Selection -->
                <div class="form-group">
                    <label class="form-label">Pages to Print</label>
                    <select id="page-select-type" class="form-control" onchange="togglePageInputs(this.value)">
                        <option value="all">All Pages</option>
                        <option value="range">Page Range (From / To)</option>
                        <option value="custom">Custom Specific Pages (e.g. 1,3,5-8)</option>
                    </select>

                    <div id="page-range-box" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">From Page</span>
                            <input type="number" id="page-from" class="form-control" value="1" min="1">
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">To Page</span>
                            <input type="number" id="page-to" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <div id="page-custom-box" style="display: none; margin-top: 0.75rem;">
                        <input type="text" id="page-custom-csv" class="form-control" placeholder="Example: 1, 3, 5-10">
                    </div>
                </div>

                <!-- Copies -->
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                    <label class="form-label" style="margin-bottom: 0;">Number of Copies</label>
                    <div class="stepper-input">
                        <button class="stepper-btn" onclick="updateCopies(-1)">-</button>
                        <div class="stepper-val" id="copies-val">1</div>
                        <button class="stepper-btn" onclick="updateCopies(1)">+</button>
                    </div>
                </div>

                <!-- Paper Size & Color Mode -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label">Paper Size</label>
                        <select id="paper-size-select" class="form-control">
                            <option value="A4" selected>A4</option>
                            <option value="A3">A3</option>
                            <option value="A5">A5</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Color Mode</label>
                        <select id="color-mode-select" class="form-control">
                            <option value="color">Color</option>
                            <option value="bw" selected>Black & White</option>
                        </select>
                    </div>
                </div>

                <!-- Scaling & Margins (Advanced options) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="form-label">Scaling</label>
                        <select id="scaling-select" class="form-control">
                            <option value="fit" selected>Fit to Page</option>
                            <option value="actual">Actual Size</option>
                            <option value="fill">Fill Page</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Duplex Printing</label>
                        <select id="duplex-select" class="form-control">
                            <option value="single" selected>Single-sided</option>
                            <option value="long">Long-edge (Flip)</option>
                            <option value="short">Short-edge (Flip)</option>
                        </select>
                    </div>
                </div>

                <button class="btn btn-primary btn-block" onclick="openSummaryModal()" style="padding: 1rem; font-size: 1.1rem;">
                    👁️ Review & Print
                </button>
            </div>

            <!-- STEP 3: Job Progress Tracking View (Initially Hidden) -->
            <div id="step-3-status" class="glass-panel" style="padding: 1.75rem; display: none; text-align: center;">
                <h3 style="font-size: 1.3rem; margin-bottom: 0.25rem;" id="job-uuid-display">JOB #JOB-10023</h3>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;" id="job-file-display">Document.pdf</div>

                <!-- Progress Tracker -->
                <div class="step-tracker">
                    <div class="step-item completed" id="step-node-0">
                        <div class="step-bubble">✓</div>
                        <div class="step-label">Uploaded</div>
                    </div>
                    <div class="step-item" id="step-node-1">
                        <div class="step-bubble">1</div>
                        <div class="step-label">Queued</div>
                    </div>
                    <div class="step-item" id="step-node-2">
                        <div class="step-bubble">2</div>
                        <div class="step-label">Processing</div>
                    </div>
                    <div class="step-item" id="step-node-3">
                        <div class="step-bubble">3</div>
                        <div class="step-label">Sending</div>
                    </div>
                    <div class="step-item" id="step-node-4">
                        <div class="step-bubble">4</div>
                        <div class="step-label">Printing</div>
                    </div>
                </div>

                <div id="live-status-message" style="font-weight: 700; font-size: 1.1rem; color: var(--primary); margin: 1.5rem 0;">
                    Sending document to host printer spooler...
                </div>

                <button class="btn btn-danger" id="cancel-job-btn" onclick="cancelCurrentJob()" style="margin-top: 1rem;">
                    Cancel Print Job
                </button>
                <button class="btn btn-secondary" id="print-another-btn" onclick="resetUpload()" style="margin-top: 1rem; display: none;">
                    🖨️ Print Another Document
                </button>
            </div>
        </div>
    </main>

    <!-- Final Print Confirmation Modal -->
    <div id="summary-modal" class="modal-overlay">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem;" id="sum-modal-title">🖨️ Confirm Print Request</h3>
            
            <div style="background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">Document:</span>
                    <strong id="sum-doc-name">File.pdf</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">Orientation:</span>
                    <strong id="sum-orientation">Portrait</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">Pages:</span>
                    <strong id="sum-pages">1-5</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">Copies:</span>
                    <strong id="sum-copies">1</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">Paper / Color:</span>
                    <strong id="sum-paper-color">A4 • Black & White</strong>
                </div>

                <!-- Dynamic UPI Payment Summary Section -->
                <div id="sum-payment-section" style="display: none; border-top: 1px dashed var(--border-color); margin-top: 0.75rem; padding-top: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                        <span style="color: var(--text-muted);">Rate per Page:</span>
                        <strong id="sum-page-rate">₹2.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 1.15rem; font-weight: 800; color: var(--accent-amber);">
                        <span>Total Payable Amount:</span>
                        <strong id="sum-total-amount">₹0.00</strong>
                    </div>

                    <a id="sum-upi-btn" href="#" class="btn btn-primary btn-block" style="text-align: center; text-decoration: none; display: block; margin-bottom: 0.75rem; background: linear-gradient(135deg, #10b981, #059669);">
                        📱 Pay via Installed UPI App (GPay / PhonePe / Paytm)
                    </a>
                    
                    <div style="text-align: center; margin-bottom: 0.75rem;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.35rem;">Or scan QR code to pay via UPI:</div>
                        <img id="sum-upi-qr-img" src="" alt="UPI Payment QR Code" style="width: 150px; height: 150px; border-radius: 8px; background: #fff; padding: 4px; border: 1px solid var(--border-color); display: inline-block;">
                    </div>

                    <div style="font-size: 0.75rem; color: var(--text-dim); text-align: center;">
                        Merchant: <span id="sum-merchant-name">Print Cafe</span> • UPI ID: <code id="sum-upi-id">shop@upi</code>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-secondary" style="flex:1;" onclick="closeSummaryModal()">Back</button>
                <button class="btn btn-primary" id="confirm-print-btn" style="flex:2;" onclick="submitPrintJob()">🖨️ PRINT NOW</button>
            </div>
        </div>
    </div>

    <script>
        const hostUuid = <?= json_encode($host_id) ?>;
        const qrToken = <?= json_encode($token) ?>;
        let hostConfig = null;
        let activeFile = null;
        let uploadResult = null;
        let pdfDoc = null;
        let currentPreviewPage = 1;
        let totalDocPages = 1;
        let calculatedTotalPages = 1;
        let calculatedTotalCost = 0;
        let selectedOrientation = 'portrait';
        let copiesCount = 1;
        let activeJobUuid = null;
        let statusPollInterval = null;
        let sessionSec = 60;
        let sessionTimerInterval = null;

        window.addEventListener('DOMContentLoaded', () => {
            checkHostStatus();
            setupDropzone();
        });

        async function checkHostStatus() {
            try {
                const res = await fetch(`api/get_host.php?host_id=${hostUuid}&token=${qrToken}`);
                const data = await res.json();
                if (data.success) {
                    if (data.token_valid === false) {
                        showTokenExpiredCard(data.token_error);
                        return;
                    }
                    if (data.host.is_online) {
                        hostConfig = data.host;
                        document.getElementById('host-offline-card').style.display = 'none';
                        document.getElementById('token-expired-card').style.display = 'none';
                        document.getElementById('client-flow-card').style.display = 'block';
                        document.getElementById('display-printer-name').innerText = data.active_printer ? data.active_printer.printer_name : data.host.host_name;
                        document.getElementById('host-status-indicator').innerHTML = '<span class="badge-status badge-online"><span class="pulse-dot"></span> Host Online</span>';
                        startSessionCountdownTimer();
                    } else {
                        showOfflineCard('Printer Host is offline or last seen more than 30s ago.');
                    }
                } else {
                    showOfflineCard(data.error);
                }
            } catch(e) {
                showOfflineCard('Unable to reach server. Check network connection.');
            }
        }

        function startSessionCountdownTimer() {
            if (sessionTimerInterval) clearInterval(sessionTimerInterval);
            sessionSec = 60;
            sessionTimerInterval = setInterval(() => {
                sessionSec--;
                const timerEl = document.getElementById('session-countdown-sec');
                if (timerEl) timerEl.innerText = sessionSec;
                if (sessionSec <= 0) {
                    clearInterval(sessionTimerInterval);
                    showTokenExpiredCard('Session time limit (60s) reached. Please rescan counter QR code.');
                }
            }, 1000);
        }

        function showTokenExpiredCard(msg) {
            if (sessionTimerInterval) clearInterval(sessionTimerInterval);
            document.getElementById('host-offline-card').style.display = 'none';
            document.getElementById('client-flow-card').style.display = 'none';
            document.getElementById('token-expired-card').style.display = 'block';
            document.getElementById('expired-reason-text').innerText = msg || 'This QR Code link has expired (60s limit) or has already been used.';
            document.getElementById('host-status-indicator').innerHTML = '<span class="badge-status badge-offline">QR Expired</span>';
        }

        function showOfflineCard(msg) {
            document.getElementById('host-offline-card').style.display = 'block';
            document.getElementById('token-expired-card').style.display = 'none';
            document.getElementById('client-flow-card').style.display = 'none';
            document.getElementById('offline-reason-text').innerText = msg;
            document.getElementById('host-status-indicator').innerHTML = '<span class="badge-status badge-offline">Host Offline</span>';
        }

        function setupDropzone() {
            const dz = document.getElementById('dropzone');
            ['dragenter', 'dragover'].forEach(name => dz.addEventListener(name, (e) => { e.preventDefault(); dz.classList.add('dragover'); }));
            ['dragleave', 'drop'].forEach(name => dz.addEventListener(name, (e) => { e.preventDefault(); dz.classList.remove('dragover'); }));
            dz.addEventListener('drop', (e) => {
                if (e.dataTransfer.files.length) handleFileSelect(e.dataTransfer.files[0]);
            });
        }

        async function handleFileSelect(file) {
            if (!file) return;
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
                alert('Unsupported file format. Please upload a PDF, JPG, or PNG file.');
                return;
            }

            activeFile = file;
            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await fetch('api/upload_file.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    uploadResult = data;
                    renderStep2(file, data);
                } else {
                    alert('Upload failed: ' + data.error);
                }
            } catch(e) {
                alert('Connection error uploading file.');
            }
        }

        function renderStep2(file, data) {
            document.getElementById('step-1-upload').style.display = 'none';
            document.getElementById('step-2-settings').style.display = 'block';
            document.getElementById('doc-file-name').innerText = file.name;

            const ext = data.file_type;
            if (ext === 'pdf') {
                renderPdfPreview(URL.createObjectURL(file));
            } else {
                renderImagePreview(URL.createObjectURL(file));
            }
        }

        async function renderPdfPreview(url) {
            document.getElementById('pdf-preview-canvas').style.display = 'block';
            document.getElementById('img-preview-tag').style.display = 'none';
            document.getElementById('pdf-controls').style.display = 'flex';

            try {
                pdfDoc = await pdfjsLib.getDocument(url).promise;
                totalDocPages = pdfDoc.numPages;
                document.getElementById('doc-file-info').innerText = `${totalDocPages} pages • ${uploadResult.file_size_formatted}`;
                document.getElementById('page-to').value = totalDocPages;
                renderPdfPage(1);
            } catch(e) {
                console.error('PDF preview error:', e);
            }
        }

        async function renderPdfPage(pageNum) {
            if (!pdfDoc) return;
            currentPreviewPage = pageNum;
            document.getElementById('preview-page-indicator').innerText = `Page ${pageNum} of ${totalDocPages}`;

            const page = await pdfDoc.getPage(pageNum);
            const canvas = document.getElementById('pdf-preview-canvas');
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: 0.8 });

            canvas.width = viewport.width;
            canvas.height = viewport.height;
            await page.render({ canvasContext: ctx, viewport }).promise;
        }

        function changePreviewPage(delta) {
            let target = currentPreviewPage + delta;
            if (target >= 1 && target <= totalDocPages) {
                renderPdfPage(target);
            }
        }

        function renderImagePreview(url) {
            document.getElementById('pdf-preview-canvas').style.display = 'none';
            const img = document.getElementById('img-preview-tag');
            img.src = url;
            img.style.display = 'block';
            document.getElementById('pdf-controls').style.display = 'none';
            document.getElementById('doc-file-info').innerText = `Image • ${uploadResult.file_size_formatted}`;
            totalDocPages = 1;
        }

        function setOrientation(orient) {
            selectedOrientation = orient;
            document.getElementById('opt-orient-portrait').classList.toggle('active', orient === 'portrait');
            document.getElementById('opt-orient-landscape').classList.toggle('active', orient === 'landscape');
        }

        function togglePageInputs(val) {
            document.getElementById('page-range-box').style.display = val === 'range' ? 'grid' : 'none';
            document.getElementById('page-custom-box').style.display = val === 'custom' ? 'block' : 'none';
        }

        function updateCopies(delta) {
            copiesCount = Math.max(1, copiesCount + delta);
            document.getElementById('copies-val').innerText = copiesCount;
        }

        function openSummaryModal() {
            const pageType = document.getElementById('page-select-type').value;
            let pageText = 'All Pages';
            let pageCount = totalDocPages;

            if (pageType === 'range') {
                const pFrom = parseInt(document.getElementById('page-from').value) || 1;
                const pTo = parseInt(document.getElementById('page-to').value) || 1;
                pageCount = Math.max(1, pTo - pFrom + 1);
                pageText = `Pages ${pFrom}–${pTo}`;
            } else if (pageType === 'custom') {
                const csv = document.getElementById('page-custom-csv').value;
                pageText = `Pages (${csv})`;
                const parts = csv.split(',').filter(x => x.trim().length > 0);
                pageCount = parts.length > 0 ? parts.length : totalDocPages;
            }

            calculatedTotalPages = pageCount * copiesCount;

            document.getElementById('sum-doc-name').innerText = activeFile.name;
            document.getElementById('sum-orientation').innerText = selectedOrientation.toUpperCase();
            document.getElementById('sum-pages').innerText = pageText;
            document.getElementById('sum-copies').innerText = copiesCount;
            document.getElementById('sum-paper-color').innerText = `${document.getElementById('paper-size-select').value} • ${document.getElementById('color-mode-select').value === 'color' ? 'Color' : 'Black & White'}`;

            // Handle UPI Payment options
            const paymentSec = document.getElementById('sum-payment-section');
            const confirmBtn = document.getElementById('confirm-print-btn');

            if (hostConfig && parseInt(hostConfig.payment_enabled) === 1) {
                const perPageRate = parseFloat(hostConfig.per_page_cost || 2.0);
                calculatedTotalCost = (calculatedTotalPages * perPageRate).toFixed(2);

                document.getElementById('sum-modal-title').innerText = '💳 UPI Payment & Confirm Print';
                document.getElementById('sum-page-rate').innerText = '₹' + perPageRate.toFixed(2) + ' / page';
                document.getElementById('sum-total-amount').innerText = '₹' + calculatedTotalCost;
                document.getElementById('sum-merchant-name').innerText = hostConfig.merchant_name || 'Print Cafe Host';
                document.getElementById('sum-upi-id').innerText = hostConfig.upi_id || 'Not Configured';

                const upiId = hostConfig.upi_id || 'shop@upi';
                const merchantName = encodeURIComponent(hostConfig.merchant_name || 'Print Cafe');
                const upiIntentUrl = `upi://pay?pa=${upiId}&pn=${merchantName}&am=${calculatedTotalCost}&cu=INR&tn=Print_${encodeURIComponent(activeFile.name)}`;

                const upiBtn = document.getElementById('sum-upi-btn');
                upiBtn.href = upiIntentUrl;
                upiBtn.innerText = `📱 Pay ₹${calculatedTotalCost} via Installed UPI App`;
                document.getElementById('sum-upi-qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(upiIntentUrl);

                paymentSec.style.display = 'block';
                confirmBtn.innerText = `✅ PAYMENT DONE - PRINT NOW (₹${calculatedTotalCost})`;
                confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            } else {
                paymentSec.style.display = 'none';
                document.getElementById('sum-modal-title').innerText = '🖨️ Confirm Print Request';
                confirmBtn.innerText = '🖨️ PRINT NOW';
                confirmBtn.style.background = 'var(--primary)';
                calculatedTotalCost = 0;
            }

            document.getElementById('summary-modal').classList.add('active');
        }

        function closeSummaryModal() { document.getElementById('summary-modal').classList.remove('active'); }

        async function submitPrintJob() {
            closeSummaryModal();

            const jobData = {
                host_id: hostUuid,
                token: qrToken,
                file_path: uploadResult.file_path,
                file_name: uploadResult.file_name,
                file_type: uploadResult.file_type,
                file_size: uploadResult.file_size,
                page_selection_type: document.getElementById('page-select-type').value,
                page_from: document.getElementById('page-from').value,
                page_to: document.getElementById('page-to').value,
                custom_pages: document.getElementById('page-custom-csv').value,
                total_pages: totalDocPages,
                copies: copiesCount,
                orientation: selectedOrientation,
                scaling: document.getElementById('scaling-select').value,
                paper_size: document.getElementById('paper-size-select').value,
                color_mode: document.getElementById('color-mode-select').value,
                duplex_mode: document.getElementById('duplex-select').value,
                user_device: /Mobile|Android|iPhone/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
                payment_status: (hostConfig && parseInt(hostConfig.payment_enabled) === 1) ? 'PAID' : 'FREE',
                amount_paid: (hostConfig && parseInt(hostConfig.payment_enabled) === 1) ? calculatedTotalCost : 0,
                payment_txn_id: 'UPI-' + Date.now()
            };

            try {
                const res = await fetch('api/create_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(jobData)
                });
                const data = await res.json();
                if (data.success) {
                    activeJobUuid = data.job_uuid;
                    startJobTracking(data);
                } else {
                    alert('Failed to submit job: ' + data.error);
                }
            } catch(e) {
                alert('Connection error submitting print job.');
            }
        }

        function startJobTracking(data) {
            document.getElementById('step-2-settings').style.display = 'none';
            document.getElementById('step-3-status').style.display = 'block';
            document.getElementById('job-uuid-display').innerText = `JOB #${data.job_uuid}`;
            document.getElementById('job-file-display').innerText = uploadResult.file_name;

            pollJobStatus();
            statusPollInterval = setInterval(pollJobStatus, 2000);
        }

        async function pollJobStatus() {
            if (!activeJobUuid) return;
            try {
                const res = await fetch(`api/job_status.php?job_id=${activeJobUuid}`);
                const data = await res.json();
                if (data.success) {
                    const job = data.job;
                    updateProgressUI(job);
                    if (['COMPLETED', 'FAILED', 'CANCELLED'].includes(job.status)) {
                        clearInterval(statusPollInterval);
                    }
                }
            } catch(e) {}
        }

        function updateProgressUI(job) {
            const stepIndex = job.step_index;
            for (let i = 0; i <= 4; i++) {
                const node = document.getElementById(`step-node-${i}`);
                if (i < stepIndex) {
                    node.className = 'step-item completed';
                } else if (i === stepIndex) {
                    node.className = 'step-item active';
                } else {
                    node.className = 'step-item';
                }
            }

            const msgEl = document.getElementById('live-status-message');
            if (job.status === 'PENDING_APPROVAL') {
                msgEl.innerHTML = '⏳ Waiting for Host Admin approval...';
            } else if (job.status === 'QUEUED') {
                msgEl.innerHTML = '📥 Job queued. Host agent preparing document...';
            } else if (job.status === 'PROCESSING') {
                msgEl.innerHTML = '⚙️ Processing page range & orientation...';
            } else if (job.status === 'SENDING_TO_PRINTER') {
                msgEl.innerHTML = '🚀 Sending to Windows Printer Spooler...';
            } else if (job.status === 'PRINTING') {
                msgEl.innerHTML = '🖨️ Printing document...';
            } else if (job.status === 'COMPLETED') {
                msgEl.innerHTML = '✅ Printing Completed Successfully!';
                msgEl.style.color = 'var(--accent-emerald)';
                document.getElementById('cancel-job-btn').style.display = 'none';
                document.getElementById('print-another-btn').style.display = 'inline-block';
            } else if (job.status === 'FAILED' || job.status === 'CANCELLED') {
                msgEl.innerHTML = `❌ Job ${job.status}: ${job.error_message || 'User cancelled'}`;
                msgEl.style.color = 'var(--accent-rose)';
                document.getElementById('cancel-job-btn').style.display = 'none';
                document.getElementById('print-another-btn').style.display = 'inline-block';
            }
        }

        async function cancelCurrentJob() {
            if (!activeJobUuid || !confirm('Cancel this print job?')) return;
            await fetch('api/cancel_job.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: activeJobUuid })
            });
            pollJobStatus();
        }

        function resetUpload() {
            location.reload();
        }
    </script>
</body>
</html>
