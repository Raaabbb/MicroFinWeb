@echo off
REM Start Autopush Script
REM This batch file starts the autopush PowerShell script in a new window

if exist "%~dp0.autopush.disabled" (
    echo Auto-push is currently disabled.
    echo Remove "%~dp0.autopush.disabled" to re-enable.
    pause
    exit /b 0
)

echo Starting MicroFin Autopush Script...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0autopush.ps1"
pause
