@echo off
cd /d "%~dp0"

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8765.*LISTENING"') do (
  echo Preview already running on port 8765
  start "" "http://127.0.0.1:8765/"
  exit /b 0
)

echo.
echo   Starting Vyomika Atelier LLP static preview...
echo   Open: http://127.0.0.1:8765/
echo   Press Ctrl+C in the preview window to stop.
echo.

start "Vyomika Atelier LLP Preview" /D "%~dp0public" cmd /k node serve-preview.js
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8765/"
