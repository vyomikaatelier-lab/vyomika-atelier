@echo off
setlocal EnableDelayedExpansion

rem ===========================================================================
rem  VYOMIKA ATELIER - Project Audit and Test Runner
rem ---------------------------------------------------------------------------
rem  Non-destructive diagnostics for the Laravel application. Writes a
rem  timestamped report to diagnostics\ and exits non-zero on critical failure.
rem
rem  Usage:
rem    project_audit_and_test.cmd            full audit (no frontend build)
rem    project_audit_and_test.cmd /build     also runs "npm run build"
rem    project_audit_and_test.cmd /quick     skips the PHPUnit suite
rem
rem  SAFETY: never runs migrate, migrate:fresh, migrate:refresh, db:wipe,
rem  db:seed or any other command that mutates database contents.
rem ===========================================================================

set "RUN_BUILD=0"
set "SKIP_TESTS=0"
for %%A in (%*) do (
    if /I "%%~A"=="/build" set "RUN_BUILD=1"
    if /I "%%~A"=="/quick" set "SKIP_TESTS=1"
)

pushd "%~dp0"

set "CRITICAL=0"
set "WARNINGS=0"

rem --- Phase 0: must be a Laravel project root ------------------------------
if not exist "artisan" (
    echo [FATAL] artisan not found in "%CD%".
    echo         Run this script from the Laravel project root.
    popd
    exit /b 2
)
if not exist "composer.json" (
    echo [FATAL] composer.json not found in "%CD%".
    popd
    exit /b 2
)

if not exist "diagnostics" mkdir "diagnostics"

set "STAMP="
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "STAMP=%%i"
if not defined STAMP set "STAMP=manual"
set "REPORT=%CD%\diagnostics\audit-%STAMP%.txt"

echo =========================================================== > "%REPORT%"
echo  VYOMIKA ATELIER - PROJECT AUDIT >> "%REPORT%"
echo  Generated: %DATE% %TIME% >> "%REPORT%"
echo  Root     : %CD% >> "%REPORT%"
echo =========================================================== >> "%REPORT%"

echo.
echo  VYOMIKA ATELIER - project audit
echo  Report: %REPORT%
echo.

rem ===========================================================================
call :section "1. TOOLCHAIN"
rem ===========================================================================

call :run_c "PHP runtime" "php -v"
call :run_w "Node" "node -v"
call :run_w "NPM" "npm -v"

call :probe "Composer"
set "COMPOSER_CMD="
composer --version >> "%REPORT%" 2>&1 && set "COMPOSER_CMD=composer"
if not defined COMPOSER_CMD (
    if exist "composer.phar" (
        php composer.phar --version >> "%REPORT%" 2>&1 && set "COMPOSER_CMD=php composer.phar"
    )
)
if not defined COMPOSER_CMD (
    echo [INFO] composer not on PATH - dependency checks limited to vendor/ presence >> "%REPORT%"
)

call :probe "PHP ini limits"
php diagnostics\audit_checks.php ini >> "%REPORT%" 2>&1

call :probe "PHP extensions"
php diagnostics\audit_checks.php ext >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "Required PHP extensions missing"

rem ===========================================================================
call :section "2. ENVIRONMENT"
rem ===========================================================================

if not exist ".env" (
    echo [FAIL] .env not found >> "%REPORT%"
    call :flag_critical ".env file is missing"
) else (
    echo [OK] .env present >> "%REPORT%"
)

call :probe "Environment summary - secrets redacted"
php diagnostics\audit_checks.php env >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "Environment configuration problem"

rem ===========================================================================
call :section "3. CACHE CLEAR - non destructive"
rem ===========================================================================

call :run_w "optimize:clear" "php artisan optimize:clear"

rem ===========================================================================
call :section "4. APPLICATION"
rem ===========================================================================

call :run_c "Laravel version" "php artisan --version"
call :run_w "About" "php artisan about --only=environment,drivers"

rem ===========================================================================
call :section "5. DATABASE - read only"
rem ===========================================================================

call :probe "Connection and schema overview"
php artisan db:show >> "%REPORT%" 2>&1
if errorlevel 1 (
    call :flag_critical "Database connection failed"
) else (
    echo [OK] database reachable >> "%REPORT%"
)

call :probe "Migration status"
php artisan migrate:status >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_warn "migrate:status returned an error"

call :probe "Pending migrations"
php artisan migrate:status --pending >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_warn "Pending migrations exist - review then run 'php artisan migrate' manually"

rem ===========================================================================
call :section "6. ROUTES"
rem ===========================================================================

set "ROUTEJSON=%TEMP%\vyomika-routes.json"
call :probe "Route inventory"
php artisan route:list --json > "%ROUTEJSON%" 2>> "%REPORT%"
if errorlevel 1 (
    call :flag_critical "route:list failed - routes file may be broken"
) else (
    php diagnostics\audit_checks.php routes >> "%REPORT%" 2>&1
    if errorlevel 1 call :flag_critical "Admin route problems detected"
)

rem ===========================================================================
call :section "7. STATIC INTEGRITY CHECKS"
rem ===========================================================================

call :probe "PHP lint - app routes config database tests"
php diagnostics\audit_checks.php lint >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "PHP syntax errors detected"

call :probe "Blade template compile check"
php diagnostics\audit_checks.php blade >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "Blade templates fail to compile"

call :probe "Admin POST forms without validation error output"
php diagnostics\audit_checks.php forms >> "%REPORT%" 2>&1

call :probe "Checkbox booleans defaulting to true"
php diagnostics\audit_checks.php booleans >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "Unchecked checkboxes would be saved as true"

call :probe "Admin form fields never read by a controller"
php diagnostics\audit_checks.php fields >> "%REPORT%" 2>&1

call :probe "Mass assignment - controller writes vs model fillable"
php diagnostics\audit_checks.php fillable >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "Admin writes columns missing from model fillable"

call :probe "Admin controllers vs feature test coverage"
php diagnostics\audit_checks.php coverage >> "%REPORT%" 2>&1

call :probe "Storage symlink"
if exist "public\storage" (
    echo [OK] public/storage link exists >> "%REPORT%"
) else (
    echo [WARN] public/storage missing - run: php artisan storage:link >> "%REPORT%"
    call :flag_warn "public/storage symlink missing"
)

rem ===========================================================================
call :section "8. COMPOSER"
rem ===========================================================================

call :probe "composer validate"
if defined COMPOSER_CMD (
    %COMPOSER_CMD% validate --no-check-publish --no-check-all >> "%REPORT%" 2>&1
    if errorlevel 1 call :flag_warn "composer validate reported problems"
) else (
    echo [SKIP] composer executable not found >> "%REPORT%"
)

call :probe "vendor installed"
if exist "vendor\autoload.php" (
    echo [OK] vendor/autoload.php present >> "%REPORT%"
) else (
    echo [FAIL] vendor missing - run composer install >> "%REPORT%"
    call :flag_critical "vendor/ not installed"
)

rem ===========================================================================
call :section "9. FRONTEND"
rem ===========================================================================

if not exist "package.json" (
    echo [SKIP] no package.json - frontend build not applicable >> "%REPORT%"
) else (
    if exist "node_modules" (
        echo [OK] node_modules present >> "%REPORT%"
    ) else (
        echo [WARN] node_modules missing - run npm install >> "%REPORT%"
        call :flag_warn "node_modules missing"
    )
    if exist "public\build\manifest.json" (
        echo [OK] public/build/manifest.json present >> "%REPORT%"
    ) else (
        echo [WARN] public/build/manifest.json missing >> "%REPORT%"
        call :flag_warn "Vite manifest missing - run npm run build"
    )
    if "%RUN_BUILD%"=="1" (
        call :probe "npm run build"
        call npm run build >> "%REPORT%" 2>&1
        if errorlevel 1 call :flag_critical "npm run build failed"
    ) else (
        echo [SKIP] npm run build - pass /build to enable >> "%REPORT%"
    )
)

rem ===========================================================================
call :section "10. TEST SUITE"
rem ===========================================================================

if "%SKIP_TESTS%"=="1" (
    echo [SKIP] test suite skipped via /quick >> "%REPORT%"
) else (
    call :probe "php artisan test"
    php artisan test >> "%REPORT%" 2>&1
    if errorlevel 1 (
        call :flag_critical "Test suite has failures"
    ) else (
        echo [OK] all tests passed >> "%REPORT%"
    )
)

rem ===========================================================================
call :section "11. APPLICATION LOG"
rem ===========================================================================

if exist "storage\logs\laravel.log" (
    call :probe "laravel.log error counts"
    powershell -NoProfile -Command "$c = @(Get-Content 'storage/logs/laravel.log'); 'lines={0}' -f $c.Count; 'ERROR={0}' -f @($c | Select-String -SimpleMatch '.ERROR:').Count; 'CRITICAL={0}' -f @($c | Select-String -SimpleMatch '.CRITICAL:').Count; 'EMERGENCY={0}' -f @($c | Select-String -SimpleMatch '.EMERGENCY:').Count" >> "%REPORT%" 2>&1
    call :probe "laravel.log last 200 lines"
    powershell -NoProfile -Command "Get-Content 'storage/logs/laravel.log' -Tail 200" >> "%REPORT%" 2>&1
) else (
    echo [INFO] storage/logs/laravel.log not present >> "%REPORT%"
)

rem ===========================================================================
call :section "12. SUMMARY"
rem ===========================================================================

echo critical failures : %CRITICAL% >> "%REPORT%"
echo warnings          : %WARNINGS% >> "%REPORT%"

echo.
echo  ------------------------------------------------
echo   critical failures : %CRITICAL%
echo   warnings          : %WARNINGS%
echo   report            : %REPORT%
echo  ------------------------------------------------
echo.

popd

if %CRITICAL% GTR 0 (
    echo AUDIT FAILED - see report for details.
    exit /b 1
)
echo AUDIT PASSED.
exit /b 0

rem ===========================================================================
rem  Helpers
rem ===========================================================================

:section
echo. >> "%REPORT%"
echo =========================================================== >> "%REPORT%"
echo  %~1 >> "%REPORT%"
echo =========================================================== >> "%REPORT%"
echo   [*] %~1
exit /b 0

:probe
echo. >> "%REPORT%"
echo --- %~1 ---------------------------------------- >> "%REPORT%"
exit /b 0

:run_c
call :probe "%~1"
echo $ %~2 >> "%REPORT%"
call %~2 >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "%~1 failed"
exit /b 0

:run_w
call :probe "%~1"
echo $ %~2 >> "%REPORT%"
call %~2 >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_warn "%~1 failed"
exit /b 0

:flag_critical
set /a CRITICAL+=1
echo [CRITICAL] %~1 >> "%REPORT%"
echo       [CRITICAL] %~1
exit /b 0

:flag_warn
set /a WARNINGS+=1
echo [WARN] %~1 >> "%REPORT%"
echo       [WARN] %~1
exit /b 0
