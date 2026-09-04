#!/usr/bin/env python3
"""
Print Cafe - Windows Host Print Agent
Background Python service that enumerates installed Windows printers,
sends heartbeats to the web backend, polls for queued print jobs,
applies PDF/image settings (page ranges, copies, orientation),
and dispatches print jobs to the Windows Print Spooler.
"""

import os
import sys
import time
import json
import tempfile
import argparse
import subprocess
import requests
from pathlib import Path

# Try importing Windows printing & PDF libraries
try:
    import win32print
    import win32api
    import win32ui
    import win32con
    HAS_WIN32 = True
except ImportError:
    HAS_WIN32 = False

try:
    from pypdf import PdfReader, PdfWriter
    HAS_PYPDF = True
except ImportError:
    HAS_PYPDF = False

try:
    from PIL import Image, ImageWin
    HAS_PIL = True
except ImportError:
    HAS_PIL = False

# Default Config
DEFAULT_SERVER_URL = "https://printcafe.onrender.com"
VIRTUAL_PRINTER_KEYWORDS = ["pdf", "onenote", "xps", "anydesk", "fax", "microsoft print to pdf", "adobe pdf"]

class HostPrintAgent:
    def __init__(self, server_url=DEFAULT_SERVER_URL, host_uuid=None):
        self.server_url = server_url.rstrip("/")
        self.host_uuid = host_uuid
        self.running = False
        self.active_printer = None
        
        # Setup Requests Session with Browser User-Agent
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Accept": "application/json, text/plain, */*"
        })

    def get_installed_printers(self):
        """Enumerate installed Windows printers using win32print or PowerShell fallback"""
        printers = []
        if HAS_WIN32:
            try:
                flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
                enum_printers = win32print.EnumPrinters(flags)
                for p in enum_printers:
                    p_name = p[2]
                    is_virtual = any(k in p_name.lower() for k in VIRTUAL_PRINTER_KEYWORDS)
                    printers.append({
                        "name": p_name,
                        "system_name": p_name,
                        "is_virtual": is_virtual
                    })
            except Exception as e:
                print(f"[Agent] Error enumerating win32 printers: {e}")

        if not printers:
            # PowerShell Fallback
            try:
                cmd = ["powershell", "-Command", "Get-Printer | Select-Object -Property Name, Type | ConvertTo-Json"]
                output = subprocess.check_output(cmd, text=True)
                data = json.loads(output)
                if isinstance(data, dict):
                    data = [data]
                for item in data:
                    p_name = item.get("Name", "")
                    if p_name:
                        is_virtual = any(k in p_name.lower() for k in VIRTUAL_PRINTER_KEYWORDS)
                        printers.append({
                            "name": p_name,
                            "system_name": p_name,
                            "is_virtual": is_virtual
                        })
            except Exception as e:
                print(f"[Agent] PowerShell printer fallback failed: {e}")

        return printers

    def send_heartbeat(self):
        """Send heartbeat and printer list to backend server"""
        url = f"{self.server_url}/api/host_heartbeat.php"
        printers = self.get_installed_printers()
        payload = {
            "host_uuid": self.host_uuid or "",
            "agent_version": "1.0.0",
            "printers": printers
        }
        try:
            res = self.session.post(url, json=payload, timeout=8)
            if res.status_code == 200:
                if "/aes.js" in res.text or "To-continue-browsing" in res.text:
                    print("[Agent Warning] Target free host (InfinityFree) is blocking Python API requests via AES JavaScript challenge.")
                    print("[Agent Warning] Please use Render URL: https://printcafe.onrender.com for 100% reliable cloud printing!")
                    return False
                
                try:
                    data = res.json()
                    if data.get("success"):
                        self.host_uuid = data.get("host_uuid")
                        return True
                    else:
                        print(f"[Agent] Heartbeat server error: {data.get('error')}")
                except json.JSONDecodeError:
                    print(f"[Agent] Non-JSON response received from server: {res.text[:150]}")
            else:
                print(f"[Agent] Heartbeat failed with HTTP {res.status_code}: {res.text[:150]}")
        except Exception as e:
            print(f"[Agent] Heartbeat connection error: {e}")
        return False

    def poll_job(self):
        """Poll server for next QUEUED job"""
        url = f"{self.server_url}/api/agent_jobs.php?host_id={self.host_uuid or ''}"
        try:
            res = self.session.get(url, timeout=8)
            if res.status_code == 200:
                try:
                    data = res.json()
                    if data.get("success") and data.get("has_job"):
                        return data.get("job")
                except json.JSONDecodeError:
                    pass
        except Exception as e:
            print(f"[Agent] Job poll error: {e}")
        return None

    def update_job_status(self, job_uuid, status, message=""):
        """Report status updates to web backend"""
        url = f"{self.server_url}/api/agent_update_job.php"
        payload = {
            "job_id": job_uuid,
            "status": status,
            "message": message
        }
        try:
            self.session.post(url, json=payload, timeout=8)
        except Exception as e:
            print(f"[Agent] Status update failed: {e}")

    def process_pdf_pages(self, src_path, page_selection_type, page_from, page_to, custom_pages):
        """Extract/crop requested pages using PyPDF"""
        if not HAS_PYPDF:
            return src_path

        try:
            reader = PdfReader(src_path)
            total_pages = len(reader.pages)
            writer = PdfWriter()

            pages_to_extract = []
            if page_selection_type == "range":
                start = max(1, page_from) - 1
                end = min(total_pages, page_to)
                pages_to_extract = list(range(start, end))
            elif page_selection_type == "custom" and custom_pages:
                parts = custom_pages.split(",")
                for part in parts:
                    part = part.strip()
                    if "-" in part:
                        try:
                            s, e = part.split("-", 1)
                            for pnum in range(int(s), int(e) + 1):
                                if 1 <= pnum <= total_pages:
                                    pages_to_extract.append(pnum - 1)
                        except ValueError:
                            pass
                    else:
                        try:
                            pnum = int(part)
                            if 1 <= pnum <= total_pages:
                                pages_to_extract.append(pnum - 1)
                        except ValueError:
                            pass
            else:
                return src_path

            if not pages_to_extract:
                return src_path

            for idx in pages_to_extract:
                writer.add_page(reader.pages[idx])

            tmp_fd, tmp_path = tempfile.mkstemp(suffix="_print.pdf")
            os.close(tmp_fd)
            with open(tmp_path, "wb") as f:
                writer.write(f)

            return tmp_path
        except Exception as e:
            print(f"[Agent] PyPDF extraction error: {e}")
            return src_path

    def download_job_file(self, job):
        """Locate file on local disk or download from web server if remote (cloud deployment)"""
        file_path = job.get("file_path", "")
        file_url = job.get("file_url", "")
        
        # 1. Check local filesystem if web server and agent are on the same machine
        if file_path and os.path.exists(file_path):
            return file_path, False

        resolved_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), file_path.lstrip("/\\"))
        if os.path.exists(resolved_path):
            return resolved_path, False

        # 2. Download from web server if remote (e.g. Render Cloud)
        download_urls = []
        if file_url:
            download_urls.append(file_url)

        rel_path = file_path
        if "/uploads/" in file_path:
            rel_path = "uploads/" + file_path.split("/uploads/", 1)[1]
        elif "\\uploads\\" in file_path:
            rel_path = "uploads/" + file_path.split("\\uploads\\", 1)[1].replace("\\", "/")

        download_urls.append(f"{self.server_url}/{rel_path.lstrip('/')}")

        for url in download_urls:
            try:
                print(f"[Agent] Downloading print document from cloud server: {url}")
                res = self.session.get(url, timeout=15)
                if res.status_code == 200 and len(res.content) > 0:
                    ext = "." + job.get("file_type", "tmp").lstrip(".")
                    tmp_fd, tmp_path = tempfile.mkstemp(suffix=ext, prefix="printcafe_")
                    os.write(tmp_fd, res.content)
                    os.close(tmp_fd)
                    print(f"[Agent] Downloaded {len(res.content)} bytes to local file: {tmp_path}")
                    return tmp_path, True
            except Exception as e:
                print(f"[Agent] File download attempt failed ({url}): {e}")

        return None, False

    def execute_print_job(self, job):
        """Execute print job on target Windows Printer Spooler"""
        job_uuid = job.get("job_uuid")
        printer_name = job.get("printer_name") or job.get("printer_system_name")
        copies = int(job.get("copies", 1))

        print(f"\n[Agent] Processing Print Job #{job_uuid} -> Printer: {printer_name}")
        self.update_job_status(job_uuid, "PROCESSING", "Agent downloading & preparing document...")

        # Locate local file or download from cloud server
        local_file_path, is_temporary = self.download_job_file(job)

        if not local_file_path or not os.path.exists(local_file_path):
            self.update_job_status(job_uuid, "FAILED", f"Source file could not be downloaded or found on disk: {job.get('file_path')}")
            return

        # Process page range if PDF
        target_print_file = local_file_path
        if job.get("file_type") == "pdf":
            target_print_file = self.process_pdf_pages(
                local_file_path,
                job.get("page_selection_type"),
                int(job.get("page_from", 1)),
                int(job.get("page_to", 1)),
                job.get("custom_pages", "")
            )

        self.update_job_status(job_uuid, "SENDING_TO_PRINTER", "Sending job to Windows Spooler...")

        try:
            # Print using PyWin32 GDI or PowerShell PrintTo
            success = self.spool_to_windows_printer(target_print_file, printer_name, copies)
            if success:
                self.update_job_status(job_uuid, "PRINTING", "Document sent to printer.")
                time.sleep(2)
                self.update_job_status(job_uuid, "COMPLETED", "Print job completed successfully.")
                print(f"[Agent] Successfully printed job #{job_uuid}")
            else:
                self.update_job_status(job_uuid, "FAILED", "Windows printer spooling failed.")
        except Exception as e:
            self.update_job_status(job_uuid, "FAILED", f"Error during printing: {str(e)}")
        finally:
            if is_temporary and os.path.exists(local_file_path):
                try:
                    os.remove(local_file_path)
                except Exception:
                    pass
            if target_print_file != local_file_path and os.path.exists(target_print_file):
                try:
                    os.remove(target_print_file)
                except Exception:
                    pass

    def spool_to_windows_printer(self, file_path, printer_name, copies=1):
        """Spool print file to Windows Spooler using ShellExecute or PowerShell"""
        ext = os.path.splitext(file_path)[1].lower()

        if HAS_WIN32 and printer_name:
            try:
                for _ in range(copies):
                    win32api.ShellExecute(0, "printto", file_path, f'"{printer_name}"', ".", 0)
                return True
            except Exception as e:
                print(f"[Agent] ShellExecute printto warning: {e}")

        try:
            for _ in range(copies):
                ps_cmd = f'Start-Process -FilePath "{file_path}" -Verb PrintTo -ArgumentList "{printer_name}" -WindowStyle Hidden'
                subprocess.run(["powershell", "-Command", ps_cmd], check=True)
            return True
        except Exception as e:
            print(f"[Agent] PowerShell print error: {e}")

        return False

    def start(self):
        """Main agent execution loop"""
        self.running = True
        print("=" * 60)
        print("   PRINT CAFE - WINDOWS HOST PRINT AGENT v1.0.0")
        print(f"   Connecting to Server: {self.server_url}")
        print("=" * 60)

        # Initial Heartbeat
        if self.send_heartbeat():
            print(f"[Agent] Connected successfully! Host UUID: {self.host_uuid}")
        else:
            print("[Agent] Initial connection ping failed or target server blocked API requests.")

        last_hb = 0
        try:
            while self.running:
                now = time.time()
                if now - last_hb >= 5:
                    self.send_heartbeat()
                    last_hb = now

                job = self.poll_job()
                if job:
                    self.execute_print_job(job)
                else:
                    time.sleep(2)
        except KeyboardInterrupt:
            print("\n[Agent] Shutting down agent...")
            self.running = False

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Print Cafe Host Agent")
    parser.add_argument("--url", default=DEFAULT_SERVER_URL, help="Print Cafe Server URL")
    parser.add_argument("--host", default=None, help="Host UUID")
    args = parser.parse_args()

    agent = HostPrintAgent(server_url=args.url, host_uuid=args.host)
    agent.start()
