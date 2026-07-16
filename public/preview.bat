@echo off
cd /d "%~dp0"
echo.
echo   Vyomika Atelier LLP static preview
echo   Open: http://127.0.0.1:8765/
echo   Press Ctrl+C to stop
echo.
node serve-preview.js
