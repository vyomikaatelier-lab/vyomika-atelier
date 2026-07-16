# Vyomika Atelier LLP — local Laravel server (live forms, account OTP, lead submissions)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

function Ensure-Composer {
    if (Get-Command composer -ErrorAction SilentlyContinue) {
        return "composer"
    }
    if (-not (Test-Path "composer.phar")) {
        Write-Host "Downloading Composer..."
        Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile "composer.phar"
    }
    return "php composer.phar"
}

if (-not (Test-Path ".env")) {
    if (Test-Path ".env.preview") {
        Copy-Item ".env.preview" ".env"
    } else {
        Copy-Item ".env.example" ".env"
    }
    (Get-Content ".env") `
        -replace 'APP_URL=.*', 'APP_URL=http://127.0.0.1:8000' `
        -replace 'APP_PREVIEW_BAR=true', 'APP_PREVIEW_BAR=false' `
        -replace 'DB_CONNECTION=.*', 'DB_CONNECTION=sqlite' `
        -replace 'SESSION_DRIVER=.*', 'SESSION_DRIVER=file' `
        -replace 'CACHE_STORE=.*', 'CACHE_STORE=file' | Set-Content ".env"
}

if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
}

$composer = Ensure-Composer
if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "Installing PHP dependencies (first run may take a few minutes)..."
    if (-not (Test-Path "cacert.pem")) {
        Invoke-WebRequest -Uri "https://curl.se/ca/cacert.pem" -OutFile "cacert.pem"
    }
    $ca = Join-Path $PSScriptRoot "cacert.pem"
    & php -d curl.cainfo=$ca -d openssl.cafile=$ca $(if ($composer -eq "composer") { "composer" } else { "composer.phar" }) install --no-interaction
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "Composer failed (often Avast/antivirus SSL scanning)." -ForegroundColor Yellow
        Write-Host "Fix: pause HTTPS scanning briefly, then run:" -ForegroundColor Yellow
        Write-Host "  php -d curl.cainfo=cacert.pem -d openssl.cafile=cacert.pem composer.phar install" -ForegroundColor Yellow
        exit 1
    }
}

$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch 'APP_KEY=base64:') {
    php artisan key:generate --force
}

php artisan migrate --force
php artisan db:seed --force

Write-Host ""
Write-Host "  Live site:  http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "  Account:    http://127.0.0.1:8000/account/login"
Write-Host "  Admin:      http://127.0.0.1:8000/admin/login  (admin@vyomikaatelier.com — local default if ADMIN_PASSWORD unset)"
Write-Host ""
Write-Host "  Static preview (no forms): http://127.0.0.1:8765"
Write-Host ""

php artisan serve --host=127.0.0.1 --port=8000
