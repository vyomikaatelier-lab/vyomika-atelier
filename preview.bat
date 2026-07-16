@echo off
cd /d "%~dp0"

if exist "vendor\autoload.php" (
  node scripts\local-preview.js
  exit /b %ERRORLEVEL%
)

echo.
echo   Laravel not ready yet ^(run: composer install^)
echo   Starting static preview instead — shop, cart, services all work.
echo.
call start-preview.bat
