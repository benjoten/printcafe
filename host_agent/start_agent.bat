@echo off
title Print Cafe Host Agent
cd /d "%~dp0"
echo ========================================================
echo               PRINT CAFE HOST AGENT
echo ========================================================
echo.
echo Starting Print Cafe Host Agent GUI Controller...
python gui_launcher.py
pause
