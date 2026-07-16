@echo off
title Vyomika Atelier LLP — Local Preview
cd /d "%~dp0"

echo.
echo  ============================================
echo   Vyomika Atelier LLP — LOCAL PREVIEW
echo  ============================================
echo.
echo   URL:  http://127.0.0.1:8765/
echo.
echo   Double-click this file anytime — no need to ask AI.
echo   Bookmark the URL in your browser for one-click access.
echo.

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8765.*LISTENING"') do (
  echo   Server already running — opening browser...
  start "" "http://127.0.0.1:8765/"
  timeout /t 3 >nul
  exit /b 0
)

echo   Starting preview server...
echo   Leave the "Vyomika Atelier LLP Preview" window open while you work.
echo.

start "Vyomika Atelier LLP Preview" /D "%~dp0public" cmd /k node serve-preview.js
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8765/"

echo   Done. Close the preview server window to stop.
timeout /t 4 >nul
