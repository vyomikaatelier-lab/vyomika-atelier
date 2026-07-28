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
call :run_w "Composer" "composer --version"
call :run_w "Node" "node -v"
call :run_w "NPM" "npm -v"

call :probe "PHP ini limits"
php -r "foreach (['post_max_size','upload_max_filesize','memory_limit','max_execution_time','max_file_uploads','max_input_vars'] as $k) { echo str_pad($k, 22), '= ', ini_get($k), PHP_EOL; }" >> "%REPORT%" 2>&1

call :probe "PHP extensions"
php -r "$need=['pdo','mbstring','openssl','tokenizer','xml','ctype','json','fileinfo','curl','gd']; $miss=[]; foreach($need as $e){ if(!extension_loaded($e)){ $miss[]=$e; } } echo $miss ? 'MISSING: '.implode(', ',$miss).PHP_EOL : 'All required extensions loaded.'.PHP_EOL; exit($miss?1:0);" >> "%REPORT%" 2>&1
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
php -r "$f='.env'; if(!is_file($f)){exit(0);} $safe=['APP_ENV','APP_DEBUG','APP_URL','LOG_CHANNEL','LOG_LEVEL','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','CACHE_STORE','SESSION_DRIVER','QUEUE_CONNECTION','FILESYSTEM_DISK','MAIL_MAILER']; foreach(file($f, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){ $line=trim($line); if($line==='' || $line[0]==='#'){continue;} $p=array_pad(explode('=',$line,2),2,''); if(in_array(trim($p[0]),$safe,true)){ echo $line, PHP_EOL; } }" >> "%REPORT%" 2>&1

call :probe "Production sanity - APP_DEBUG"
php -r "$env=[]; if(is_file('.env')){ foreach(file('.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){ $l=trim($l); if($l==='' || $l[0]==='#'){continue;} $p=array_pad(explode('=',$l,2),2,''); $env[trim($p[0])]=trim(trim($p[1]), chr(34).chr(39)); } } $appEnv=$env['APP_ENV'] ?? ''; $debug=strtolower($env['APP_DEBUG'] ?? ''); echo 'APP_ENV=', $appEnv, ' APP_DEBUG=', $debug, PHP_EOL; if($appEnv==='production' && in_array($debug,['true','1','on'],true)){ echo 'WARN: APP_DEBUG enabled while APP_ENV=production', PHP_EOL; exit(1); }" >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_warn "APP_DEBUG enabled in production"

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
    php -r "$j=json_decode(file_get_contents(getenv('ROUTEJSON')), true) ?: []; $admin=array_filter($j, fn($r)=>str_starts_with($r['uri'] ?? '', 'admin')); $writes=array_filter($admin, fn($r)=>preg_match('/POST|PUT|PATCH|DELETE/', $r['method'] ?? '')); echo 'total routes       : ', count($j), PHP_EOL; echo 'admin routes       : ', count($admin), PHP_EOL; echo 'admin write routes : ', count($writes), PHP_EOL;" >> "%REPORT%" 2>&1
)

call :probe "Admin route table"
php artisan route:list --path=admin --columns=method,uri,name,action >> "%REPORT%" 2>&1

call :probe "Admin routes handled by closures"
php -r "$f=getenv('ROUTEJSON'); if(!is_file($f)){exit(0);} $j=json_decode(file_get_contents($f), true) ?: []; $bad=0; foreach($j as $r){ if(!str_starts_with($r['uri'] ?? '', 'admin')){continue;} if(($r['action'] ?? '')==='Closure'){ echo 'CLOSURE: ', $r['method'], ' ', $r['uri'], PHP_EOL; $bad++; } } if(!$bad){ echo 'All admin routes resolve to controller actions.', PHP_EOL; }" >> "%REPORT%" 2>&1

rem ===========================================================================
call :section "7. STATIC INTEGRITY CHECKS"
rem ===========================================================================

call :probe "PHP lint - app routes config database tests"
php -r "$dirs=['app','routes','config','database','tests']; $bad=0; foreach($dirs as $d){ if(!is_dir($d)){continue;} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d)); foreach($it as $f){ if(!$f->isFile() || $f->getExtension()!=='php'){continue;} $out=[]; $rc=0; exec('php -l ' . escapeshellarg($f->getPathname()) . ' 2>&1', $out, $rc); if($rc!==0){ echo 'SYNTAX ERROR: ', $f->getPathname(), PHP_EOL, implode(PHP_EOL, $out), PHP_EOL; $bad++; } } } echo $bad ? ('Lint failures: '.$bad.PHP_EOL) : ('PHP lint clean.'.PHP_EOL); exit($bad?1:0);" >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_critical "PHP syntax errors detected"

call :probe "Admin POST forms without validation error output"
php -r "$dir='resources/views/admin'; if(!is_dir($dir)){echo 'no admin views', PHP_EOL; exit(0);} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)); $bad=0; foreach($it as $f){ if(!$f->isFile() || !str_ends_with($f->getFilename(), '.blade.php')){continue;} $c=file_get_contents($f->getPathname()); if(!preg_match('/<form/i', $c)){continue;} if(!preg_match('/method\s*=\s*.(post|POST)/', $c)){continue;} if(str_contains($c,'@error') || str_contains($c,'errors->any()') || str_contains($c,'errors->all()') || str_contains($c,'admin.partials.errors')){continue;} echo 'NO ERROR DISPLAY: ', str_replace(chr(92), '/', $f->getPathname()), PHP_EOL; $bad++; } if(!$bad){ echo 'All admin POST forms surface validation errors.', PHP_EOL; }" >> "%REPORT%" 2>&1

call :probe "Checkbox booleans defaulting to true"
php -r "$dir='app/Http/Controllers'; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)); $bad=0; foreach($it as $f){ if(!$f->isFile() || $f->getExtension()!=='php'){continue;} foreach(file($f->getPathname()) as $n=>$line){ if(preg_match('/->boolean\(\s*.[A-Za-z0-9_]+.\s*,\s*true\s*\)/', $line)){ echo 'DEFAULT-TRUE: ', basename($f->getPathname()), ':', $n+1, ' ', trim($line), PHP_EOL; $bad++; } } } if(!$bad){ echo 'No default-true checkbox reads found.', PHP_EOL; }" >> "%REPORT%" 2>&1

call :probe "Admin controllers vs feature test coverage"
php -r "$dir='app/Http/Controllers/Admin'; if(!is_dir($dir)){exit(0);} $tests=''; if(is_dir('tests')){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests')); foreach($it as $f){ if($f->isFile() && $f->getExtension()==='php'){ $tests .= file_get_contents($f->getPathname()); } } } foreach(glob($dir.'/*.php') as $c){ $base=basename($c, '.php'); $module=strtolower(str_replace('AdminController','',$base)); $hit=stripos($tests, $module)!==false; echo str_pad($base, 46), $hit ? 'covered' : 'NO TEST REFERENCE', PHP_EOL; }" >> "%REPORT%" 2>&1

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
composer validate --no-check-publish --no-check-all >> "%REPORT%" 2>&1
if errorlevel 1 call :flag_warn "composer validate reported problems"

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
