#!/usr/bin/env python3
"""
Print Cafe - Host Agent GUI Controller
Simple Tkinter desktop utility for starting, stopping, and monitoring the Host Agent on Windows.
"""

import sys
import os
import threading
import time
import tkinter as tk
from tkinter import ttk, messagebox

# Import HostPrintAgent from agent.py
sys.path.insert(0, os.path.dirname(__file__))
from agent import HostPrintAgent, DEFAULT_SERVER_URL

class AgentGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("Print Cafe - Host Agent Controller")
        self.root.geometry("460x390")
        self.root.resizable(False, False)

        self.agent = None
        self.agent_thread = None
        self.is_running = False

        self.setup_ui()

    def setup_ui(self):
        # Header Frame
        header = ttk.Frame(self.root, padding=15)
        header.pack(fill="x")
        
        lbl_title = ttk.Label(header, text="☕ Print Cafe Agent", font=("Segoe UI", 16, "bold"))
        lbl_title.pack(anchor="w")
        lbl_sub = ttk.Label(header, text="Wireless Print Spooler Service for Windows", font=("Segoe UI", 9))
        lbl_sub.pack(anchor="w")

        ttk.Separator(self.root, orient="horizontal").pack(fill="x", padx=15)

        # Settings Form Frame
        form = ttk.Frame(self.root, padding=15)
        form.pack(fill="x")

        ttk.Label(form, text="Server Web URL:").grid(row=0, column=0, sticky="w", pady=5)
        self.ent_url = ttk.Entry(form, width=35)
        self.ent_url.insert(0, DEFAULT_SERVER_URL)
        self.ent_url.grid(row=0, column=1, sticky="w", pady=5)

        ttk.Label(form, text="Host UUID:").grid(row=1, column=0, sticky="w", pady=5)
        self.ent_uuid = ttk.Entry(form, width=35)
        self.ent_uuid.insert(0, "")
        self.ent_uuid.grid(row=1, column=1, sticky="w", pady=5)

        # Status Frame
        status_frame = ttk.LabelFrame(self.root, text=" Service Status ", padding=10)
        status_frame.pack(fill="x", padx=15, pady=5)

        self.lbl_status = ttk.Label(status_frame, text="● Agent Stopped", font=("Segoe UI", 11, "bold"), foreground="red")
        self.lbl_status.pack(anchor="w")

        self.lbl_log = ttk.Label(status_frame, text="Ready to start local print agent...", font=("Segoe UI", 9), foreground="#555", wraplength=410)
        self.lbl_log.pack(anchor="w", pady=2)

        # Action Buttons
        btn_frame = ttk.Frame(self.root, padding=15)
        btn_frame.pack(fill="x")

        self.btn_toggle = ttk.Button(btn_frame, text="🚀 START AGENT", command=self.toggle_agent, width=20)
        self.btn_toggle.pack(side="left", padx=5)

        btn_dashboard = ttk.Button(btn_frame, text="🖥️ Open Dashboard", command=self.open_dashboard, width=20)
        btn_dashboard.pack(side="right", padx=5)

    def toggle_agent(self):
        if not self.is_running:
            import importlib
            import agent
            importlib.reload(agent)
            from agent import HostPrintAgent

            url = self.ent_url.get().strip()
            uuid_val = self.ent_uuid.get().strip()
            self.agent = HostPrintAgent(server_url=url, host_uuid=uuid_val if uuid_val else None)
            self.is_running = True
            
            self.agent_thread = threading.Thread(target=self.run_agent_loop, daemon=True)
            self.agent_thread.start()

            self.lbl_status.config(text="● Agent Running (ONLINE)", foreground="green")
            
            if "infinityfree" in url.lower() or "gt.tc" in url.lower():
                self.lbl_log.config(text="Note: InfinityFree blocks background Python scripts via /aes.js security check. For 100% full printing, use Local URL: http://localhost/PrintCafe app", foreground="#d97706")
            else:
                self.lbl_log.config(text="Polling server & listening for print jobs...", foreground="#555")

            self.btn_toggle.config(text="🛑 STOP AGENT")
        else:
            self.is_running = False
            if self.agent:
                self.agent.running = False
            self.lbl_status.config(text="● Agent Stopped", foreground="red")
            self.lbl_log.config(text="Agent stopped by user.", foreground="#555")
            self.btn_toggle.config(text="🚀 START AGENT")

    def run_agent_loop(self):
        self.agent.start()

    def open_dashboard(self):
        import webbrowser
        webbrowser.open(f"{self.ent_url.get().rstrip('/')}/host/dashboard.php")

if __name__ == "__main__":
    root = tk.Tk()
    app = AgentGUI(root)
    root.mainloop()
